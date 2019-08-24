<?php
require_once('sys.includes.php');
include('header-unlogged.php');
$page_title = __('Drop-Off Summary','cftp_admin');

$auth = isset($_REQUEST['auth']) ? htmlspecialchars($_REQUEST['auth'],ENT_QUOTES, 'UTF-8') : '';
if(!empty($auth)){
	define('TABLE_DROPOFF','tbl_drop_off_request');
	$sql = $dbh->prepare( 'SELECT * FROM '.TABLE_DROPOFF.' WHERE auth_key = "'.$auth.'"' );
	$sql->execute();
	$sql->setFetchMode(PDO::FETCH_ASSOC);
	$grow = $sql->fetch();
	if(!empty($grow) && count($grow)>0)
	{
		$authorization = true;
		if($grow['status']==0) {
			$to_name = $grow["from_name"];
			$from_organization = $grow["from_organization"];
			$to_email = $grow["to_email"];
			$duplicate_access= true;
		}
		else{
			$duplicate_access= false;
		}
	}
	else {
		$authorization = false;
	}
} ?>
<div id="main">
	<div id="content" class="container">
		<div class="row">
		<?php
		$target_dir = UPLOADED_FILES_FOLDER;
		$target_file = $target_dir;
		$uploadOk = 1;
		$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
		if($duplicate_access) {
			if($_POST)
			{
				$to = ($_REQUEST['to']) ? $_REQUEST['to'] : '';
				$comments = ($_REQUEST['comments']) ? $_REQUEST['comments'] : '';
				$auth = ($_REQUEST['auth']) ? $_REQUEST['auth'] : '';
				$statement = $dbh->prepare("select id,user from ".TABLE_USERS." where email = '$to'");
				$statement->execute();
				$statement->setFetchMode(PDO::FETCH_ASSOC);
				$userindo = $statement->fetch();
				if($userindo)
				{
					$targetDir = UPLOADED_FILES_FOLDER;
					$cleanupTargetDir = true; // Remove old files
					$maxFileAge = 5 * 3600; // Temp file age in seconds
					@set_time_limit(UPLOAD_TIME_LIMIT);
					// Get parameters
					$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
					$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
					$filecount = count($_FILES['userfiles']['name']);
					$array_file_name = array();

					if(!empty($_FILES['userfiles']['name'][0]))
					{

						$zip = new ZipArchive();
						$zipName = isset($_FILES['userfiles']['name'][0]) ? $_FILES['userfiles']['name'][0] : '';
						$this_file = new PSend_Upload_File();
						// Rename the file
						$zipName = $this_file->safe_rename($zipName);
						$ext = strrpos($zipName, '.');
						$zipName_a = substr($zipName, 0, $ext);
						$zipName_b = substr($zipName, $ext);

						$count = 1;
						while (file_exists($targetDir . DIRECTORY_SEPARATOR . $zipName_a . '_guestdrop_' . $count .'_'. $zipName_b))
						$count++;
						$zipRealName = $zipName_a . '_guestdrop_' . $count.'_'. $zipName_b;
						    $withoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $zipRealName);
						    $zipRealName=$withoutExt.".zip";
						    $r = $zip->open(UPLOADED_FILES_FOLDER.$zipRealName,  ZipArchive::CREATE);


						for($i = 0 ; $i < $filecount; $i++)
						{
							$file_empty = isset($_FILES['userfiles']['name'][$i]) ? $_FILES['userfiles']['name'][$i] : '';
							if (!empty($file_empty) )
							{

								/*looop start ------------------------------------------------------------------- */
								$fileName = isset($_FILES['userfiles']['name'][$i]) ? $_FILES['userfiles']['name'][$i] : '';
								$requestType=$_POST['request_type'];
								$this_file = new PSend_Upload_File();
								// Rename the file
								$fileName = $this_file->safe_rename($fileName);

								// Make sure the fileName is unique but only if chunking is disabled
								if ($chunks < 2 && file_exists($targetDir . $fileName)) {
									$ext = strrpos($fileName, '.');
									$fileName_a = substr($fileName, 0, $ext);
									$fileName_b = substr($fileName, $ext);

									$count = 1;
									while (file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName_a . '_' . $count . $fileName_b))
										$count++;

									$fileName = $fileName_a . '_' . $count . $fileName_b;
								}

								$filePath = $targetDir .$fileName;
								// Create target dir
								if (!file_exists($targetDir))
									@mkdir($targetDir);

								// Remove old temp files
								if ($cleanupTargetDir && is_dir($targetDir) && ($dir = opendir($targetDir))) {
									while (($file = readdir($dir)) !== false) {
										$tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

										// Remove temp file if it is older than the max age and is not the current file
										if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge) && ($tmpfilePath != "{$filePath}.part")) {
											@unlink($tmpfilePath);
										}
									}

									closedir($dir);
								} else {
									die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');
								}


								// Look for the content type header
								if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
									$contentType = $_SERVER["HTTP_CONTENT_TYPE"];

								if (isset($_SERVER["CONTENT_TYPE"]))
									$contentType = $_SERVER["CONTENT_TYPE"];
								// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
								if (strpos($contentType, "multipart") !== false) {
									if (isset($_FILES['userfiles']['tmp_name'][$i]) && is_uploaded_file($_FILES['userfiles']['tmp_name'][$i])) {
										// Open temp file
										$out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
										if ($out) {
											// Read binary input stream and append it to temp file
											$in = fopen($_FILES['userfiles']['tmp_name'][$i], "rb");

											if ($in) {
												while ($buff = fread($in, 4096))
													fwrite($out, $buff);
											} else
												die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
											fclose($in);
											fclose($out);

											@unlink($_FILES['userfiles']['tmp_name'][$i]);
										} else
											die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
									} else
										die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
								} else {
									// Open temp file
									$out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
									if ($out) {
										// Read binary input stream and append it to temp file
										$in = fopen("php://input", "rb");

										if ($in) {
											while ($buff = fread($in, 4096))
												fwrite($out, $buff);
										} else
											die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');

										fclose($in);
										fclose($out);
									} else
										die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
								}

								// Check if file has been uploaded
								if (!$chunks || $chunk == $chunks - 1) {
									// Strip the temp .part suffix off
									rename("{$filePath}.part", $filePath);
								}
								$aes = new AESENCRYPT();
								$aes->encryptFile($fileName);
								$r=$zip->addFile($filePath,$fileName);

							}
						}

						$r=$zip->close();
						$url = $zipRealName;
						$fromid = $userindo['id'];
						$filenamearray = explode(".",$url);
						$filename = $filenamearray[0];		 $array_file_name[] = $filenamearray[0];
						$public_allow = 0;
						$uploader = $to_name;
						$time = '2017-03-02 00:00:00';
						$expdate = '2017-03-09 00:00:00';
						$statement = $dbh->prepare("INSERT INTO ".TABLE_FILES." (`url`, `filename`, `description`, `timestamp`, `uploader`, `expires`, `expiry_date`, `public_allow`, `public_token`,`request_type`) VALUES ('$url', '$zipRealName', '', CURRENT_TIMESTAMP, '$uploader', '0', '2017-12-09 00:00:00', '0', NULL,'$requestType');");
						if($statement->execute()) {
							$img_id = $dbh->lastInsertId();
							$filesrelations = $dbh->prepare("INSERT INTO ".TABLE_FILES_RELATIONS." (`timestamp`, `file_id`, `client_id`, `group_id`, `folder_id`, `hidden`, `download_count`) VALUES (CURRENT_TIMESTAMP, ".$img_id.", ".$fromid.", NULL, NULL, '0', '0')");
							if($filesrelations->execute()) {
								$file_status=true;

							}
						}
					}
					else {
						echo "<div class='alert alert-warning alert-dismissable'><a href='#' class='close' data-dismiss='alert' aria-label='close'>&times;</a><strong>Failed!</strong> Please choose at least one file.</div>";
					}
					if($file_status) {
						$notification_auth = $dbh->prepare("UPDATE " . TABLE_DROPOFF . " SET `status` = '1' , `to_note_request`= '".$comments."',`from_email`= '".$to."' WHERE auth_key=:auth_key");
						$notification_auth->bindParam(':auth_key', $auth);
						if($notification_auth->execute()) {
							$notify_client = new PSend_Email();
							$email_arguments = array(
								'type' => 'new_files_for_client',
								'address' => $to,
								'files_list' =>
								$array_file_name
							);
							$try_sending = $notify_client->psend_send_email($email_arguments);
						        $new_log_action = new LogActions();
							$log_action_args = array(
													'action' => 38,
													'owner_id' => $userindo["id"],
													'affected_file_name' =>$fileName,
													'affected_account_name' => $userindo["user"],
													'owner_user' => $to_name,
												);
							$new_record_action = $new_log_action->log_action_save($log_action_args);
								echo "<div class='alert alert-success alert-dismissable'><a href='#' class='close' data-dismiss='alert' aria-label='close'>&times;</a><strong>Success!</strong> Your file has been uploaded successfully.</div>";
						}

					}
				}
				else
				{
					if(empty($to) && $to=='') {
						echo "<div class='alert alert-warning alert-dismissable'><a href='#' class='close' data-dismiss='alert' aria-label='close'>&times;</a><strong>Failed!</strong> Please fill the Email ID.</div>";
					}
					else if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
								echo "<div class='alert alert-warning alert-dismissable'><a href='#' class='close' data-dismiss='alert' aria-label='close'>&times;</a><strong>Failed!</strong> Please type valid email ID.</div>";
					}
					else {
						echo "<div class='alert alert-warning alert-dismissable'><a href='#' class='close' data-dismiss='alert' aria-label='close'>&times;</a><strong>Failed!</strong> Email ID is not exist in our record.</div>";
					}

				}
			}
		}
	?>
	<?php
	if($duplicate_access) {
	?>
	  <h2><?php echo $page_title; ?></h2>
	  <div class="error_div" style="text-align: center;color: red;padding: 10px;"><?php echo !empty($error_message)?$error_message:''; ?></div>
	  <div style="width:600px;background: white none repeat scroll 0 0;border: 1px solid #adadad;margin: 0 auto;padding: 32px 64px 12px 64px;width: 600px;">
		<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" name="addclient" enctype="multipart/form-data" method="post" class="form-horizontal" autocomplete="off">
			<input type="hidden" name="request_type" value="2">
			<div class="form-group">
			<div class="col-sm-4"></div>

			<div class="col-sm-8"> This web page will allow you to drop-off (upload) one or more files for a MicroHealth user. </div>
			<label for="from_mail_id" class="col-sm-4 control-label">
			  <?php _e('From','cftp_admin'); ?>
			</label>
			<div class="col-sm-8"> <span style="width:100%;border:1px solid #ccc;padding:15px;float:left;"> <?php echo $to_name; ?></span> </div>
		  </div>
		  <div class="form-group">
			<label for="from_organization" class="col-sm-4 control-label">
			  <?php _e('To','cftp_admin'); ?>
			</label>
			<div class="col-sm-8 pos-relative">
			  <input type="text" name="to" id="microhealthuserid" class="form-control required" value="<?php echo isset($to)?$to:''; ?>" placeholder="Enter MicroHealth Send user mail Id" />
			  <input type="hidden" name="auth" id="auth" class="form-control required" value="<?php echo $auth; ?>"/>
			  <div id="searchres"></div>
			</div>
		  </div>
		  <div class="form-group">
			<label for="to_email_request" class="col-sm-4 control-label">
			  <?php _e('Comments','cftp_admin'); ?>
			</label>
			<div class="col-sm-8">
			  <textarea name="comments" rows="4" cols="40" class="full-width"> </textarea>
			</div>
		  </div>
		  <div class="cc-file-container">
			<div class="form-group">
			<label for="to_email_request" class="col-sm-4 control-label">
			  <?php _e('File','cftp_admin'); ?>
			</label>
			<div class="col-sm-6 A">
			  <input type="file" name="userfiles[]" id="fileone" class="form-control userfiles required" value="" placeholder="upload file" style="padding:0;" />
			  <div class="error_file_empty" ></div>
			</div>
			<div class="col-sm-2">
			<span class="glyphicon glyphicon-plus cc-add-file" aria-hidden="true"></span>
			</div>
		  </div>
		  </div>
		  <div class="inside_form_buttons text-rgt">
			<button type="submit" name="submit" class="btn btn-success">Submit Upload</button>
		  </div>
		  <div class="form-group">
			  <div class="col-md-12 note_file_upload">
				<p>NOTES:<br><em>For Multiple file upload please choose '+' icon near the upload file.<br>The maximum allowed file size (in mb.) is 2048.<br>No empty files (0 KB) allowed.</em></p>
			  </div>
		  </div>
		</form>
	  </div>
	</div>
	<?php
	}
	else if (!$authorization){
		echo "<h2 style='text-align: center;'>";echo "You are not authorized to access this page !";	echo "</h2>";
	}
	else if(!$duplicate_access) {
		echo "<h2 style='text-align: center;'>";echo "You have already uploaded a file for this request!";	echo "</h2>";
	}
	?>
	</div>
