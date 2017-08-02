<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'contact.php');

$templatelist = "contact,post_captcha,post_captcha_recaptcha,post_captcha_nocaptcha";

require_once "./global.php";
require_once MYBB_ROOT.'inc/class_captcha.php';
require_once MYBB_ROOT.'inc/functions_modcp.php';

// Load global language phrases
$lang->load("contact");


// Make navigation
add_breadcrumb($lang->contact, "contact.php");

if($mybb->settings['contact'] != 1 || (!$mybb->user['uid'] && $mybb->settings['contact_guests'] == 1))
{
	error_no_permission();
}

$errors = array();

$mybb->input['message'] = trim_blank_chrs($mybb->get_input('message'));
$mybb->input['subject'] = trim_blank_chrs($mybb->get_input('subject'));

if($mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	
	// Validate input
	if(empty($mybb->input['message']))
	{
		$errors[] = $lang->contact_no_message;
	}

	if(strlen($mybb->input['message']) > $mybb->settings['contact_maxmessagelength'] && $mybb->settings['contact_maxmessagelength'] > 0)
	{
		$errors[] = $lang->sprintf($lang->message_too_long, $mybb->settings['contact_maxmessagelength'], strlen($mybb->input['message']));
	}

	if(strlen($mybb->input['message']) < $mybb->settings['contact_minmessagelength'] && $mybb->settings['contact_minmessagelength'] > 0)
	{
		$errors[] = $lang->sprintf($lang->message_too_short, $mybb->settings['contact_minmessagelength'], strlen($mybb->input['message']));
	}

	if(!$mybb->user['uid'] && $mybb->settings['stopforumspam_on_contact'])
	{
		require_once MYBB_ROOT . '/inc/class_stopforumspamchecker.php';

		$stop_forum_spam_checker = new StopForumSpamChecker(
			$plugins,
			$mybb->settings['stopforumspam_min_weighting_before_spam'],
			$mybb->settings['stopforumspam_check_usernames'],
			$mybb->settings['stopforumspam_check_emails'],
			$mybb->settings['stopforumspam_check_ips'],
			$mybb->settings['stopforumspam_log_blocks']
		);

		try {
			if($stop_forum_spam_checker->is_user_a_spammer('', $mybb->input['email'], get_ip()))
			{
				$errors[] = $lang->sprintf($lang->error_stop_forum_spam_spammer,
					$stop_forum_spam_checker->getErrorText(array(
						'stopforumspam_check_emails',
						'stopforumspam_check_ips')));
			}
		}
		catch (Exception $e)
		{
			if($mybb->settings['stopforumspam_block_on_error'])
			{
				$errors[] = $lang->error_stop_forum_spam_fetching;
			}
		}
	}

	if(empty($errors))
	{
		if($mybb->settings['contact_badwords'] == 1)
		{
			// Load the post parser
			require_once MYBB_ROOT."inc/class_parser.php";
			$parser = new postParser;

			$mybb->input['message'] = $parser->parse_badwords($mybb->input['message']);
		}

		$user = $lang->na;
		if($mybb->user['uid'])
		{
			$user = htmlspecialchars_uni($mybb->user['username']).' - '.$mybb->settings['bburl'].'/'.get_profile_link($mybb->user['uid']);
		}

		$message = $mybb->input['message'];

		// Email the administrator
		// Haz mod - start do report
		$new_report = array(
			'id' => $id,
			'id2' => $id2,
			'id3' => $id3,
			'uid' => $mybb->user['uid']
		);
		$new_report['reasonid']= 1;
		$new_report['reason']  = $message;
		add_report($new_report, 'generic');
		// Haz mod - end do report

		redirect('index.php', $lang->contact_success_message, '', true);
	}
	else
	{
		$errors = inline_error($errors);
	}
}

if(empty($errors))
{
	$errors = '';
}


$mybb->input['message'] = htmlspecialchars_uni($mybb->input['message']);

eval("\$page = \"".$templates->get("custom_contact")."\";");
output_page($page);
