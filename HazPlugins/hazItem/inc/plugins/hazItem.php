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

define('HZIT_PLUGIN_VER', '1.1');
/**
 * Require Plugin: hazGeneralLib
 *
 * Installation Guild:
 * 		- put "hazItem.php" into "./inc/plugins/"
 *		- put "languages/" 		under "./inc/"
 *		- Active "hazItem" in the admin panel.
 */

function hazItem_info()
{
    return array(
        "name"          => "Haz Item",
        "description"   => "Let user can have their own item",
        "website"       => "",
        "author"        => "Hazmole",
        "authorsite"    => "",
        "version"       => HZIT_PLUGIN_VER,
        "guid"          => "",
        "codename"      => str_replace('.php', '', basename(__FILE__)),
        "compatibility" => "*"
    );
}

// Hook
$plugins->add_hook("modcp_nav", "hazItem_buildItemNav");
$plugins->add_hook("modcp_start", "hazItem_buildEditItemListPage");
$plugins->add_hook("modcp_start", "hazItem_buildEditItemsPage");
$plugins->add_hook("modcp_start", "hazItem_EditItemList");
$plugins->add_hook("modcp_start", "hazItem_EditItems");
$plugins->add_hook("member_profile_start", "hazItem_buildMemberItemList");
$plugins->add_hook("xmlhttp", "hazItem_xmlSarchForItem");

// activate plugin
function hazItem_install() {
    global $db;
    // Add ItemList to Database
    $db->write_query("CREATE TABLE mybb_haz_itemList( 
        id int(10), title VARCHAR(20), image VARCHAR(100), description VARCHAR(100))");
    // Add Owner_ItemList to Database
    $db->write_query("CREATE TABLE mybb_haz_items( 
        uid int(10), id int(10), date bigint(30))");

}
function hazItem_uninstall() {
    global $db;
    // Remove Score from Database
    $db->write_query("DROP TABLE mybb_haz_itemList");
    $db->write_query("DROP TABLE mybb_haz_items");
}
function hazItem_is_installed() {
    global $db;
    if( $db->table_exists("haz_itemList") ) return true;
    else    return false;
}

// activate plugin
function hazItem_activate() {
    global $db;
	// Add Global Templates
    $my_template = hazItem_getMyTemplateContent();
    foreach ($my_template as $row) {
        $db->insert_query("templates", $row);
    }

    require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets('modcp_nav',           '#{\$modcp_nav_users}#', '{\$modcp_nav_users}<!-- hazItem -->{\$hazItem_modcp_nav}<!-- /hazItem -->');
    find_replace_templatesets('member_profile',      '#{\$bannedbit}#', '{\$bannedbit}<!-- hazItem -->{\$hazItem_member_itemlist}<!-- /hazItem -->');
}
// deactivate plugin
function hazItem_deactivate() {
	global $db;
    // Remove Global Templates
    $my_template = hazItem_getMyTemplateContent();
    foreach ($my_template as $row) {
        $db->delete_query("templates", "`title` = '".$row["title"]."'");
    }

    require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets('modcp_nav',           '#\<!--\shazItem\s--\>(.+)\<!--\s/hazItem\s--\>#is', '', 0);
    find_replace_templatesets('member_profile',      '#\<!--\shazItem\s--\>(.+)\<!--\s/hazItem\s--\>#is', '', 0);
}



function hazItem_xmlSarchForItem(){
    global $db, $mybb, $charset;

    if($mybb->input['action'] == "get_items"){
        $mybb->input['query'] = ltrim($mybb->get_input('query'));

        // If the string is less than 2 characters, quit.
        if(my_strlen($mybb->input['query']) < 2){ exit;}

        if($mybb->get_input('getone', MyBB::INPUT_INT) == 1){
            $limit = 1;
        }
        else{
            $limit = 15;
        }
        // Send our headers.
        header("Content-type: application/json; charset={$charset}");
        // Query for any matching users.
        $query_options = array(
            "order_by" => "title",
            "order_dir" => "asc",
            "limit_start" => 0,
            "limit" => $limit
        );
        $query = $db->simple_select("haz_itemList", "id, title", "title LIKE '".$db->escape_string_like($mybb->input['query'])."%'", $query_options);

        if($limit == 1){
            $item = $db->fetch_array($query);
            $data = array('id' => $item['title'], 'text' => $item['title']);
        }
        else{
            $data = array();
            while($item = $db->fetch_array($query)){
                $data[] = array('id' => $item['title'], 'text' => $item['title']);
            }
        }

        echo json_encode($data);
        exit;
    }
}


