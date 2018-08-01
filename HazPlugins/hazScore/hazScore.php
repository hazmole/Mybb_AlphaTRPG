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

define('HZSC_PLUGIN_VER', '1.0');
/**
 * Installation Guild:
 * 		- put "hazRollDice.php" into "./inc/plugins/"
  *		- put "languages/" 		under "./inc/"
 *		- put "hazmole/"		under "./jscripts/"
 *		- Active "hazRollDic" in the admin panel.
 */

function hazScore_info()
{
    return array(
        "name"          => "Haz Score",
        "description"   => "Add Score mechanism",
        "website"       => "",
        "author"        => "Hazmole",
        "authorsite"    => "",
        "version"       => HZSC_PLUGIN_VER,
        "guid"          => "",
        "codename"      => str_replace('.php', '', basename(__FILE__)),
        "compatibility" => "*"
    );
}

// Hook
$plugins->add_hook("member_profile_start", "hazScore_buildScoreBarOfProfile");
$plugins->add_hook("usercp_start",  "hazScore_buildScoreBarOfUsercp");
$plugins->add_hook("postbit",  "hazScore_buildScoreBarOfPost");

// activate plugin
function hazScore_install() {
    global $db;
    // Add Score to Database
    $db->write_query("CREATE TABLE mybb_haz_score( 
        pid int(10), uid int(10), sid int(10), score int(10), reason VARCHAR(30))");

    // Add Settings
    // Get SettingGroup DisplayOrder
    $query  = $db->simple_select("settinggroups", "COUNT(*) as rows");
    $dis_order = $db->fetch_field($query, 'rows') + 1;
    // Insert SettingGroup and get Group ID
    $group_id = $db->insert_query('settinggroups', array(
        'name'          => 'hazScore',
        'title'         => 'Haz Score',
        'description'   => '',
        'disporder'     => $dis_order,
        'isdefault'     => '0'
    ));
    // Insert Setting
    $hazScore_setting = hazScore_getPluginSettingArray($group_id);
    $db->insert_query_multiple("settings", $hazScore_setting);
    // Rebuild
    rebuild_settings();
}
function hazScore_uninstall() {
    global $db;
    // Remove Score from Database
    $db->write_query("DROP TABLE mybb_haz_score");

    // Remove Settings
    // Get This SettingGroup ID
    $group_id = $db->fetch_field(
        $db->simple_select('settinggroups', 'gid', "name='hazScore'")
        , 'gid' );
    // Delete Setting
    $db->delete_query('settings', 'gid=' . $group_id);
    $db->delete_query("settinggroups", "name = 'hazScore'");
    // Rebuild
    rebuild_settings();
}
function hazScore_is_installed() {
    global $db;
    if( $db->table_exists("haz_score") ) return true;
    else    return false;
}



// activate plugin
function hazScore_activate() {
	;

    require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets('member_profile', '#{\$reputation}#', '{\$reputation}<!-- hazScore -->{$hazScore_bar}<!-- /hazScore -->');
    find_replace_templatesets('usercp',         '#{\$reputation}#', '{\$reputation}<!-- hazScore -->{$hazScore_bar}<!-- /hazScore -->');
    find_replace_templatesets('postbit_author_user', '#{\$post\[\'replink\'\]}#', '{\$post[\'replink\']}<!-- hazScore --><!-- /hazScore -->');
    find_replace_templatesets('modcp_nav',           '#{\$modcp_nav_users}#', '{\$modcp_nav_users}<!-- hazScore -->{\$hazScore_modcp_nav}<!-- /hazScore -->');
}
// deactivate plugin
function hazScore_deactivate() {
	;

    require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets('member_profile', '#\<!--\shazScore\s--\>(.+)\<!--\s/hazScore\s--\>#is', '', 0);
    find_replace_templatesets('usercp',         '#\<!--\shazScore\s--\>(.+)\<!--\s/hazScore\s--\>#is', '', 0);
    find_replace_templatesets('postbit_author_user', '#\<!--\shazScore\s--\>\<!--\s/hazScore\s--\>#is', '', 0);
    find_replace_templatesets('modcp_nav',           '#\<!--\shazScore\s--\>\<!--\s/hazScore\s--\>#is', '', 0);
}



