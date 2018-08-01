<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_hazDiscordWebhook_threadnotice($task)
{
	global $settings, $db;
	
	$webhook_url = $settings['hazDiscordWebhookTask_webhook_url'];
	$title = $settings['hazDiscordWebhookTask_title'];


	// Get Prefix_id
	$prefix_value = $settings['hazDiscordWebhookTask_detectThreadPrefix'];
	$query = $db->simple_select("threadprefixes", "pid", "prefix = '{$prefix_value}'");
	if($query==NULL) return ;

	$prefix_id = $db->fetch_field($query, "pid");
	if($prefix_id==0) return ;

	$embed_array = [];
	$total_count = 0;
	$count = 0;
	$query = $db->simple_select("threads", "fid, tid, subject", "prefix = '{$prefix_id}' AND visible=1 ORDER BY lastpost ASC");
	while( ($thread = $db->fetch_array($query)) != NULL){
		$fid = $thread["fid"];
		$forum_name = $db->fetch_field($db->simple_select("forums", "name", "fid={$fid}"), "name");
		$thread_url = $settings['bburl'] . '/' . get_thread_link($thread['tid']);
        $embed_array[] = array(
        		"title" => $forum_name.$thread["subject"],
        		"url" => $thread_url
        		);

        $total_count += 1;
        $count += 1;
        if($count>=10){
        	sendtodiscordwebhook($webhook_url, $title, $embed_array);
        	$count = 0;
        	$embed_array = [];
        	$title = "";
        }
    }
	if(sizeof($embed_array)>0)
		sendtodiscordwebhook($webhook_url, $title, $embed_array);

	add_task_log($task, "Success notice: {$total_count}");
}

function sendtodiscordwebhook($webhook_url, $text, $embeds){
	
	$data = array(
        'content' => $text,
        'name' => "",
        'avatar_url' => "",
        'embeds' => $embeds,
    );
	$data_string = json_encode($data);

	$curl = curl_init();

	curl_setopt($curl, CURLOPT_URL, $webhook_url);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

	$output = curl_exec($curl);
	//$output = json_decode($output, true);

	curl_close($curl);
	return $output;
}