function hazItem_EditItems(){
    global $db, $mybb, $lang;

    if($mybb->input['action'] == "do_editItems"){
        if($mybb->get_input("uid")==-1){
            // Add new Item to somebody
            if($mybb->get_input("itemname")=="") error("You need to enter the item's name");
            if($mybb->get_input("username")=="") error("You need to enter the users' name");

            // Get Item ID
            $iid = hazItem_getItemIdByName($mybb->get_input("itemname"));

            // Get User ID
            $usernames = explode(",", $mybb->get_input("username"));
            foreach ($usernames as $k => $name) {
                $query = $db->simple_select("users", "uid", "username='{$name}'");
                $uid = $db->fetch_field($query, 'uid');

                // Check
                $query = $db->simple_select("haz_items", "COUNT(*) as rows", "uid='{$uid}' AND id='{$iid}'");
                if($db->fetch_field($query, 'rows')==0){
                    // Insert
                    $new_item = array(
                        "uid"    => $uid,
                        "id"     => $iid,
                        "date"   => TIME_NOW
                    );
                    $db->insert_query('haz_items', $new_item);
                }
            }
        }
        else{
            // Delete
            $uid = $mybb->get_input("uid");
            $iid = $mybb->get_input("iid");
            $db->delete_query("haz_items", "uid='{$uid}' AND id='{$iid}'");
        }

        redirect("modcp.php?action=editItems", "Edited Items and their ownerships...");
    }
}

function hazItem_EditItemList(){
    global $db, $mybb, $lang;

    if($mybb->input['action'] == "do_editItemList"){
        if($mybb->get_input("title")=="") error("You need to enter the Title of the item");


        if($mybb->get_input("id")==-1){
            $query = $db->simple_select("haz_itemList", "id", "1 ORDER BY id DESC");
            $id = $db->fetch_field($query, 'id');
            $id = ($id==NULL)? 0: $id+1;
        }
        else{
            $id = $mybb->get_input("id");
        }
        $new_item = array(
            "id"            => $id,
            "title"         => $db->escape_string_like($mybb->get_input("title")),
            "image"         => $db->escape_string($mybb->get_input("image")),
            "description"   => $db->escape_string($mybb->get_input("desc"))
            );

        if($mybb->get_input("id")==-1)
            $db->insert_query('haz_itemList', $new_item);
        else
            $db->update_query('haz_itemList', $new_item, ('id='.$id));

        redirect("modcp.php?action=editItemList", "Edited one Item in the list...");
    }
}


