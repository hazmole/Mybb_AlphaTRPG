
var hazdsb_ws;
function hazDiscordShoutbox_connect(){
	// Check Pre-Setting
	if( !isWebSoketWork() ){	console.error("Browser Does not support websocket."); return ;}
	if( hazdsb_server==null ){	console.error("Server url is not set by Admin."); return ;}

	// Create websocket
	hazdsb_ws = new WebSocket(hazdsb_server);
	hazdsb_ws.onmessage = function (evt) { 
       var received_msg = evt.data;
       hazDiscordShoutbox_parseMessages(received_msg);
    };

    if(document.getElementById("hazdsb_shout"))
    document.getElementById("hazdsb_shout").addEventListener("keyup", function(event) {
		event.preventDefault();
		if (event.keyCode == 13) hazDiscordShoutbox_say();
	});

}

function hazDiscordShoutbox_parseMessages(data){
	var json_data = JSON.parse(data);
	var output_text = "";
	if(Array.isArray(json_data)){
		for(var i=0;i<json_data.length;i++){
			output_text += hazDiscordShoutbox_parseMessage(json_data[i]);
		}
	}
	else{
		output_text += hazDiscordShoutbox_parseMessage(json_data);
	}
	hazDiscordShoutbox_output(output_text);
	twemoji.parse(document.getElementById("hazdsb_shoutarea"));
}
function hazDiscordShoutbox_parseMessage(message){
	var str = "";
	var avatar = (message.avatar==null)? "https://discordapp.com/assets/6debd47ed13483642cf09e832ed0bc1b.png": message.avatar;
	var content = message.content;
	// new line
	content = content.replace(/\n/g, "<br/>");
	// smilies
	content = hazDiscordShoutbox_parseSmilies(content);
	// auto link
	var match_arr = content.match(/(?![^<]*>|[^<>]*<\/)((https?:)\/\/[a-z0-9&#=.\/\-?_%]+)/gi);
	content = content.replace(/(?![^<]*>|[^<>]*<\/)((https?:)\/\/[a-z0-9&#=.\/\-?_%]+)/gi, "<a href=\"$1\" target=\"_blank\">$1</a>");
	// append attachments
	if(message.attachment!=null)
       for(var i=0;i<message.attachment.length;i++){
          var url  = message.attachment[i];
          var link = (isImage(url))? ("<img src='"+url+"' />"): ("附件");
          content += "<br/><a href='"+url+"' target='_blank'>" + link + "</a>";
       }
    // append image preview
    if(match_arr!=null)
	    for(var i=0;i<match_arr.length;i++){
	    	if(isImage(match_arr[i]))	content += "<br/><a href='"+match_arr[i]+"' target='_blank'><img src='"+match_arr[i]+"' /></a>";
	    }
	// other format
	str += "<span><a href='javascript:hazDiscordShoutbox_mention(\""+message.user_id+"\")'><img class='avatar' src='"+avatar+"'/></a></span>";
	str += "<b class='user'>" + message.username+" </b>：<span> "+content+" </span>";
	return "<div class='haz_message'>" + str + "</div>";
}
function hazDiscordShoutbox_output(text){
	var objDiv = document.getElementById("hazdsb_shoutarea");
	var need_update = false;
	//Jump
	if(objDiv.scrollHeight - objDiv.scrollTop - objDiv.clientHeight < 200) need_update = true;
	objDiv.innerHTML += text;
	if(need_update) objDiv.scrollTop = objDiv.scrollHeight;
}


function hazDiscordShoutbox_mention(id){
	var content_box = document.getElementById("hazdsb_shout");
	if(content_box!=null)	content_box.value += "<@"+id+"> ";
}
function hazDiscordShoutbox_say(){
	var content = document.getElementById("hazdsb_shout").value;
	if(content=="") return;

	if(hazdsb_user=="" || hazdsb_user==null) hazdsb_user="遊客";
	var msg = {"type":"custom_message","username":hazdsb_user, "content":content};

	hazdsb_ws.send(JSON.stringify(msg));
	document.getElementById("hazdsb_shout").value = "";
}

function hazDiscordShoutbox_buildOnline(){
	if( hazdsb_widget==null ){  console.error("Widget url is not set by Admin."); return ;}

	$.ajax({ url: hazdsb_widget}).done(function(data) {
		if(document.getElementById("hazdsb_invite_btn"))
			document.getElementById("hazdsb_invite_btn").href = data.instant_invite;

		var member_list = "";
		for(var i=0;i<data.members.length;i++){
			member_list += hazDiscordShoutbox_buildMember(data.members[i]);
		}
		document.getElementById("hazdsb_online").innerHTML = member_list;
	});
}
function hazDiscordShoutbox_buildMember(member){
	var str = "";
	str += "<span style=\"padding-right:5px;\"><img src=\""+member.avatar_url+"\" style=\"height:20px;width:20px;border-radius:5px;\"></span>";
	str += member.username;

	return "<div class=\"hazdsb_member\">"+str+"</div>";
}

function hazDiscordShoutbox_parseSmilies(content){
	for(var i=0;i<hazdsb_smilies_json.length;i++){
		var smilie = hazdsb_smilies_json[i];
		content = content.replace(smilie.find, ("<img src=\""+smilie.image+"\">"));
	}
	return content; 
}



function isWebSoketWork(){	return ("WebSocket" in window); }
function isImage(url){		return (null!=url.match(/\.((png)|(gif)|(jpg)|(bmp))$/i));}