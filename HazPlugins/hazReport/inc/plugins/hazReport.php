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

define('HZRPT_PLUGIN_VER', '1.0');
/**
 * Installation Guild:
 * 		- put "hazReport.php" into "./inc/plugins/"
 *      - put "custom_report.php" into "./"
 *		- Active "hazReport" in the admin panel.
 */

function hazReport_info()
{
    return array(
        "name"          => "Haz Report",
        "description"   => "Build Report Panel on index page",
        "website"       => "",
        "author"        => "Hazmole",
        "authorsite"    => "",
        "version"       => HZRPT_PLUGIN_VER,
        "guid"          => "",
        "codename"      => str_replace('.php', '', basename(__FILE__)),
        "compatibility" => "*"
    );
}


// activate plugin
function hazReport_activate() {
    global $db;
    // Add Global Templates
    $my_template = hazReport_getMyTemplateContent();
    foreach ($my_template as $title => $template) {
        $new_global_template = array(
            'title' => $db->escape_string($title), 
            'template' => $db->escape_string($template), 
            'sid' => '-1', 
            'version' => HZRPT_PLUGIN_VER, 
            'dateline' => TIME_NOW);
        $db->insert_query("templates", $new_global_template);
    }
}
// deactivate plugin
function hazReport_deactivate() {
    global $db;
    // Remove Global Templates
    $my_template = hazReport_getMyTemplateContent();
    foreach ($my_template as $title => $template) {
        $db->delete_query("templates", "`title` = '".$db->escape_string($title)."'");
    }
}


function hazReport_getMyTemplateContent(){
    $my_template['custom_contact'] = 
"<html>
<head>
<title>{\$mybb->settings['bbname']} - {\$lang->contact}</title>
{\$headerinclude}
</head>
<body>
{\$header}
<form action=\"custom_report.php\" method=\"post\">
    <input type=\"hidden\" name=\"my_post_key\" value=\"{\$mybb->post_code}\" />
    {$errors}
    <table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">
        <tr>
        <td class=\"thead\" colspan=\"2\"><strong>{\$lang->contact}</strong></td>
        </tr>
        <tr>
        <td class=\"trow2\" valign=\"top\"><strong>{\$lang->contact_message}:</strong><br /><span class=\"smalltext\">{\$lang->contact_message_desc}</span></td>
        <td class=\"trow2\"><textarea cols=\"50\" rows=\"10\" name=\"message\" class=\"textarea\" >{\$mybb->input['message']}</textarea></td>
        </tr>
        {\$captcha}
    </table>
    <br />
    <div align=\"center\">
        <input type=\"submit\" class=\"button\" name=\"submit\" value=\"{\$lang->contact_send}\" />
    </div>
</form>
{\$footer}
</body>
</html>";

    return $my_template;
}

?>
