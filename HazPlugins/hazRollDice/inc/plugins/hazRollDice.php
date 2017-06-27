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

define('HZRD_PLUGIN_VER', '2.658');
/**
 * Installation Guild:
 * 		- put "hazRollDice.php" into "./inc/plugins/"
  *		- put "languages/" 		under "./inc/"
 *		- put "hazmole/"		under "./jscripts/"
 *		- Active "hazRollDic" in the admin panel.
 */

function hazRollDice_info()
{
    return array(
        "name"          => "Haz Roll Dices",
        "description"   => "Add Dices rolling function when edit/post",
        "website"       => "",
        "author"        => "Hazmole",
        "authorsite"    => "",
        "version"       => HZRD_PLUGIN_VER,
        "guid"          => "",
        "codename"      => str_replace('.php', '', basename(__FILE__)),
        "compatibility" => "*"
    );
}

// Hook
$plugins->add_hook("newreply_start", 		"hazRollDice_buildRollBar");
$plugins->add_hook("editpost_action_start",	"hazRollDice_buildRollBar");
$plugins->add_hook("newthread_start",		"hazRollDice_buildRollBar");

$plugins->add_hook("newreply_do_newreply_end", 	"hazRollDice_setResultRecord");
$plugins->add_hook("editpost_do_editpost_end", 	"hazRollDice_setResultRecord");
//$plugins->add_hook("newthread_do_newthread_end","hazRollDice_test");

$plugins->add_hook("postbit",	"hazRollDice_buildDiceResult");
$plugins->add_hook("misc_start","hazRollDice_handleResultRecord");

$plugins->add_hook("class_moderation_delete_post","hazRollDice_deleteDiceResult");


// Install plugin
function hazRollDice_install() {
	global $db;
	$db->write_query("CREATE TABLE mybb_haz_rolldice( pid int(10), uid int(10), rid int(10), claim VARCHAR(30), result VARCHAR(300), reason VARCHAR(50))");
}
// Uninstall plugin
function hazRollDice_uninstall() {
	global $db;
	$db->write_query("DROP TABLE mybb_haz_rolldice");
}
function hazRollDice_is_installed() {
	global $db;
	if( $db->table_exists("haz_rolldice") )	return true;
	else	return false;
}
// activate plugin
function hazRollDice_activate() {
	global $db;
	// Add Global Templates
	$my_template = hazRollDice_getMyTemplateContent();
	foreach ($my_template as $row) {
		$db->insert_query("templates", $row);
	}
	// Modify Default Templates
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('newreply', '#{\$posticons}#', '{\$posticons}<!-- hazRollDice -->{$hazDice_bar}<!-- /hazRollDice -->');
	find_replace_templatesets('newthread','#{\$posticons}#', '{\$posticons}<!-- hazRollDice -->{$hazDice_bar}<!-- /hazRollDice -->');
	find_replace_templatesets('editpost', '#{\$posticons}#', '{\$posticons}<!-- hazRollDice -->{$hazDice_bar}<!-- /hazRollDice -->');
	find_replace_templatesets('postbit', 		 '#'.preg_quote('{$post[\'attachments\']}').'#', '<!-- hazRollDice -->{$post[\'hazDice_result\']}<!-- /hazRollDice -->{\$post[\'attachments\']}');
	find_replace_templatesets('postbit_classic', '#'.preg_quote('{$post[\'attachments\']}').'#', '<!-- hazRollDice -->{$post[\'hazDice_result\']}<!-- /hazRollDice -->{\$post[\'attachments\']}');
	
}
// deactivate plugin
function hazRollDice_deactivate() {
	global $db;
	// Remove Global Templates
	$my_template = hazRollDice_getMyTemplateContent();
	foreach ($my_template as $row) {
		$db->delete_query("templates", "`title` = '".$row["title"]."'");
	}
	// Recover Default Templates
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('newreply', '#\<!--\shazRollDice\s--\>(.+)\<!--\s/hazRollDice\s--\>#is', '', 0);
	find_replace_templatesets('newthread','#\<!--\shazRollDice\s--\>(.+)\<!--\s/hazRollDice\s--\>#is', '', 0);
	find_replace_templatesets('editpost', '#\<!--\shazRollDice\s--\>(.+)\<!--\s/hazRollDice\s--\>#is', '', 0);
	find_replace_templatesets('postbit', 		 '#\<!--\shazRollDice\s--\>(.+)\<!--\s/hazRollDice\s--\>#is', '', 0);
	find_replace_templatesets('postbit_classic', '#\<!--\shazRollDice\s--\>(.+)\<!--\s/hazRollDice\s--\>#is', '', 0);
}

// For edit/new post, show rolling button.
function hazRollDice_buildRollBar() {
	global $lang, $db, $mybb , $templates, $theme, $post;
	global $hazDice_result, $hazDice_bar;
	// Load Language pack
	$lang->load('hazRollDice');
	// Set post_id & user_id
	$pid = ($post['pid'])? 	$post['pid']: -1;
	$uid = $mybb->user['uid'];
	// Fetch Data from DataBase
	$post_dices_query = $db->simple_select("haz_rolldice", "*", "pid=".$pid);
	$user_dices_query = $db->simple_select("haz_rolldice", "*", "uid=".$uid." AND pid=0");
	// Loop building
	$text = "";
	$result = null;
	while(null!=($result = $db->fetch_array($post_dices_query))){
		$text .= hazRollDice_getDiceResultTemplate( $result['claim'], $result['result'], $result['reason'], true);
	}
	while(null!=($result = $db->fetch_array($user_dices_query))){
		$text .= hazRollDice_getDiceResultTemplate( $result['claim'], $result['result'], $result['reason'], false);
	}
	if($text=="")	$text="<tr><td>--</td></tr>";
	// Evaluate String to variables
	eval("\$hazDice_result .= \"".$text."\";");	
	eval("\$hazDice_bar .= \"".$templates->get("hzrd_rollbar")."\";");
}