// Build-Related Functions
function hazItem_buildMemberItemList(){
    global $mybb, $db, $lang;
    global $hazItem_member_itemlist;

    $lang->load('hazItem');

    $uid = $mybb->input['uid'];

    // build Item lists
    $hzitem_items_content = "";
    $query = $db->simple_select("haz_items", "id", "uid={$uid} ORDER BY id ASC");
    while( ($id = $db->fetch_field($query, "id")) != NULL){
        $iids[] = $id;
    }
    if(count($iids)==0) $hzitem_items_content = $lang->hazItem_dont_have_item;
    else{
        $query = $db->simple_select("haz_itemList", "*", "id IN (".implode(",", $iids).")");
        while( ($item = $db->fetch_array($query)) != NULL){
            $name = $item["title"];
            $url  = $item["image"];
            $desc = $item["description"];
            $hzitem_items_content .= 
            "<td style='text-align:center;'>
                <a href='javascript:void(0)'><img src='{$url}' title='{$desc}' alt='{$name}' style='height:100px;' onclick='pop_item_window(this)'></a><br/><b>{$name}</b>
            </td>";
        }
        $hzitem_items_content = hazItem_getMemberItemList_scrollableContent($hzitem_items_content);
    }
    
    $itemlistframe = hazItem_getMemberItemListFrame($hzitem_items_content);
    eval("\$hazItem_member_itemlist .= \"".$itemlistframe."\";");
}
function hazItem_getMemberItemListFrame($content){
    global $lang;
    $lang->load('hazItem');
    return 
"<table border='0' cellspacing='0' cellpadding='5' class='tborder'>
    <tr><td class='thead'>".$lang->hazItem_memberItemlist_title."</td></tr>
    <tr><td height='120px'>
        <center><div style='position:relative;'>".$content."</div></center>
    </td></tr>
</table><br/>";
}
function hazItem_getMemberItemList_scrollableContent($content){
    return 
"<script>
function move(v){
    var obj=document.getElementById('haz_list_obj');
    var frm=document.getElementById('haz_list_frame');
    var l='0';
    if(obj.style.left!='') l=((obj.style.left).match(/(-?\d+)px/))[1];
    l=parseInt(l);
    if(frm.offsetWidth-obj.offsetWidth>60) return;
    if(v>0 && (l+v)>30) return;
    if(v<0 && (l+v)<frm.offsetWidth-obj.offsetWidth-30) return;
    obj.style.left = (l+v)+'px';}
function pop_item_window(obj){
    $('#item_pop_window_close').show();
    $('#item_window_title').html(obj.alt);
    $('#item_window_image').attr('src',obj.src);
    $('#item_window_desc' ).html(obj.title);
    $('#item_pop_window' ).show(500);}
function close_item_window(obj){
    $('#item_pop_window_close').hide();
    $('#item_pop_window').hide();
}
</script>
<div id='haz_list_frame' style='position:relative;overflow-x:hidden;' >
    <table id='haz_list_obj' style='position:relative;'><tr>".$content."</tr></table>
</div>
<button style='position:absolute;width:30px;height:100%;background:#b5b5b5;top:0px; left:0px;' onmousedown='mholdstart(move.bind(this, 5))' onmouseup='mholdend()'>&lt;</button>
<button style='position:absolute;width:30px;height:100%;background:#b5b5b5;top:0px;right:0px;' onmousedown='mholdstart(move.bind(this,-5))' onmouseup='mholdend()'>&gt;</button>
<div id='item_pop_window_close' style='background:black;opacity: 0.75;display:none;position:fixed;top:0px;left:0px;width:100%;height:100%;' onclick='close_item_window()'></div>
<fieldset id='item_pop_window' style='display:none;color:black;position:fixed;border:5px solid #ae7300;width:500px;height:400px;top:50%;left:50%;margin-top:-200px;margin-left:-250px';><center>
    <h1 id='item_window_title'></h1><p>
    <div style='height:200px;'><img id='item_window_image' src='' style='max-width:400px;max-height:200px;border:1px black solid;'></div><p>
    <div id='item_window_desc' style='background:white;width:450px;height:100px;border:black 1px solid;'></div>
</center></fieldset>
";
}

function hazItem_buildItemNav(){
    global $lang, $db, $mybb , $templates, $theme, $post;
    global $hazItem_modcp_nav;

    eval("\$hazItem_modcp_nav .= \"".$templates->get("hzitem_nav")."\";");
}
function hazItem_buildEditItemListPage(){
    global $mybb, $templates, $lang, $db;
    global $header, $headerinclude, $footer, $modcp_nav;

    if($mybb->input['action'] == "editItemList"){

        $option = ($mybb->input['username']!="")? "uid=": "1";


        $query = $db->simple_select("haz_itemList", "*", "");
        $hzitem_itemlist_content = "";
        while( ($member = $db->fetch_array($query)) != NULL){
            $hzitem_itemlist_content .= hazItem_getItemListTemplate($member['id'], $member['title'], $member['image'], $member['description']);
        }
        if($hzitem_itemlist_content==""){
            $hzitem_itemlist_content = "<tr><td colspan=\"5\"><center>Not found!</center></td></tr>";
        }

        $hzitem_itemlist_content .= hazItem_getItemListTemplate(-1, "", "", "");


        eval("\$hzitem_title = \"Edit ItemList\";");
        eval("\$hzitem_content= \"".$templates->get("hzitem_modcp_itemList_table")."\";");
        eval("\$editItemList = \"".$templates->get("hzitem_modcp_default_frame")."\";");
        output_page($editItemList);
    }
}
function hazItem_getItemListTemplate($id, $name, $url, $desc){
    $id_text = ($id==-1)? "new": "{$id}";
    $style = ($id==-1)? "style=\"background:#b9a77a;\"": "";

    $image = ($id==-1)?
        "<td colspan=\"2\"><input style=\"width:100%;\" type=\"text\" name=\"image\" value=\"{$url}\"></td>":
        "<td><img src=\"{$url}\" style=\"max-width:100px;max-height:100px;\"></td>
         <td><input style=\"width:100%;\" type=\"text\" name=\"image\" value=\"{$url}\"></td>";

    $template = 
        "<form action=\"modcp.php?action=do_editItemList\" method=\"post\">
            <tr {$style}>
                <td><input type=\"text\" name=\"id\"    value=\"{$id}\" hidden>{$id_text}</td>
                <td><input type=\"text\" name=\"title\" value=\"{$name}\"></td>
                {$image}
                <td><input style=\"width:100%;\" type=\"text\" name=\"desc\"  value=\"{$desc}\"></td>
                <td><input type=\"submit\"></td>
            </tr>
        </form>";
    return $template;
}

