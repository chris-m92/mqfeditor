<?php
	include "../req/vendor/autoload.php";
		
	if(isset($_FILES["file"])) {
		$name = $_FILES["file"]["name"];
		$type = $_FILES["file"]["type"];
		$tempName = $_FILES["file"]["tmp_name"];
		
		$dtg = date("Y-m-d_h-i-s");
		
		$targetDir = "/opt/bitnami/apache2/htdocs/pdf_uploads/";
		$targetFile = $targetDir.$dtg."Z_".basename($name);
		
		//========================
		// Logging function
		//========================
		$logPath = "/opt/bitnami/apache2/htdocs/pdf_logs/";
		$filename = $dtg."Z_".basename($name)."-log.txt";
		
		$log = $logPath.$filename;
		$jsonLog = $logPath."json-".$filename;
		$lineLog = $logPath."line-".$filename;
		
		$logFile = fopen($log, "ab");
		$jsonFile = fopen($jsonLog, "ab");
		$lineFile = fopen($lineLog, "ab");
		
		if(!$logFile) {
			echo "Unable to open log file";
			exit;
		}
		
		$crlf = "\r\n";
		//=========================
		
		move_uploaded_file($tempName, $targetFile);
		
		$filetype = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
		
		if($filetype != "pdf") {
			header("Location: /parser?e=incorrectExtension");
			exit();
		}
		
		if($type != "application/pdf") {
			header("Location: /parser?e=incorrectFileType");
			exit();
		}

		// Parser
		$parser = new \Smalot\PdfParser\Parser();
		try {
			$pdf = $parser->parseFile($targetFile);
			unlink($targetFile);
			
			$text = $pdf->getText();
			
			// Split file into multiple lines
			$textArray = explode("\n", $text);
			
			$number = 1;
			
			$isQuestion = false;
			$isOption = false;
			$isAnswer = false;
			$isReference = false;
			$finished = false;
			
			$question = "";
			$option = "";
			$answer = "";
			$reference = "";
			
			$questionArray = array();			
			$optionArray = array();
			
			foreach($textArray as $line) {
				$line = trim($line);
				fwrite($lineFile, $line.$crlf);
				
				// Start looking for questions
				// Looks all the way through 999 questions "999."
				$first4 = substr($line, 0, 4);
				
				// If there is a ".", then we think that it's the number of the question
				if(strpos($first4, ".") !== false) {
					
					// Check the first 3 characters up to "999" and remove any spaces or periods
					$first3 = substr($line, 0, 3);
					$first3 = rtrim($first3, ". ");
					
					// If the remaining characters are numberic, then we assume it's a question
					if(is_numeric($first3)) {
						$isQuestion = true;
						$isAnswer = false;
						$isOption = false;
						$isReference = false;
						
						$question = "";
					}
				}
				
				// Start looking for options
				// We look for a pattern of "a.", "b.", "c." etc...
				// Could look into using regex for this 
				$first2 = substr($line, 0, 2);				
				if(strpos($first2, ".") == 1 && substr($line, 0, 4) != "T.O.") {
					if(!is_numeric(substr($line, 0, 1))) {
						fwrite($logFile, "-----Option-----".$crlf);
						fwrite($logFile, $line.$crlf);
						$isQuestion = false;
						$isOption = true;
						$isAnswer = false;
						$isReference = false;
					}
				}
				
				// If we're looking at a question, then add the line to the current question
				if($isQuestion) {
					fwrite($logFile, "-----Question-----".$crlf);
					fwrite($logFile, $line.$crlf);
					$question .= $line;
				}
				
				// Next up could be an option
				if($isOption) {
					array_push($optionArray, trim(substr($line, 3)));
					$isOption = false;
				}
				
				// Start looking for the answer and reference
				// First look for a colon
				$colon = substr($line, 0, 11);
				if($colon !== false) {
					// Remove any erroneous spaces
					$colon = str_replace(" ", "", $colon);
					
					// Look to see if the subject is an answer
					if(substr($colon, 0, 6) == "Answer") {
						fwrite($logFile, "-----Answer-----".$crlf);
						fwrite($logFile, $line.$crlf);
						$isQuestion = false;
						$isOption = false;
						$isAnswer = true;
						$isReference = false;
					}
					
					// Look to see if the subject is a reference
					if(substr($colon, 0, 9) == "Reference") {
						fwrite($logFile, "-----Reference-----".$crlf);
						fwrite($logFile, $line.$crlf.$crlf);
						$isQuestion = false;
						$isOption = false;
						$isAnswer = false;
						$isReference = true;
					}
				}
				
				// Looking at the answer
				if($isAnswer) {
					$answer = substr($line, -1);
					$isAnswer = false;
				}
				
				// Looking at the reference
				if($isReference) {
					$reference = substr($line, 11);
					$reference = trim($reference);
					
					$question = trim($question);
					$answer = trim($answer);
					
					// Change the answer from a letter to a number where
					// a = 0, b = 1, c = 2, d = 3, etc...
					$answer = ord(strtolower($answer)) - 97;
					
					$reference = trim($reference);
					
					$fullQuestion = array("number"=>$number, "question"=>$question, "type"=>"multiple_choice", "correct_response"=>$answer, "ref"=>$reference, "responses"=>$optionArray);
					
					//$fullQuestion = json_encode($fullQuestion);
					
					array_push($questionArray, $fullQuestion);
					
					$question = "";
					$answer = "";
					$option = "";
					$reference = "";
					
					$optionArray = array();
					
					$isQuestion = false;
					$isOption = false;
					$isAnswer = false;
					$isReference = false;
					$number++;
				}
			}
			
			$testArray = array("description"=>"", "name"=>substr($name,0,-4), "version"=>1,"questions"=>$questionArray);
			
			$jsonTest = json_encode($testArray);
			
			
			fwrite($jsonFile, $jsonTest);
		} catch(Exception $e) {
			if($e->getMessage() == "Missing catalog.") {
				echo "There was an error parsing your MQF, this is caused by printing a Word Doc to PDF. Instead, use the Save As function to save as a PDF and retry. If you still continue to receive this error, please send us an email with the PDF and error details.";
			} else {
				echo "There was an Error: ".$e->getMessage()." If you continue to see this error, please send us an email with the PDF and error details.";
			}
			unlink($targetFile);
		}
		echo "Finished.";
		$finished = true;
		fclose($logFile);
		fclose($jsonFile);
		fclose($lineFile);
	} else {
		$finished = false;
	}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php require("../req/head/head.php"); ?>
		<script>
			$(document).ready(function() {
				$("#file").change(function(e) {
					var filename = $("input[type=file]").val().split("\\").pop();
					$("#file-label").text(filename);
				});
				
				$("#mqf-upload-form").submit(function(e) {
					e.preventDefault();
					e.stopImmediatePropagation();
					var formData = new FormData($(this)[0]);
					//var file = $("input[type=file]")[0].files[0];
					var file = $("#file").get(0).files;
					if(file.length === 0) {
						$("#file").removeClass("is-valid");
						$("#file").addClass("is-invalid");
					} else {
						$("#file").removeClass("is-invalid");
						$("#file").addClass("is-valid");
						
						$(".card-footer").removeClass("d-none");
						$.ajax({
							xhr: function() {
								var xhr = new window.XMLHttpRequest();
								xhr.upload.addEventListener("progress", function(evt) {
									if(evt.lengthComputable) {
										var percentComplete = evt.loaded / evt.total;
										percentComplete = parseInt(percentComplete * 100);
										$(".progress-bar").css("width", percentComplete + "%");
										$(".progress-bar").html(percentComplete + "%");
									}
								}, false);
								return xhr;
							},
							type: "POST",
							url: "parser",
							data: formData,
							async: true,
							cache: false,
							contentType: false,
							processData: false,
							success: function(result) {
								$("#result").html(result);
							}
						});
					}
				});
				//return false;
			});
		</script>
    </head>
    <body id="bg">
		<noscript><?php require("../req/structure/js-alert.php"); ?></noscript>
		
		<div id="body-container" class="container">
			<div class="card my-5">
				<div class="card-body">
					<?php 
						if($finished) { 
							$testFile = file_get_contents($jsonLog);						
							$decoded = json_decode($testFile, true);
					?>
						<form method="POST" enctype="multipart/form-data" action="parse-edited">
							<div class="form-group">
								<div class="input-group mb-3">
									<div class="input-group-prepend">
										<span class="input-group-text">Name</span>
									</div>
									<input type="text" class="form-control" id="test-name" name="test-name" value="<?php echo $decoded["name"]; ?>">
								</div>
							</div>
							<div class="form-group">
								<div class="input-group mb-3">
									<div class="input-group-prepend">
										<span class="input-group-text">Description</span>
									</div>
									<input type="text" class="form-control" id="test-description" name="test-description" placeholder="Add a description">
								</div>
							</div>
							<div class="form-group">
								<div class="input-group mb-3">
									<div class="input-group-prepend">
										<span class="input-group-text">Version</span>
									</div>
									<input type="text" class="form-control" id="test-version" name="test-version" value="<?php echo $decoded["version"]; ?>">
								</div>
							</div>
							<?php foreach($decoded["questions"] as $q) { ?>
								<h4><?php echo $q["number"]; ?></h4>
								<div class="form-group">
									<div class="input-group mb-3">
										<div class="input-group-prepend">
											<span class="input-group-text">Question</span>
										</div>
										<input type="text" class="form-control" id="<?php echo "num-".$q["number"]."-question"; ?>" name="<?php echo "num-".$q["number"]."-question"; ?>" value="<?php echo $q["question"]; ?>">
									</div>
								</div>
								<?php foreach($q["responses"] as $key => $value) { ?>
									<div class="form-group">
										<div class="input-group mb-3">
											<div class="input-group-prepend">
												<span class="input-group-text">
													<?php 
														$key = strtoupper(chr($key+97));
														echo $key; 
													?>
												</span>
											</div>
											<input type="text" class="form-control" id="<?php echo "num-".$q["number"]."-$key"; ?>" name="<?php echo "num-".$q["number"]."-$key"; ?>" value="<?php echo $value; ?>">
										</div>
									</div>
								<?php } ?>
								<div class="form-group">
									<div class="input-group mb-3">
										<div class="input-group-prepend">
											<span class="input-group-text">Answer</span>
										</div>
										<input type="text" class="form-control" id="num-<?php echo $q["number"]; ?>-answer" name="num-<?php echo $q["number"]; ?>-answer" value="<?php echo strtoupper(chr($q["correct_response"]+97)); ?>">
									</div>
								</div>
								<div class="form-group">
									<div class="input-group mb-3">
										<div class="input-group-prepend">
											<span class="input-group-text">Reference</span>
										</div>
										<input type="text" class="form-control" id="num-<?php echo $q["number"]; ?>-reference" name="num-<?php echo $q["number"]; ?>-reference" value="<?php echo $q["ref"]; ?>">
									</div>
								</div>
							<?php } ?>
							<button type="submit" class="btn btn-block btn-success">Save changes</button>
						</form>
					<?php } else { ?>
						<form id="mqf-upload-form" method="POST" enctype="multipart/form-data">
							<div class="custom-file">
								<input type="file" class="custom-file-input" id="file" name="file" accept=".pdf">
								<label id="file-label" class="custom-file-label" for="file">Choose File</label>
							</div>
							<button type="submit" class="btn btn-block btn-primary mt-2">Upload File</button>
						</form>
					<?php } ?>
				</div>
				<div class="card-footer d-none">
					<div class="progress">
						<div class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
					</div>
					<div id="result"></div>
				</div>
			</div>
		</div>
    </body>
</html>