// For showThread, show each post's Rolling result.
function hazRollDice_buildDiceResult(&$post) {
	global $lang, $db, $mybb , $templates, $theme;
	
	$lang->load('hazRollDice');
	
	if($post['pid']){
		$text = "";
		$count= 0;
		$query = $db->simple_select("haz_rolldice", "*", "pid=".$post['pid']);
		
		// Set format
		while(null!=($hazPost = $db->fetch_array($query))){
			if($hazPost["claim"][0]=='s'){
				$text .= "<td colspan='3' style='color:white;background:black;text-align:center;'><strong>--[".$lang->hazRollDice_secret_dice."]--</strong></td>"
				."<td>".preg_replace("/\"/", "\\\"", $hazPost["reason"])."</td></tr>";
			}
			else{
				$text .= hazRollDice_getDiceResultTemplate( $hazPost["claim"], $hazPost["result"], $hazPost["reason"], true);
			}
			$count+=1;
		}
		
		if($count){
			eval("\$post[hazDice_result_text] .= \"".$text."\";");
			eval("\$post[hazDice_result] .= \"".$templates->get("hzrd_resultbar")."\";");
		}
	}
}

// For storing result to SQL
function hazRollDice_handleResultRecord() {
	global $db, $_POST, $mybb;
	
	if($_POST['action'] == "hz_rolldice") {
		$new_record = array(
			"pid" 		=> 0,
			"uid" 		=> $mybb->user['uid'],
			"claim" 	=> $mybb->get_input("haz_dices_claim"),
			"result" 	=> $mybb->get_input("haz_dices_result"),
			"reason" 	=> $db->escape_string($mybb->get_input("haz_dices_reason"))
			);
		$db->insert_query('haz_rolldice', $new_record);
	}
}

// For updating result to SQL
function hazRollDice_setResultRecord() {
	global $db, $mybb, $pid;
	
	if(!$mybb->get_input('savedraft')){
		$uid   = $mybb->user['uid'];
		$query = "UPDATE mybb_haz_rolldice SET pid=".$pid." WHERE uid='".$uid."' AND pid=0;";
		$db->write_query($query);
	}
}


function hazRollDice_deleteDiceResult(){
	global $db, $pids;
	$db->delete_query("haz_rolldice", "pid IN ($pids)");
}


function hazRollDice_getDiceResultTemplate($claim, $result, $reason, $is_confirm){
	if(preg_match( "/→ (.*)$/", $result)==0)
		$result .= "</td><td>";
	else
		$result = preg_replace("/→ (.*)$/", "</td><td> → <b style='color:blue;'>$1</b>", $result);

	$template .= "<tr><td>".($is_confirm? "":": ")
				."<b>".$claim."</b></td>"
				."<td> → ".$result."</td>"
				."<td>".preg_replace("/\"/", "\\\"", $reason)."</td></tr>";
	return $template;
}

function hazRollDice_getMyTemplateContent(){
	$my_template[0] = array(
		"title" 	=> "hzrd_rollbar",
		"template"	=> '<script type="text/javascript" src="{$mybb->asset_url}/jscripts/hazmole/rolldices/hz_rolldice.js?ver='.HZRD_PLUGIN_VER.'"></script>'
						.'<tr>'
						.'<td class="trow2" valign="top">'
							.'<button type="button" onClick="haz_rollingDices();">{$lang->hazRollDice_roll}</button>'
							.'<input id="haz_dices_dice" type="text" placeholder="{$lang->hazRollDice_bar_sample}" maxlength="60" style="width:30%;">'
							.'<input id="haz_dices_reason" type="text" placeholder="{$lang->hazRollDice_bar_reason}" maxlength="100" style="width:40%;">'
						.'</td>'
						.'<td class="trow2" valign="top">'
							.'<div style="margin:5px;padding:5px;background:white;border-radius:5px;">'
								.'<table border="0"><tbody id="haz_dices_result">'
									.'{$hazDice_result}'
								.'</tbody></table>'
							.'</div>'
						.'</td>'
						.'</tr>',
		"sid"		=> -1,
		"version"	=> 1.0,
		"dateline"	=> TIME_NOW
	);
	$my_template[1] = array(
		"title" 	=> "hzrd_resultbar",
		"template"	=> '<div style="margin:5px;padding:5px;background:white;border:1px solid;border-radius:5px;">'
						.'<strong>{$lang->hazRollDice_result_title}</strong><p>'
						.'<table><tbody>'
							.'{\$post[hazDice_result_text]}'
						.'</tbody></table>'
						.'</div>',
		"sid"		=> -1,
		"version"	=> 1.0,
		"dateline"	=> TIME_NOW
	);
	return $my_template;
}

?>
