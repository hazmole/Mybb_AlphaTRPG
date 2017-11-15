function haz_rollingDices() {
	var roll_exp    = $("#haz_dices_dice").val();
	var roll_reason = $("#haz_dices_reason").val();

	if(roll_reason.length>=50){
		alert("Your reason is too long!");
		return -1;
	}

	// Ignore Fullshape, space, ()
	roll_exp = roll_exp.replace(/[()（）]/g, "");
	roll_exp = roll_exp.replace(/＜/g, "<").replace(/＞/g, ">").replace(/＝/g, "=").replace(/＋/g, "+").replace(/－/g, "-");
	roll_exp = roll_exp.replace(/[DｄＤ]/g, "d").replace(/[BｂＢ]/g, "b").replace(/[SｓＳ]/g, "s");
	if(roll_exp.length>=20){
		alert("Expression is too long!");
		return -1;
	}

	//===========================
	// Check the rolling format
	var result_object;
	result_object = hazrd_analyzeRollFormat(roll_exp);
	if(result_object<0){
		alert("Wrong Format!");
		return -1;
	}
	var dice_sets = result_object["dice_sets"]
	
	//===========================
	// Rolling dices
	var dice_sets_result = [];
	for(var i=0;i<dice_sets.length;i++){
		if(dice_sets[i]["type"]==1){
			var result = [];
			for(var j=0;j<dice_sets[i]["number"];j++){
				result.push(Math.floor((Math.random()*dice_sets[i]["dice"]))+1);
			}
			dice_sets_result.push(result);
		}
		else
			dice_sets_result.push("");
	}

	//==========================
	// Analyze Result
	var total_sum = 0, number_sum = 0;
	// :: Sum up numbers
	for(var i=0; i<dice_sets.length; i++)
		if(dice_sets[i]["type"]==0) // only effect number
			number_sum += ((dice_sets[i]["sign"]=="-")? -1: 1) * dice_sets[i]["number"];

	// :: Counting each dices value/ success number
	for(var i=0; i<dice_sets.length; i++){
		var set = dice_sets[i];
		var set_multi = (set["sign"]=="-")? -1: 1;

		var set_sum = 0;
		if(set["type"]==1){ // only effect dice_result
			for(var j=0;j<set["number"];j++){
				if(result_object["is_separate"]){
					if(result_object["is_compared"]
					 && eval((dice_sets_result[i][j]+number_sum)+result_object["compare_symbo"]+result_object["compare_val"]))
					 	set_sum += 1;
				}
				else	set_sum += dice_sets_result[i][j];
			}
			set["result"] = set_sum;
			total_sum += set_multi * set["result"];
		}
	}
	if(!result_object["is_separate"])	total_sum += number_sum;

	//==========================
	// Output
	var result_string = hazrd_generateRollResult( result_object, dice_sets_result, total_sum, number_sum);
	
	haz_rd_updateByDb(roll_exp, result_string, roll_reason);
}


