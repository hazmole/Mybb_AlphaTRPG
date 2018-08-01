<?php
/**
 * Haz Phide
 * Copyright 2015 Hazmole, All Rights Reserved
 * License: http://www.mybb.com/about/license
 *
 * Get a Great help of Sephiroth's [Spoiler MyCode] plugin
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

define('HZDWH_PLUGIN_VER', '1.0');
/**
 * Installation Guild:
 * 		- put "hazHider.php" into "./inc/plugins/"
 *		- Active "hazHider" in the admin panel.
 */

function hazDiscordWebhook_info()
{
    return array(
        "name"          => "Haz Webhook with Discord",
        "description"   => "Integrate Notice with my Discord webhook",
        "website"       => "",
        "author"        => "Hazmole",
        "authorsite"    => "",
        "version"       => HZDWH_PLUGIN_VER,
        "guid"          => "",
        "codename"      => str_replace('.php', '', basename(__FILE__)),
        "compatibility" => "*"
    );
}


// Install plugin
function hazDiscordWebhook_install() {
	global $db;
	// Get SettingGroup DisplayOrder
	$query	= $db->simple_select("settinggroups", "COUNT(*) as rows");
	$dis_order = $db->fetch_field($query, 'rows') + 1;
	// Insert SettingGroup and get Group ID
	$group_id = $db->insert_query('settinggroups', array(
		'name'			=> 'hazDiscordWebhook',
		'title'			=> 'Haz Notice with Discord Webhook',
		'description'	=> 'setting details ...',
		'disporder'		=> $dis_order,
		'isdefault'		=> '0'
	));
	// Insert Setting
	$hazDiscordWebhook_setting = hazDiscordWebhook_getPluginSettingArray($group_id);
	$db->insert_query_multiple("settings", $hazDiscordWebhook_setting);
	// Rebuild
	rebuild_settings();
}
function hazDiscordWebhook_uninstall() {
	global $db;
	// Get This SettingGroup ID
	$group_id = $db->fetch_field(
		$db->simple_select('settinggroups', 'gid', "name='hazDiscordWebhook'")
		, 'gid' );
	// Delete Setting
	$db->delete_query('settings', 'gid=' . $group_id);
	$db->delete_query("settinggroups", "name = 'hazDiscordWebhook'");
	// Rebuild
	rebuild_settings();
}
function hazDiscordWebhook_is_installed() {
	global $db;
	$query = $db->simple_select("settinggroups", "COUNT(*) as rows", "name = 'hazDiscordWebhook'");
	$rows  = $db->fetch_field($query, 'rows');
	return ($rows > 0);
}

function hazDiscordWebhook_activate() {
	// Modify Default Templates
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("newthread_postoptions",  '#{\$disablesmilies}#', "{\$disablesmilies}<!-- hazDiscordNotice -->{\$haz_hazDiscordNotice_options}<!-- /hazDiscordNotice -->");
}
function hazDiscordWebhook_deactivate() {
	// Recover Default Templates
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("newthread_postoptions", 	'#\<!--\shazDiscordNotice\s--\>(.+)\<!--\s\/hazDiscordNotice\s--\>#is', '', 0);
}


global $settings;

if ($settings['hazDiscordWebhook_detectPost']) {
	$plugins->add_hook('newreply_do_newreply_end', 'hazDiscordWebhook_noticePost');
}
$plugins->add_hook('newthread_do_newthread_end', 'hazDiscordWebhook_noticeThread');


$plugins->add_hook("newthread_start",		"hazDiscordWebhook_buildOptions");

//$plugins->add_hook('showthread_end', 'hazDiscordWebhook_test');


function hazDiscordWebhook_noticeThread() {
	global $settings, $mybb, $tid, $forum;

	$postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);

	if(isset($postoptions["notice"]) && !$mybb->get_input('savedraft') && in_array((int)$forum['fid'],explode(',',$mybb->settings['hazDiscordWebhook_detectForum']))) {
		$topic 		= $forum['name']."-".$mybb->input['subject'];
		$thread_url = $settings['bburl'] . '/' . get_thread_link($tid);
		$message 	= $settings['hazDiscordWebhook_new_thread'];

		hazDiscordWebhook_send($message, $topic, $thread_url);
	}

}

function hazDiscordWebhook_noticePost() {
	global $settings, $mybb, $tid, $forum, $url, $thread;

	if(!$mybb->get_input('savedraft') && in_array((int)$forum['fid'],explode(',',$mybb->settings['hazDiscordWebhook_detectForum']))) {
		$topic    = $forum['name']."-".$thread['subject'];
		$post_url = $settings['bburl'] . '/' . htmlspecialchars_decode($url);
		$message  = "「".$topic."」 ".$settings['hazDiscordWebhook_new_post'];

		hazDiscordWebhook_send($message, $topic, $post_url);
	}
}


function hazDiscordWebhook_send($text, $topic, $topic_url){
	global $settings;

	$url = $settings['hazDiscordWebhook_webhook_url'];
	$data = array(
        'content' => $text,
        'name' => "",
        'avatar_url' => "",
        'embeds' => array(array(
        		"title" => $topic,
        		"descrption" => "描述",
        		"url" => $topic_url
        		)
        	)
    );
	$data_string = json_encode($data);

	$curl = curl_init();

	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

	$output = curl_exec($curl);
	//$output = json_decode($output, true);

	curl_close($curl);
	return $output;
}


function hazDiscordWebhook_buildOptions(){
	global $haz_hazDiscordNotice_options;

	$templates = "<br/><label>"
."<input type='checkbox' class='checkbox' name='postoptions[notice]' value='1' tabindex='11' checked>"
."<strong> 允許通知︰</strong> 在聊天室中進行通知。"
."</label>";
	eval("\$haz_hazDiscordNotice_options .= \"".($templates)."\";");
}


function hazDiscordWebhook_getPluginSettingArray($group_id){
	$dorder = 1;

	$setting[] = array(
		'name' => 'hazDiscordWebhook_webhook_url',
		'title' => 'Webhook URL',
		'description' => 'Channel websocket URL',
		'optionscode' => 'text',
		'value' => '',
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);
	$setting[] = array(
		'name' => 'hazDiscordWebhook_detectForum',
		'title' => 'Available Forum',
		'description' => 'Which forum article/post will be detect',
		'optionscode' => 'forumselect',
		'value' => '',
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);
	$setting[] = array(
		'name' => 'hazDiscordWebhook_detectPost',
		'title' => 'Enable post detect',
		'description' => '',
		'optionscode' => 'yesno',
		'value' => 0,
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);
	$setting[] = array(
		'name' => 'hazDiscordWebhook_new_thread',
		'title' => 'New Thread Reply',
		'description' => 'what will notice-chan say when there is new article.',
		'optionscode' => 'text',
		'value' => '眾卿！已有新的文章問世！',
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);
	$setting[] = array(
		'name' => 'hazDiscordWebhook_new_post',
		'title' => 'New Post Reply',
		'description' => 'what will notice-chan say when there is new post.',
		'optionscode' => 'text',
		'value' => '有新回覆，不吝賜教。',
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);

	return $setting;
}
?>