function hazScore_buildScoreBarOfProfile(){
    global $lang, $mybb;
    global $hazScore_bar;

    $lang->load('hazScore');

    $score = hazScore_getScoreByUid($mybb->input['uid']);
    $bar = "<tr><td class=\"trow1\"><strong>{$lang->hazScore_score_title}</strong></td>
                <td class=\"trow1\"><strong>{$score}</strong></td></tr>";

    eval("\$hazScore_bar = \"".preg_replace("/\"/", "\\\"", $bar)."\";");
}
function hazScore_buildScoreBarOfUsercp(){
    global $lang, $mybb;
    global $hazScore_bar;

    $lang->load('hazScore');

    $score = hazScore_getScoreByUid($mybb->user['uid']);
    $bar = "<strong>{$lang->hazScore_score_title}</strong>
            <strong>{$score}</strong><br/>";

    eval("\$hazScore_bar = \"".preg_replace("/\"/", "\\\"", $bar)."\";");
}
function hazScore_buildScoreBarOfPost(&$post){
    global $lang, $mybb;
    global $hazScore_bar;

    $lang->load('hazScore');

    $score = hazScore_getScoreByUid($post['uid']);
    $bar = "<br/>{$lang->hazScore_score_title} <strong>{$score}</strong>";

    $bar = preg_replace("/\"/", "\\\"", $bar);
    $post['user_details'] = preg_replace('/(<!-- hazScore -->)(.*)(<!-- \/hazScore -->)/', "$1{$bar}$3", $post['user_details']);

    return $post;
}

function hazScore_getScoreByUid($uid){
    global $db, $settings;

    // Set $available_forum_query
    if($settings['hazScore_countForum']=="all" || $settings['hazScore_countForum']==-1)
        $available_forum_query = "";
    else if($settings['hazScore_countForum']=="")
        $available_forum_query = "AND fid=-1";
    else
        $available_forum_query = "AND fid IN ({$settings['hazScore_countForum']})";
    
    $score = 0;
    // Add Posts Args
    $query = $db->simple_select("posts", "COUNT(*) as rows", "uid={$uid} {$available_forum_query}");
    $post_count  = $db->fetch_field($query, 'rows');
    $score += $post_count * $settings['hazScore_post'];
    // Add Threads Args
    $query = $db->simple_select("threads", "COUNT(*) as rows", "uid={$uid} {$available_forum_query}");
    $post_count  = $db->fetch_field($query, 'rows');
    $score += $post_count * $settings['hazScore_thread'];
    // Add Reputations Args
    $query = $db->simple_select("reputation", "reputation", "uid={$uid}");
    while( ($member = $db->fetch_array($query)) != NULL){
        $score += $settings['hazScore_reputation'] * (($member['reputation']==NULL)? 0: $member['reputation']);
    }
    
    // Add Scores
    $query = $db->simple_select("haz_score", "score", "uid={$uid}");
    while( ($member = $db->fetch_array($query)) != NULL){
        $score += $member['score'];
    }

    return floor($score);
}

function debug($data){
    echo "<textarea>";
    if(is_array ( $data )){
        foreach ($data as $key => $value)
            echo "{$key}=>{$value},\n";
    }
    else    echo "{$data}\n";
    echo "</textarea>";
}



function hazScore_getPluginSettingArray($group_id){
    $dorder = 1;

    $setting[] = array(
        'name' => 'hazScore_post',
        'title' => 'Value of Post',
        'description' => '1 post = ? score',
        'optionscode' => 'numeric',
        'value' => 0.1,
        'disporder' => $dorder++,
        'gid'       => $group_id
    );
    $setting[] = array(
        'name' => 'hazScore_thread',
        'title' => 'Value of Thread',
        'description' => '1 thread = ? score',
        'optionscode' => 'numeric',
        'value' => 1,
        'disporder' => $dorder++,
        'gid'       => $group_id
    );
    $setting[] = array(
        'name' => 'hazScore_reputation',
        'title' => 'Value of Reputation',
        'description' => '1 reputation = ? score',
        'optionscode' => 'numeric',
        'value' => 1,
        'disporder' => $dorder++,
        'gid'       => $group_id
    );
    $setting[] = array(
        'name' => 'hazScore_countForum',
        'title' => 'Available Forum',
        'description' => 'Which forum thread/post will be count as score',
        'optionscode' => 'forumselect',
        'value' => '-1',
        'disporder' => $dorder++,
        'gid'       => $group_id
    );

    return $setting;
}

?>
