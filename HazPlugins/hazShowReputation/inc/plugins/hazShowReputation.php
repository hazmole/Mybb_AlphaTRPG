<?php
/**
 * Haz Phide
 * Copyright 2015 Hazmole, All Rights Reserved
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

define('HZSRP_PLUGIN_VER', '1.2');

function hazShowReputation_info()
{
    return array(
        "name"          => "Haz Show Reputation",
        "description"   => "show the Reputation in each post.",
        "website"       => "",
        "author"        => "Hazmole",
        "authorsite"    => "",
        "version"       => HZSRP_PLUGIN_VER,
        "guid"          => "",
        "codename"      => str_replace('.php', '', basename(__FILE__)),
        "compatibility" => "*"
    );
}

// Hook
$plugins->add_hook("postbit",	"hazShowReputation_buildLogBox");

// Install plugin
function hazShowReputation_install() {
	global $db;
	// Get SettingGroup DisplayOrder
	$query	= $db->simple_select("settinggroups", "COUNT(*) as rows");
	$dis_order = $db->fetch_field($query, 'rows') + 1;
	// Insert SettingGroup and get Group ID
	$group_id = $db->insert_query('settinggroups', array(
		'name'			=> 'hazShowReputation',
		'title'			=> 'Haz Show Reputation',
		'description'	=> 'display details ...',
		'disporder'		=> $dis_order,
		'isdefault'		=> '0'
	));
	// Insert Setting
	$hazShowReputation_setting = hazShowReputation_getPluginSettingArray($group_id);
	$db->insert_query_multiple("settings", $hazShowReputation_setting);
	// Rebuild
	rebuild_settings();
}
function hazShowReputation_uninstall() {
	global $db;
	// Get This SettingGroup ID
	$group_id = $db->fetch_field(
		$db->simple_select('settinggroups', 'gid', "name='hazShowReputation'")
		, 'gid' );
	// Delete Setting
	$db->delete_query('settings', 'gid=' . $group_id);
	$db->delete_query("settinggroups", "name = 'hazShowReputation'");
	// Rebuild
	rebuild_settings();
}
function hazShowReputation_is_installed() {
	global $db;
	$query = $db->simple_select("settinggroups", "COUNT(*) as rows", "name = 'hazShowReputation'");
	$rows  = $db->fetch_field($query, 'rows');
	return ($rows > 0);
}

// activate plugin
function hazShowReputation_activate() {
	global $db;
	// Add Global Templates
	$my_template = hazShowReputation_getMyTemplateContent();
	foreach ($my_template as $title => $template) {
		$new_global_template = array(
			'title' => $db->escape_string($title), 
			'template' => $db->escape_string($template), 
			'sid' => '-1', 
			'version' => HZSRP_PLUGIN_VER, 
			'dateline' => TIME_NOW);
		$db->insert_query("templates", $new_global_template);
	}
	//Modify Default Templates
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('postbit', 		 '#'.preg_quote('{$post[\'attachments\']}').'#', '<!-- hazShowRep -->{$post[\'hazRep_log\']}<!-- /hazShowRep -->{\$post[\'attachments\']}');
	find_replace_templatesets('postbit_classic', '#'.preg_quote('{$post[\'attachments\']}').'#', '<!-- hazShowRep -->{$post[\'hazRep_log\']}<!-- /hazShowRep -->{\$post[\'attachments\']}');
}
// deactivate plugin
function hazShowReputation_deactivate() {
	global $db;
	// Remove Global Templates
	$my_template = hazShowReputation_getMyTemplateContent();
	foreach ($my_template as $title => $template) {
		$db->delete_query("templates", "`title` = '".$db->escape_string($title)."'");
	}
	// Recover Default Templates
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('postbit', 		 '#\<!--\shazShowRep\s--\>(.+)\<!--\s/hazShowRep\s--\>#is', '', 0);
	find_replace_templatesets('postbit_classic', '#\<!--\shazShowRep\s--\>(.+)\<!--\s/hazShowRep\s--\>#is', '', 0);
}


// For showThread, show each post's Rolling result.
function hazShowReputation_buildLogBox(&$post) {
	global $db, $mybb, $lang, $templates, $theme, $settings;;
	
	$lang->load('hazShowReputation');
	$max_display_num = $settings['hazShowReputation_DisplayNum'];

	if($post['pid']){
		$query = $db->simple_select("reputation", "*", "uid = '{$post['uid']}'AND pid = '{$post['pid']}' ORDER BY dateline DESC");
		$count = 0;
		$display_rep_code = "";
		$hidden_rep_code  = "";

		while($hazRep = $db->fetch_array($query)){
			$user = $db->fetch_array($db->simple_select("users", "*", "uid = '".$hazRep['adduid']."'"));

			$add_user = preg_quote($user["username"]);
			$add_num  = ((int)$hazRep["reputation"]>0)? "+".$hazRep["reputation"]: $hazRep["reputation"];
			$reason   = preg_quote($hazRep["comments"]);
			$rep_log = "<div style='font-size:12px;'>
							<span style='margin:0px 10px 2px 5px;'>{$add_user}</span>
							<strong style='margin:0px 10px 2px 5px;'>{$lang->hazShowRep_rep}{$add_num}</strong>
							<span style='color:gray;'> {$reason}</span>
					    </div>";
			
			if($max_display_num!=0 && $count>=$max_display_num)
				$hidden_rep_code  = $rep_log . $hidden_rep_code;
			else
				$display_rep_code = $rep_log . $display_rep_code;
			$count += 1;
		}
		
		// Need Display
		if($count>0){
			if($max_display_num!=0 && $count>$max_display_num){
				$text = "<div>
							<div class=\"hidden_rep\" style=\"display:none;\">{$hidden_rep_code}</div>
							<a href=\"javascript:void(0);\" onclick=\"$(this).parent().children('.hidden_rep').toggle(100);$(this).hide(100);\">{$lang->hazShowRep_more}</a>
						</div>";
			}
			else
				$text = "";
			$text .= $display_rep_code;

			$text = preg_replace("/\"/", "\\\"", $text);
			eval("\$post[hazRep_logContent] .= \"".$text."\";");
			eval("\$post[hazRep_log] .= \"".$templates->get("hzsr_log")."\";");
		}
	}
}

function hazShowReputation_getPluginSettingArray($group_id){
	$dorder = 1;

	$setting[] = array(
		'name' => 'hazShowReputation_DisplayNum',
		'title' => 'Display Reputations Number',
		'description' => 'only display ? reputations, the other older Reps will be folded. (0 = not hidden)',
		'optionscode' => 'numeric',
		'value' => 0,
		'disporder' => $dorder++,
		'gid'		=> $group_id
	);

	return $setting;
}

function hazShowReputation_getMyTemplateContent(){
	$my_template['hzsr_log'] = 
"<div style=\"margin:5px;padding:5px;background:#F7F5EE;border:gray 1px solid;border-radius:5px;\">
	<strong>{\$lang->hazShowRep_log_title}:</strong><br/>
	<table><tbody>
		{\$post[hazRep_logContent]}
	</tbody></table>
</div>";

	return $my_template;
}

?>
