function haz_rollingDices() {
	var text   = $("#haz_dices_dice").val();
	var reason = $("#haz_dices_reason").val();
	var match= text.match(/^([s]?)(\d+)[dD](\d+)(([+-](\d+))*)/);
	if(!match){
    alert("Wrong Format!");
 	  return;
  }
	//console.log("match: "+match);
	
	var special_char=match[1];
	var dice_num =   match[2];
	var dice_size=   match[3];
	var dice_offset= match[4];
	var result = 0;
	var results = [];
	var offset_array = [];
	if(dice_num<0 || dice_size<1) return;
	if(dice_num>40)		dice_num =40;
	if(dice_size>100)	dice_size=100;
	
	for(var i=0;i<dice_num;i++){
		results.push(Math.floor((Math.random()*dice_size))+1);
	}
	
	
	$.each(results, function(isx, value){
		result += parseInt(value);
	})
	if((offset_array=dice_offset.match(/[+-]\d+/g))){
		$.each(offset_array, function(isx, value){
			result += parseInt(value);
		})
	}
	
	
	haz_rd_updateByDb(match[0], results, dice_offset, result, reason);
}

function haz_rd_updateByDb(claim, results, dice_offset, sum, reason) {
	var data = {
		action: "hz_rolldice",
		haz_dices_claim:	claim,
		haz_dices_result:	"["+results+"]"+dice_offset,
		haz_dices_sum:		sum,
		haz_dices_reason:	reason
	};
	var output = haz_rd_tableRender(claim, results, dice_offset, sum, reason);
	
	var success = function(data){
		console.log(data);
		haz_rd_updateByJquery(output);
	};
	var error = function(xhr, status, err) {
		console.error(status, err.toString());
	};
	$.ajax({
		url: "misc.php",
		method: "POST",
		data: data,
		success: success,
		error: error,
	});	
}

function haz_rd_updateByJquery(output) {
	var text = $("#haz_dices_result").text();
	if(text=="--"){
		$("#haz_dices_result").html(output);
	}
	else{
		var html = $("#haz_dices_result").html();
		$("#haz_dices_result").html(html+output);
	}
}

function haz_rd_tableRender(recipe, results, offset, result, reason){
	reason = reason.replace(/</g, '&lt;');
	reason = reason.replace(/>/g, '&gt;');
	
	var TD = '';
	TD += '<td>: <b>'+recipe+'</b> => </td>';
	TD += '<td>['+results+']'+offset+' = </td>';
	TD += '<td><b style=\'color:blue;\'>'+result+'</b></td>';
	TD += '<td> '+reason+'</td>';
	return '<tr>'+TD+'</tr>';
}
