<?php
/**
 * Haz Phide
 * Copyright 2017 Hazmole, All Rights Reserved
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

/**
 * Installation Guild:
 * 		- put "hazGeneralLib.php" into "./inc/plugins/"
 *		- put "hazmole/"		under "./jscripts/"
 *		- Active "hazGeneralLib" in the admin panel.
 */

function hazGeneralLib_info()
{
    return array(
        "name"          => "Haz General Library",
        "description"   => "preset .js file into header: holdMouseEvent",
        "website"       => "",
        "author"        => "Hazmole",
        "authorsite"    => "",
        "version"       => "1.0",
        "guid"          => "",
        "codename"      => str_replace('.php', '', basename(__FILE__)),
        "compatibility" => "*"
    );
}

function hazGeneralLib_activate() {
    global $db;

    $libs[] = "./jscripts/hazmole/holdMouseEvent.js";

    // construct
    $libs_str = "";
    foreach ($libs as $path) {
    	$libs_str .= '<script type="text/javascript" src='.$path."></script>";
    }
    

    require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets('headerinclude',           '#<\/script>$#', '</script><!-- hazGLib -->'.$libs_str.'<!-- /hazGLib -->');
}
// deactivate plugin
function hazGeneralLib_deactivate() {
	global $db;

    require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets('headerinclude',           '#\<!--\shazGLib\s--\>(.+)\<!--\s/hazGLib\s--\>#is', '', 0);
}



?>
