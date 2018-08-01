<?php
/**
 * Haz Discord Webhook with Tasks
 * Copyright 2018 Hazmole, All Rights Reserved
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

define('HZDWHT_PLUGIN_VER', '1.1');
/**
 * Installation Guild:
 * 		
 */

function hazDiscordWebhook_task_info()
{
    return array(
        "name"          => "Haz Webhook with Discord using Task",
        "description"   => "Integrate Notice with my Discord webhook & Mybb's Task",
        "website"       => "",
        "author"        => "Hazmole",
        "authorsite"    => "",
        "version"       => HZDWHT_PLUGIN_VER,
        "guid"          => "",
        "codename"      => str_replace('.php', '', basename(__FILE__)),
        "compatibility" => "*"
    );
}


// Install plugin
function hazDiscordWebhook_task_install() {
	global $db;
	// Get SettingGroup DisplayOrder
	$query	= $db->simple_select("settinggroups", "COUNT(*) as rows");
	$dis_order = $db->fetch_field($query, 'rows') + 1;
	// Insert SettingGroup and get Group ID
	$group_id = $db->insert_query('settinggroups', array(
		'name'			=> 'hazDiscordWebhookTask',
		'title'			=> 'Haz Notice with Discord Webhook & Task',
		'description'	=> 'setting details ...',
		'disporder'		=> $dis_order,
		'isdefault'		=> '0'
	));

	// Insert Setting
	$hazDiscordWebhook_task_setting = hazDiscordWebhook_task_getPluginSettingArray($group_id);
	$db->insert_query_multiple("settings", $hazDiscordWebhook_task_setting);
	// Rebuild
	rebuild_settings();
}
function hazDiscordWebhook_task_uninstall() {
	global $db;
	// Get This SettingGroup ID
	$group_id = $db->fetch_field(
		$db->simple_select('settinggroups', 'gid', "name='hazDiscordWebhookTask'")
		, 'gid' );
	// Delete Setting
	$db->delete_query('settings', 'gid=' . $group_id);
	$db->delete_query("settinggroups", "name = 'hazDiscordWebhookTask'");
	// Rebuild
	rebuild_settings();
}
function hazDiscordWebhook_task_is_installed() {
	global $db;
	$query = $db->simple_select("settinggroups", "COUNT(*) as rows", "name = 'hazDiscordWebhookTask'");
	$rows  = $db->fetch_field($query, 'rows');
	return ($rows > 0);
}
function hazDiscordWebhook_task_activate() {
	// Modify Default Templates
}
function hazDiscordWebhook_task_deactivate() {
	// Recover Default Templates
}




function hazDiscordWebhook_task_getPluginSettingArray($group_id){
	$dorder = 1;

	$setting[] = array(
		'name' => 'hazDiscordWebhookTask_webhook_url',
		'title' => 'Webhook URL',
		'description' => 'Channel websocket URL',
		'optionscode' => 'text',
		'value' => '',
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);
	$setting[] = array(
		'name' => 'hazDiscordWebhookTask_detectThreadPrefix',
		'title' => 'Available ThreadPrefix',
		'description' => 'Which forum article/post will be detect',
		'optionscode' => 'text',
		'value' => '2.1【招收中】',
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);
	$setting[] = array(
		'name' => 'hazDiscordWebhookTask_title',
		'title' => 'Notice Title',
		'description' => 'what will notice-chan say when she do the task.',
		'optionscode' => 'text',
		'value' => '眾卿！以下為正招募意願者之團務！',
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);

	return $setting;
}
?>