</div>
<script type="text/javascript">
$(document).ready(function(e) {

	$(document).on('click', ".mhusermid li", function() {
		$("#microhealthuserid").val($(this).text());
		//$(".mhusermid").toggle();

	});
	$('body').click(function() {
$(".mhusermid").hide();

});
$(document).on('click','.cc-add-file',function() {
	//check_empty();
	var $ccc = $(this).parent().prev('.A').find('.userfiles').val();
	if($ccc!='') {
		$(this).parent().prev('.A').find('.error_file_empty').html('');
	$(".cc-file-container").append("<div class='form-group'><label for='to_email_request' class='col-sm-4 control-label'>File</label><div class='col-sm-6 A'><input type='file' name='userfiles[]' id='fileone' class='form-control required userfiles' value='' placeholder='upload file' /><div class='error_file_empty'></div></div><div class='col-sm-2'><span class='glyphicon glyphicon-plus cc-add-file' aria-hidden='true'></span><span class='glyphicon glyphicon-remove cc-remove-file' aria-hidden='true'></span></div></div>");
	}
	else {
		$(this).parent().prev('.A').find('.error_file_empty').html("Please choose the file first");
	}
});
$(document).on('click','.cc-remove-file',function() {
	$(this).parent().parent().remove();
});
});


</script>
<style type="text/css">
.mhusermid {
	padding: 15px;
    width: 304px;
    position: absolute;
    top: 34px;
    left: 15px;
    background: white;
    z-index: 99;
    box-sizing: border-box;
    border: 1px solid #eee;
	list-style-type:none;
}
.mhusermid li {
	padding:10px;
	border-bottom:1px solid #eee;
	cursor:pointer;
}
.mhusermid li:last-child {
	border-bottom:none;
}
.pos-relative {
	position:relative;
}
.full-width {
	width:100%;
}
.cc-add-file {
	padding: 5px;
    color: white;
    background: #0c920c;
    border-radius: 50%;
    font-size: 11px;
	cursor:pointer;
}
.cc-remove-file {
	padding: 5px;
    color: white;
    background: #ff0a0a;
    border-radius: 50%;
    font-size: 11px;
	cursor:pointer;
	margin-left:3px;
}
.userfiles {padding:0}
.note_file_upload {
	padding-top: 24px;
}
.error_file_empty {color:#fb0303;}
</style>