function hazrd_generateRollResult(roll_config, dice_sets_result, total_sum, number_sum){
	var result_string = "";

	var dice_sets   = roll_config["dice_sets"]
	var is_separate = roll_config["is_separate"];
	var is_compared = roll_config["is_compared"];

	// each dice set
	for(var i=0;i<dice_sets.length;i++){
		var set = dice_sets[i];

		// sign, does not show at the first object
		if(i!=0 && !(is_separate && set["type"]==0))
			result_string += " "+set["sign"]+" ";

		if(set["type"]==1){ // dice set
			if(!is_separate || is_compared)
				result_string += set["result"];
			result_string += "[";
			for(var j=0;j<set["number"];j++){
				if(j!=0)	result_string += ", ";
				result_string += dice_sets_result[i][j];
			}
			result_string += "]";
		}
		else{ //number
			if(is_separate) continue; // separate mode does not show number offset
			result_string += set["number"];
		}
	}

	if(!is_separate)	result_string += " → " + total_sum;
	else				result_string = "{"+result_string+"}";

	if(is_separate && number_sum!=0)
		result_string += ((number_sum>0)?"+":"") + number_sum;

	if(is_compared){
		result_string += " → ";
		if(!is_separate)	result_string += (eval(total_sum+roll_config["compare_symbo"]+roll_config["compare_val"]))? "成功": "失敗";
		else				result_string += total_sum+"成功骰";
	}

	return result_string;
}
function hazrd_analyzeRollFormat(roll_exp){
	var regex_structure, match_result, content;
	var return_object = {};

	// Initial
	return_object["dice_sets"] = [];

	// Check secret/ compare
	regex_structure = /^([s]?)([bd\d+-]*)(([><=]|(>=)|(<=))(-?\d+))?$/;
	match_result = roll_exp.match(regex_structure);
	if(match_result==null)	return -1;
	// :: analyze
	return_object["is_secret"]   = (match_result[1]!="");
	return_object["is_compared"] = (match_result[4]!=null );
	if(match_result[4]!=null){
		return_object["compare_symbo"] = (match_result[4]=="=")? "==": match_result[4];
		return_object["compare_val"]   = parseInt(match_result[7]);
	}
	content = match_result[2];

	// Check first dice set format
	regex_structure = /^(\d+[db]\d+)(.*)$/;
	match_result = content.match(regex_structure);
	if(match_result==null)	return -1;
	// :: analyze
	var diceset = hazrd_createDiceSet("+", match_result[1]);
	return_object["dice_sets"].push( diceset );
	return_object["is_separate"] = (diceset["special"]=="b");
	content = match_result[2];

	// Check remaind diceset/number format
	while(content!=""){
		regex_structure = /^([+-])((\d+[db]\d+)|(\d+))(.*)$/;
		match_result = content.match(regex_structure);	
		if(match_result==null)	return -1;
		// :: analyze
		var sign =    match_result[1];
		if(match_result[3]!=null)	return_object["dice_sets"].push( hazrd_createDiceSet(  sign, match_result[3]));
		else						return_object["dice_sets"].push( hazrd_createNumberSet(sign, parseInt(match_result[4])));
		content = match_result[5];
	}

	// :: check consistent
	var dice_sets = return_object["dice_sets"];
	for(var i=1;i<dice_sets.length;i++){
		if(dice_sets[i]["type"]==1 && dice_sets[0]["special"]!=dice_sets[i]["special"])	return -1;
	}

	return return_object;
}
//=============
// Structure functions
function hazrd_createDiceSet( sign, dice_expression){
	var regex = /^(\d+)([db])(\d+)$/;
	var match_result = dice_expression.match(regex);
	if(match_result==null)	return null;

	var number = parseInt(match_result[1]);
	var dice   = parseInt(match_result[3]);

	return {
		"sign" : sign,
		"type"  : 1,
		"number": ((number>20)? 20: number),
		"dice":   ((dice>100)? 100: dice),
		"special": match_result[2][0]
	};
}
function hazrd_createNumberSet( sign, number){
	return {
		"sign" : sign,
		"type"  : 0,
		"number": number
	};
}


//=============================
//  Support Functions
//=============================
function haz_rd_updateByDb(claim, results, reason) {
	var data = {
		action: "hz_rolldice",
		pid: getPidOfCurrentPage(),
		haz_dices_claim:	claim,
		haz_dices_result:	results,
		haz_dices_reason:	reason
	};
	var output = haz_rd_tableRender(claim, results, reason);
	
	var success = function(data){
		//console.log(data);
		haz_rd_updateByJquery(output);
	};
	var error = function(xhr, status, err) {
		//console.error(status, err.toString());
	};

	var hash = (location.hash);
	console.log(hash);

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

function haz_rd_tableRender(claim, results, reason){
	reason = reason.replace(/</g, '&lt;');
	reason = reason.replace(/>/g, '&gt;');
	
	if(results.match(/→(.*)$/)==null)
		results += "</td><td>";
	else
		results = results.replace(/→(.*)$/, '</td><td> → <b style=\'color:blue;\'>$1</b>');

	var TD = '';
	TD += '<td>: <b>'+claim+'</b></td>';
	TD += '<td> → '+results+'</td>';
	TD += '<td> '+reason+'</td>';
	return '<tr>'+TD+'</tr>';
}

function getPidOfCurrentPage(){
	var url = location.href;
	var result = url.match(/[?&]pid=(\d+)/);

	if(result==null) 	return 0;
	else				return parseInt(result[1]);
}