function hazItem_buildEditItemsPage(){
    global $db, $mybb, $templates, $lang, $header, $headerinclude, $footer, $modcp_nav;

    if($mybb->input['action'] == "editItems"){
        // Stretch From Template
        $js = $templates->get("hzitem_modcp_items_searchjs");
        $search_panel = $templates->get("hzitem_modcp_items_searchpanel");
        $item_result  = $templates->get("hzitem_modcp_items_table");

        // Filter
        $iid  = hazItem_getItemIdByName($mybb->get_input("itemname"));
        $uids = hazItem_getUserIdsByNames($mybb->get_input("username"));
        if($iid==NULL&&$uids==NULL) $options = "1 LIMIT 20";
        $options  = ($iid!=NULL)? "id={$iid} AND ": "1 AND ";
        $options .= ($uids!=NULL)? "uid IN (".implode(",", $uids).")": "1";

        // Append Result Content
        $hzitem_items_content = "";
        $query = $db->simple_select("haz_items", "*", $options);
        while( ($member = $db->fetch_array($query)) != NULL){
            $hzitem_items_content .= hazItem_getItemsTemplate($member['uid'], $member['id'], $member['date']);
        }
        $hzitem_items_content .= hazItem_getItemsTemplate(-1, NULL, NULL);

        // Display
        $content = $search_panel."<br>".$item_result.$js;
        
        eval("\$hzitem_title = \"Edit Items\";");
        eval("\$hzitem_content= \"".$content."\";");
        eval("\$editItem = \"".$templates->get("hzitem_modcp_default_frame")."\";");
        output_page($editItem);
        
    }
}
function hazItem_getItemsTemplate($uid, $iid, $date){
    global $db,$mybb;

    // Set Variables
    $style = ($uid==-1)? "style=\"background:#b9a77a;\"": "";
    $query = $db->simple_select("haz_itemList", "*", "id='{$iid}'");
    $item  = $db->fetch_array($query);
    $itemname = $item['title'];
    $url      = $item['image'];
    $query = $db->simple_select("users", "username", "uid='{$uid}'");
    $username = $db->fetch_field($query, 'username');
    $d = my_date($mybb->settings['dateformat'], $date);

    // Set Mode
    $item = ($uid==-1)?
        "<td colspan=\"2\"><input style=\"width:100%;\" type=\"text\" name=\"itemname\"></td>":
        "<td width=\"25px\"><img src=\"{$url}\" style=\"max-width:20px;max-height:20px;\"></td>
         <td><input name=\"iid\" value=\"{$iid}\" hidden>{$itemname}</td>";
    $user = ($uid==-1)?
        "<td><input name=\"uid\" value=\"{$uid}\" hidden><input style=\"width:100%;\" type=\"text\" name=\"username\"></td>":
        "<td><input name=\"uid\" value=\"{$uid}\" hidden>{$username}</td>";
    $date = ($uid==-1)?
        "<td></td>":
        "<td>{$d}</td>";

    $template = 
        "<form action=\"modcp.php?action=do_editItems\" method=\"post\">
            <tr {$style}>
                {$item}{$user}{$date}
                <td><input type=\"submit\"></td>
            </tr>
        </form>";
    return $template;
}


