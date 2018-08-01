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

define('HZDSB_PLUGIN_VER', '1.2');
/**
 * Installation Guild:
 * 		- put "hazHider.php" into "./inc/plugins/"
 *		- Active "hazHider" in the admin panel.
 */

function hazDiscordShoutbox_info()
{
    return array(
        "name"          => "Haz Shoutbox with Discord",
        "description"   => "Integrate Shoutbox with my Discord channel",
        "website"       => "",
        "author"        => "Hazmole",
        "authorsite"    => "",
        "version"       => HZDSB_PLUGIN_VER,
        "guid"          => "",
        "codename"      => str_replace('.php', '', basename(__FILE__)),
        "compatibility" => "*"
    );
}

//$plugins->add_hook("parse_message", "hazhider_run");


// Install plugin
function hazDiscordShoutbox_install() {
	global $db;
	// Get SettingGroup DisplayOrder
	$query	= $db->simple_select("settinggroups", "COUNT(*) as rows");
	$dis_order = $db->fetch_field($query, 'rows') + 1;
	// Insert SettingGroup and get Group ID
	$group_id = $db->insert_query('settinggroups', array(
		'name'			=> 'hazDiscordShoutbox',
		'title'			=> 'Haz Shoutbox with Discord',
		'description'	=> 'display details ...',
		'disporder'		=> $dis_order,
		'isdefault'		=> '0'
	));
	// Insert Setting
	$hazDiscordShoutbox_setting = hazDiscordShoutbox_getPluginSettingArray($group_id);
	$db->insert_query_multiple("settings", $hazDiscordShoutbox_setting);
	// Rebuild
	rebuild_settings();
}
function hazDiscordShoutbox_uninstall() {
	global $db;
	// Get This SettingGroup ID
	$group_id = $db->fetch_field(
		$db->simple_select('settinggroups', 'gid', "name='hazDiscordShoutbox'")
		, 'gid' );
	// Delete Setting
	$db->delete_query('settings', 'gid=' . $group_id);
	$db->delete_query("settinggroups", "name = 'hazDiscordShoutbox'");
	// Rebuild
	rebuild_settings();
}
function hazDiscordShoutbox_is_installed() {
	global $db;
	$query = $db->simple_select("settinggroups", "COUNT(*) as rows", "name = 'hazDiscordShoutbox'");
	$rows  = $db->fetch_field($query, 'rows');
	return ($rows > 0);
}

function hazDiscordShoutbox_activate() {
	global $db;
	// Add Global Templates
	$my_template = hazDiscordShoutbox_getMyTemplateContent();
	foreach ($my_template as $title => $template) {
		$new_global_template = array(
			'title' => $db->escape_string($title), 
			'template' => $db->escape_string($template), 
			'sid' => '-1', 
			'version' => HZDSB_PLUGIN_VER, 
			'dateline' => TIME_NOW);
		$db->insert_query("templates", $new_global_template);
	}

	// Modify Default Templates
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("index",  '#{\$forums}#', "<!-- hazShoutbox -->{\$haz_shoutbox}{\$hazdsb_smilies_json}{\$hazdsb_include}<!-- /hazShoutbox -->{\$forums}");
}
function hazDiscordShoutbox_deactivate() {
	global $db;
	// Remove Global Templates
	$my_template = hazDiscordShoutbox_getMyTemplateContent();
	foreach ($my_template as $title => $template) {
		$db->delete_query("templates", "`title` = '".$db->escape_string($title)."'");
	}
	// Recover Default Templates
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("index", 	'#\<!--\shazShoutbox\s--\>(.+)\<!--\s\/hazShoutbox\s--\>#is', '', 0);
}



global $settings;

if ($settings['hazDiscordShoutbox_online']) {
	$plugins->add_hook('index_start', 'hazDiscordShoutbox_buildShoubox');
}

function hazDiscordShoutbox_buildShoubox($message) {
	// Set pattern & replacing string
	global $lang, $mybb, $templates, $theme, $collapsed, $settings, $cache;
	global $haz_shoutbox, $hazdsb_include, $hazdsb_reply, $hazdsb_invite, $hazdsb_smilies_json;
/*
	if (!$lang->rinshoutbox) {
		$lang->load('rinshoutbox');
	}
*/
	if( $mybb->user["uid"]!=0 || $settings['hazDiscordShoutbox_guest'] ){
		$hazdsb_reply = hazDiscordShoutbox_getReplyTemplate($mybb->user["username"]);
		$hazdsb_invite= hazDiscordShoutbox_getInviteTemplate( true );
	}
	else
		$hazdsb_invite= hazDiscordShoutbox_getInviteTemplate( false );

//	echo "<textarea>";

	$smilie_json = "";
    $smilies = $cache->read("smilies");
	foreach($smilies as $sid => $smilie){
		//if($smilie["disporder"]<=100)
		$smilie_json .= "{find:'".preg_quote(preg_quote($smilie["find"]))."',image:'".$smilie["image"]."'},";
	}
	$smilie_json = "[".$smilie_json."];";

//	echo "</textarea>";

	eval("\$haz_shoutbox = \"".$templates->get("hazdsb_shoutbox")."\";");
	eval("\$hazdsb_include = \"".$templates->get("hazdsb_header_include")."\";");
	eval("\$hazdsb_smilies_json = \"<script type='text/javascript'> hazdsb_smilies_json=".$smilie_json."</script>\";");
}




