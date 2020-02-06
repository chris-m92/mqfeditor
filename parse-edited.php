<?php
	$number = 1;
	
	$questionArray = array();
	
	if(!isset($_POST["test-name"]) || $_POST["test-name"] == "") {
		header("Location: /parser?e=EditedFileMissingName");
		exit;
	}
	
	if(!isset($_POST["test-description"]) || $_POST["test-description"] == "") {
		header("Location: /parser?e=EditedFileMissingDescription");
		exit;
	}
	
	if(!isset($_POST["test-version"]) || $_POST["test-version"] == "") {
		header("Location: /parser?e=EditedFileMissingVersion");
		exit;
	}
	
	while(isset($_POST["num-$number-question"])) {
		
		// Get the question
		$question = $_POST["num-$number-question"];
		
		$optionArray = array();
		
		// Get the options
		if(isset($_POST["num-$number-A"])) {
			array_push($optionArray, $_POST["num-$number-A"]);
		}
		if(isset($_POST["num-$number-B"])) {
			array_push($optionArray, $_POST["num-$number-B"]);
		}
		if(isset($_POST["num-$number-C"])) {
			array_push($optionArray, $_POST["num-$number-C"]);
		}
		if(isset($_POST["num-$number-D"])) {
			array_push($optionArray, $_POST["num-$number-D"]);
		}
		
		// Get the answer
		$answer = $_POST["num-$number-answer"];
		
		// Change the answer back to an integer
		$answer = ord(strtolower($answer)) - 97;
		
		// Get the reference
		$reference = $_POST["num-$number-reference"];
		

		$tempArray = array("question"=>$question, "type"=>"multiple_choice", "correct_response"=>$answer, "ref"=>$reference, "responses"=>$optionArray);
		
		array_push($questionArray, $tempArray);
		$number++;
	}
	
	$testArray = array("description"=>$_POST["test-description"], "name"=>$_POST["test-name"], "version"=>$_POST["test-version"], "questions"=>$questionArray);
	
	$jsonEncodedTest = json_encode($testArray);
	
	echo $jsonEncodedTest;
	
?>