function hazItem_getItemIdByName($itemname){
    global $db;
    if($itemname=="") return NULL;
    $itemname = $db->escape_string($itemname);
    $query = $db->simple_select("haz_itemList", "id", "title='{$itemname}'");
    $iid = $db->fetch_field($query, 'id');
    return $iid;
}
function hazItem_getUserIdsByNames($usernames){
    global $db;
    if($usernames=="") return NULL;
    $usernames = explode(",", $usernames);
    foreach ($usernames as $k => $name) {
        $query = $db->simple_select("users", "uid", "username='{$name}'");
        $uids[] = $db->fetch_field($query, 'uid');
    }
    return $uids;
}

function hazItem_getMyTemplateContent(){
    $my_template[] = array(
        "title"     => "hzitem_nav",
        "template"  => 
    '<tr>\n'
        .'<td class="tcat tcat_menu tcat_collapse">\n'
            .'<div class="expcolimage"><img src="{$theme[\\\'imgdir\\\']}/collapse{$collapsedimg[\\\'modcpitems\\\']}.png" id="modcpitems_img" class="expander" alt="[-]" title="[-]" /></div>\n'
            .'<div><span class="smalltext"><strong>道具</strong></span></div>\n'
        .'</td>\n'
    .'</tr>\n'
    .'<tbody style="{$collapsed[\\\'modcpusers_e\\\']}" id="modcpitems_e">\n'
        .'<tr><td class="trow1 smalltext"><a href="modcp.php?action=editItemList">編輯道具清單</a></td></tr>\n'
        .'<tr><td class="trow1 smalltext"><a href="modcp.php?action=editItems">收授道具</a></td></tr>\n'
    .'</tbody>\n'
    ,
        "sid"       => -1,
        "version"   => HZIT_PLUGIN_VER,
        "dateline"  => TIME_NOW
    );
    $my_template[] = array(
        "title"     => "hzitem_modcp_default_frame",
        "template"  => 
    '<html><head><title>{$mybb->settings[\\\'bbname\\\']} - {$hzitem_title}</title>{$headerinclude}</head>\n'
    .'<body>{$header}\n'
        .'<table width="100%" border="0" align="center">\n'
            .'<tr>{$modcp_nav}\n'
                .'<td valign="top">\n'
                    .'{$hzitem_content}\n'
                .'</td>\n'
            .'</tr>\n'
        .'</table>\n'
    .'{$footer}</body></html>'
    ,
        "sid"       => -1,
        "version"   => HZIT_PLUGIN_VER,
        "dateline"  => TIME_NOW
    );
    $my_template[] = array(
        "title"     => "hzitem_modcp_itemList_table",
        "template"  => 
    '<table border="1" cellspacing="0" cellpadding="6" class="tborder">\n'
        .'<tbody>\n'
        .'<tr><td class="thead" align="center" colspan="6"><strong>Item List</strong></td></tr>\n'
        .'<tr>\n'
            .'<td class="tcat"><span class="smalltext"><strong>ID</strong></span></td>\n'
            .'<td class="tcat" style="width:200px;"><span class="smalltext"><strong>Name</strong></span></td>\n'
            .'<td class="tcat" style="width:120px;"><span class="smalltext"><strong>Image</strong></span></td>\n'
            .'<td class="tcat"><span class="smalltext"><strong>Url</strong></span></td>\n'
            .'<td class="tcat" colspan="2"><span class="smalltext"><strong>Description</strong></span></td>\n'
        .'</tr>\n'
        .'{$hzitem_itemlist_content}\n'
        .'</tbody>\n'
    .'</table>'
    ,
        "sid"       => -1,
        "version"   => HZIT_PLUGIN_VER,
        "dateline"  => TIME_NOW
    );
    $my_template[] = array(
        "title"     => "hzitem_modcp_items_searchpanel",
        "template"  => 
    '<form action="modcp.php" method="get">\n'
    .'<input type="hidden" name="action" value="editItems">'
    .'<table border="1" cellspacing="0" cellpadding="6" class="tborder">\n'
        .'<tbody>\n'
        .'<tr><td class="thead" align="center" colspan="3"><strong>Search</strong></td></tr>\n'
        .'<tr>\n'
            .'<td><input type="text" name="itemname" class="textbox" style="width:350px;"/></td>\n'
            .'<td><input type="text" name="username" class="textbox" style="width:350px;"/></td>\n'
            .'<td class="tcat" rowspan="2"><input type="submit" /></td>\n'
        .'</tr>\n</tbody>\n'
    .'</table>'
    .'</form>'
    ,
        "sid"       => -1,
        "version"   => HZIT_PLUGIN_VER,
        "dateline"  => TIME_NOW
    );
    $my_template[] = array(
        "title"     => "hzitem_modcp_items_table",
        "template"  => 
    '<input type="hidden" name="action" value="editItems">'
    .'<table border="1" cellspacing="0" cellpadding="6" class="tborder">\n'
        .'<tbody>\n'
        .'<tr><td class="thead" align="center" colspan="6"><strong>Items</strong></td></tr>\n'
        .'<tr>\n'
            .'<td colspan="2" class="tcat"><span class="smalltext"><strong>Item Name</strong></span></td>\n'
            .'<td class="tcat" style="width:50%;"><span class="smalltext"><strong>Owner Name</strong></span></td>\n'
            .'<td class="tcat" style="width:20%;"><span class="smalltext"><strong>Date</strong></span></td>\n'
            .'<td class="tcat" style="width:70px;"><span class="smalltext"><strong>Delete</strong></span></td>\n'
        .'</tr>\n'
        .'{$hzitem_items_content}\n'
        .''
        .'</tbody>\n'
    .'</table>'
    ,
        "sid"       => -1,
        "version"   => HZIT_PLUGIN_VER,
        "dateline"  => TIME_NOW
    );

    $my_template[] = array(
        "title"     => "hzitem_modcp_items_searchjs",
        "template"  => 
    '<link rel="stylesheet" href="{$mybb->asset_url}/jscripts/select2/select2.css?ver=1807">\n'
    .'<script type="text/javascript" src="{$mybb->asset_url}/jscripts/select2/select2.min.js?ver=1806"></script>\n'
    .'<script type="text/javascript"><!--\nif(use_xmlhttprequest == "1"){\n'
    .'MyBB.select2();\n'
    .'$("[name=\\\"username\\\"]").select2({\n'
        .'placeholder: "{$lang->search_user}",\n'
        .'minimumInputLength: 2,\nmultiple: true,\n'
        .'ajax: { \n'
            .'url: "xmlhttp.php?action=get_users",\n'
            .'dataType: "json",\n'
            .'data: function (term, page) {\n'
                .'return {\n'
                .'    query: term, \n'
                .'};\n'
            .'},\n'
            .'results: function (data, page) { \n'
                .'return {results: data};\n'
            .'}\n'
        .'},\n'
        .'initSelection: function(element, callback) {\n'
            .'var value = $(element).val();\n'
            .'if (value !== "") {\n'
                .'callback({\n'
                    .'id: value,\n'
                    .'text: value\n'
                .'});\n'
            .'}\n'
        .'},\n'
    .'});\n'
    .'$("[name=\\\"itemname\\\"]").select2({\n'
        .'placeholder: "Search for Item",\n'
        .'minimumInputLength: 2,\nmultiple: false,\n'
        .'ajax: { \n'
            .'url: "xmlhttp.php?action=get_items",\n'
            .'dataType: "json",\n'
            .'data: function (term, page) {\n'
                .'return {\n'
                .'    query: term, \n'
                .'};\n'
            .'},\n'
            .'results: function (data, page) { \n'
                .'return {results: data};\n'
            .'}\n'
        .'},\n'
        .'initSelection: function(element, callback) {\n'
            .'var value = $(element).val();\n'
            .'if (value !== "") {\n'
                .'callback({\n'
                    .'id: value,\n'
                    .'text: value\n'
                .'});\n'
            .'}\n'
        .'},\n'
    .'});\n'
.'}\/\/ --></script>'
    ,
        "sid"       => -1,
        "version"   => HZIT_PLUGIN_VER,
        "dateline"  => TIME_NOW
    );


    return $my_template;
}

?>