function hazDiscordShoutbox_getPluginSettingArray($group_id){
	$dorder = 1;

	$setting[] = array(
		'name' => 'hazDiscordShoutbox_online',
		'title' => 'Shoutbox Online',
		'description' => 'set shoutbox online or not',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);
	$setting[] = array(
		'name' => 'hazDiscordShoutbox_server',
		'title' => 'Websocket URL',
		'description' => 'Shoutbox websocket URL',
		'optionscode' => 'text',
		'value' => 'wss://transmitter.hazmoleaws.work:8001',
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);
	$setting[] = array(
		'name' => 'hazDiscordShoutbox_widget',
		'title' => 'Widget URL',
		'description' => 'Channels widget URL',
		'optionscode' => 'text',
		'value' => 'https://discordapp.com/api/guilds/326606306934915074/widget.json',
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);
	$setting[] = array(
		'name' => 'hazDiscordShoutbox_guest',
		'title' => 'Guest using permission',
		'description' => 'Can guest using the shoutbox?',
		'optionscode' => 'yesno',
		'value' => 0,
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);
	$setting[] = array(
		'name' => 'hazDiscordShoutbox_title',
		'title' => 'Title',
		'description' => '',
		'optionscode' => 'text',
		'value' => '聊天室',
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);
	$setting[] = array(
		'name' => 'hazDiscordShoutbox_height',
		'title' => 'Shoutbox height',
		'description' => 'height (px)',
		'optionscode' => 'numeric',
		'value' => '400',
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);

	return $setting;
}

function hazDiscordShoutbox_getMyTemplateContent(){
	$my_template['hazdsb_header_include'] = 
"<script src='{\$mybb->asset_url}/jscripts/hazmole/discordshoutbox/hazDiscordShoutbox.js?ver=".HZDSB_PLUGIN_VER."'></script>
<script src='https://twemoji.maxcdn.com/twemoji.min.js'></script>  
<link rel='stylesheet' href='{\$mybb->asset_url}/jscripts/hazmole/discordshoutbox/hazDiscordShoutbox.css?ver=".HZDSB_PLUGIN_VER."' type='text/css'/>
<script type=\"text/javascript\">
	hazdsb_server = '{\$mybb->settings['hazDiscordShoutbox_server']}';
	hazdsb_widget = '{\$mybb->settings['hazDiscordShoutbox_widget']}';
	\$(document).ready(function() {
		twemoji.size = '16x16';
		hazDiscordShoutbox_connect();
		hazDiscordShoutbox_buildOnline();
	});
</script>";

	$my_template['hazdsb_shoutbox'] = 
"<table border=\"0\" cellspacing=\"0\" cellpadding=\"4\" class=\"tborder tShout\">
	<thead>
		<tr>
			<td colspan=2 class=\"thead\" colspan=\"1\">
				<div class=\"expcolimage\"><img src=\"{\$theme['imgdir']}/collapse{\$collapsedimg['rshout']}.png\" id=\"rshout_img\" class=\"expander\" alt=\"[-]\" title=\"[-]\" /></div>
				<div><strong>{\$mybb->settings['hazDiscordShoutbox_title']}</strong></div>
			</td>
		</tr>
	</thead>
	<tbody id=\"rshout_e\">
		<tr><td colspan=2 class=\"tcat\"><span class=\"smalltext\"><strong><span>{\$lang->hazDiscordShoutbox_notice_msg}(告示) : </span><span class='notshow'></span></strong></span></td>
		</tr>
		<tr>
			<td class=\"trow2\" style=\"max-width:400px;\">
				<div class=\"contentShout\">
					<div id=\"hazdsb_shoutarea\" class=\"shoutarea wrapShout\" style=\"height:{\$mybb->settings['hazDiscordShoutbox_height']}px;\"></div>
				</div>
			</td>
			<td style=\"width:200px;\">{\$hazdsb_invite}</td>
		</tr>
	</tbody>
	{\$hazdsb_reply}
</table>";

	return $my_template;
}

function hazDiscordShoutbox_getReplyTemplate($user_name){
	$template = 
"<tbody>
	<script>hazdsb_user = \"".preg_quote($user_name)."\"</script>
	<tr>
		<td colspan=2><input id=\"hazdsb_shout\" type=\"text\" placeholder=\"說些什麼吧\"></td>
	</tr>
</tbody>";
	return $template;
}

function hazDiscordShoutbox_getInviteTemplate( $is_member ){
	global $mybb;
	$height = $mybb->settings['hazDiscordShoutbox_height'];

	$btn_id    = ($is_member)? "hazdsb_invite_btn": "";
	$btn_color = ($is_member)? "#555": "red";
	$btn_text  = ($is_member)? "加入官方聊天室": "註冊加入聊天！";

	$template = 
"<div style=\"width:100%;height:".$height."px;background:black;color:white;text-align:center;\">
	<div style=\"padding: 20px 20px 5px 20px;height:40px;background-color:#738bd7;\">
		<div style=\"width:160px; height:30px;background:url(https://discordapp.com/assets/35d75407bd75d70e84e945c9f879bab8.svg) 50% no-repeat;\"></div>
	</div>
	<div style=\"height:20px;font-size:16px; padding:5px;\">在線名單</div>
	<div id=\"hazdsb_online\" style=\"margin:5px;height:".($height-170)."px;border:1px solid #ccc;overflow-y:auto;text-align:left;\">
	</div>
	<a id=\"".$btn_id."\" href=\"./member.php?action=register\" target=\"_blank\">
		<div style=\"height:20px;width:150px;overflow:hidden;background:".$btn_color.";border-radius:20px;padding:15px;margin:10px;\">
			<span style=\"font-size:16px;color:white;\">".$btn_text."</span>
		</div>
	</a>
</div>";
	return $template;
}
?>