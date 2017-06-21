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

/**
 * Installation Guild:
 * 		- put "hazHider.php" into "./inc/plugins/"
 *		- Active "hazHider" in the admin panel.
 */

function hazHider_info()
{
    return array(
        "name"          => "Haz Hider",
        "description"   => "Parse [phide] and [spoiler] tag in message",
        "website"       => "",
        "author"        => "Hazmole",
        "authorsite"    => "",
        "version"       => "1.0",
        "guid"          => "",
        "codename"      => str_replace('.php', '', basename(__FILE__)),
        "compatibility" => "*"
    );
}

$plugins->add_hook("parse_message", "hazhider_run");

function hazhider_run($message) {
	// Set pattern & replacing string
	$pattern = array();
	array_push($pattern, "#\[phide=(?:&quot;|\"|')?([^\]]*?)(?:&quot;|\"|')?\](.*?)\[\/phide\](\r\n?|\n?)#si",
						 "#\[phide\](.*?)\[\/phide\](\r\n?|\n?)#si");
	array_push($pattern, "#\[spoiler=(?:&quot;|\"|')?([^\]]*?)(?:&quot;|\"|')?\](.*?)\[\/spoiler\](\r\n?|\n?)#si",
						 "#\[spoiler\](.*?)\[\/spoiler\](\r\n?|\n?)#si");

	$replace = array();
	array_push($replace, hazhider_getPhideWrap( "$1",   "$2"), 
						 hazhider_getPhideWrap( "Hide", "$1"));
	array_push($replace, hazhider_getSpoilerWrap( "$1",   "$2"), 
						 hazhider_getSpoilerWrap( "Hide", "$1"));

	// Replace
	while(preg_match($pattern[0], $message) or preg_match($pattern[1], $message)
		 or preg_match($pattern[2], $message) or preg_match($pattern[3], $message)) {
		$message = preg_replace($pattern, $replace, $message);
	}

	// Ignore NewLine
	$find = array(
		"#<div class=\"phide_body\">(\r\n?|\n?)#",
		"#<div class=\"spoiler_body\">(\r\n?|\n?)#",
		"#(\r\n?|\n?)</div>#"
	);
	$replace = array(
		"<div class=\"phide_body\">",
		"<div class=\"spoiler_body\">",
		"</div>"
	);
	$message = preg_replace($find, $replace, $message);
	return $message;
}


function hazhider_getPhideWrap($title, $content) {
	$head = "<div class=\"phide_header\">".hazhider_getPhideButton($title)."</div>";
	$body = "<div class=\"phide_body\" style=\"display: none; border: 1px solid; padding:5px; width:90%;\">".$content."</div>";

	return "<div class=\"phide_wrap\">".$head.$body."</div>";
}
function hazhider_getSpoilerWrap($title, $content) {
	$head = "<div class=\"spoiler_header\">".hazhider_getSpoilerButton($title)."</div>";
	$body = "<div class=\"spoiler_body\" style=\"display: none; border: 1px #B99F9F solid; padding:5px; width:90%;\">".$content."</div>";

	return "<div class=\"spoiler_wrap\">".$head.$body."</div>";
};

function hazhider_getPhideButton($title) {
	$script = "$(this).parent().parent('.phide_wrap').children('.phide_body').toggle(100);";
	return "<button type=\"button\" onClick=\"".$script."\">".$title."</button>";
}
function hazhider_getSpoilerButton($title) {
	$script = "$(this).parent().parent('.spoiler_wrap').children('.spoiler_body').toggle(100);";
	return "<a href=\"javascript:void(0);\" onClick=\"".$script."\">".$title."</a>";
};

?>
