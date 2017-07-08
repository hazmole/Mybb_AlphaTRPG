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

function hazRollDice_update_info()
{
    return array(
        "name"          => "Haz Roll Dices (Update app)",
        "description"   => "use to update HazRollDice from 2.15 to 2.66",
        "website"       => "",
        "author"        => "Hazmole",
        "authorsite"    => "",
        "version"       => 1.0,
        "guid"          => "",
        "codename"      => str_replace('.php', '', basename(__FILE__)),
        "compatibility" => "*"
    );
}

// Install plugin
function hazRollDice_update_install() {
	global $db;
	$db->write_query("UPDATE mybb_haz_rolldice SET result=concat(result, concat(\" → \", sum))");
	$db->write_query("ALTER TABLE mybb_haz_rolldice DROP sum");
	$db->write_query("ALTER TABLE mybb_haz_rolldice ADD rid int(10)");
}
// Uninstall plugin
function hazRollDice_update_uninstall() {
	;
}
function hazRollDice_update_is_installed() {
	global $db;

	$query = $db->write_query("SELECT COUNT(*) as rows FROM information_schema.COLUMNS WHERE TABLE_NAME = 'mybb_haz_rolldice' AND COLUMN_NAME = 'rid'");
	$rows  = $db->fetch_field($query, 'rows');

	return ($rows > 0);
}

?>