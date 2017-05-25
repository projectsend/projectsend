<?php
require_once('sys.includes.php');
include('header-unlogged.php');

$page_title = __('Drop-Off Summary','cftp_admin');
//$form_action="dropoff_guest_action.php";

$auth = isset($_REQUEST['auth']) ? htmlspecialchars($_REQUEST['auth'],ENT_QUOTES, 'UTF-8') : '';
if(!empty($auth)){

	define('TABLE_DROPOFF','tbl_drop_off_request');
	$sql = $dbh->prepare( 'SELECT * FROM '.TABLE_DROPOFF.' WHERE auth_key = "'.$auth.'"' );	

	$sql->execute();
	$sql->setFetchMode(PDO::FETCH_ASSOC);
	$grow = $sql->fetch();
	if($grow) {
			$to_name = $grow["to_name"];
			$from_organization = $grow["from_organization"];
			$to_email = $grow["to_email"];
			
		
	}
	else {
		header('Location:'.SYSTEM_URI);
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

if($_POST) {
	$to = ($_REQUEST['to']) ? $_REQUEST['to'] : '';
	$comments = ($_REQUEST['comments']) ? $_REQUEST['comments'] : '';
	$auth = ($_REQUEST['auth']) ? $_REQUEST['auth'] : '';
	//$file1 = $_FILES['fileone'];
	//var_dump($_FILES['fileone']['tmp_name']);
$statement = $dbh->prepare("select id,user from ".TABLE_USERS." where email = '$to'");
$statement->execute();
$statement->setFetchMode(PDO::FETCH_ASSOC);
$userindo = $statement->fetch();
if($userindo) {
// user exist
//---------------------------------------------------------------------------------
// Settings
$targetDir = UPLOADED_FILES_FOLDER;

$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds

@set_time_limit(UPLOAD_TIME_LIMIT);

// Uncomment this one to fake upload time
// usleep(5000);

// Get parameters
$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;


$filecount = count($_FILES['userfiles']['name']);

for($i = 0 ; $i < $filecount; $i++) {
// looop start ------------------------------------------------------------------------------------------
		$fileName = isset($_FILES['userfiles']['name'][$i]) ? $_FILES['userfiles']['name'][$i] : '';
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
					
					/* AES Decryption started by RJ-07-Oct-2016 */
					//$blockSize = 256;
						//$inputKey = "project send encryption";
						
					$fileData = file_get_contents($filePath);
					$aes = new AES($fileData, ENCRYPTION_KEY, BLOCKSIZE);
					$encData = $aes->encrypt();
					unlink($filePath);
					file_put_contents($filePath , $encData);
					/* AES Decryption ended by RJ-07-Oct-2016 */
		
		// Return JSON-RPC response
		//die('{"jsonrpc" : "2.0", "result" : null, "id" : "id", "NewFileName" : "'.$fileName.'"}');
		
		//----------------------------------
		$url = $fileName;
		 $fromid = $userindo['id'];
		 $filenamearray = explode(".",$url);
		 $filename = $filenamearray[0];
		 $public_allow = 0;
		
		$uploader = $userindo['user'];
		
		 //var_dump($filename ,$fromid , $_POST);
		 $time = '2017-03-02 00:00:00';
		 $expdate = '2017-03-09 00:00:00';
		 
		$statement = $dbh->prepare("INSERT INTO ".TABLE_FILES." (`url`, `filename`, `description`, `timestamp`, `uploader`, `expires`, `expiry_date`, `public_allow`, `public_token`) VALUES ('$url', '$filename', '', CURRENT_TIMESTAMP, '$uploader', '0', '2017-12-09 00:00:00', '0', NULL);");
		if($statement->execute()) {
			$img_id = $dbh->lastInsertId();
			$filesrelations = $dbh->prepare("INSERT INTO ".TABLE_FILES_RELATIONS." (`timestamp`, `file_id`, `client_id`, `group_id`, `folder_id`, `hidden`, `download_count`) VALUES (CURRENT_TIMESTAMP, ".$img_id.", ".$fromid.", NULL, NULL, '0', '0')");
			//var_dump($filesrelations);
			$filesrelations->execute();
		}
// loop end ---------------------------------------------------------------------------------------------		
}
//----------------------------------
//------------------------------------------------------------------------------------
echo "<div class='alert alert-success alert-dismissable'><a href='#' class='close' data-dismiss='alert' aria-label='close'>&times;</a><strong>Success!</strong> Your file has been uploaded successfully.</div>";
}
else {
// user do not exist

	
}
}
?>


  <h2><?php echo $page_title; ?></h2>
  <div style="width:600px;background: white none repeat scroll 0 0;border: 1px solid #adadad;margin: 0 auto;padding: 64px;width: 600px;">
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" name="addclient" enctype="multipart/form-data" method="post" class="form-horizontal" autocomplete="off">
      <div class="form-group">
        <div class="col-sm-4"></div>
        <div class="col-sm-8"> This web page will allow you to drop-off (upload) one or more files for a MicroHealth user. </div>
        <label for="from_mail_id" class="col-sm-4 control-label">
          <?php _e('From','cftp_admin'); ?>
        </label>
        <div class="col-sm-8"> <span style="width:100%;border:1px solid #ccc;padding:25px;float:left;"> <?php echo $to_name; ?></span> </div>
      </div>
      <div class="form-group">
        <label for="from_organization" class="col-sm-4 control-label">
          <?php _e('To','cftp_admin'); ?>
        </label>
        <div class="col-sm-8 pos-relative">
          <input type="text" name="to" id="microhealthuserid" class="form-control required" value="" placeholder="Enter MicroHealth Send user mail Id" />
          <input type="hidden" name="auth" id="auth" class="form-control required" value="<?php echo $auth; ?>"/>
          <div id="searchres"></div>
        </div>
      </div>
      <div class="form-group">
        <label for="to_email_request" class="col-sm-4 control-label">
          <?php _e('Coments','cftp_admin'); ?>
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
        <div class="col-sm-6">
          <input type="file" name="userfiles[]" id="fileone" class="form-control required" value="" placeholder="upload file" />
        </div>
        <div class="col-sm-2">
        <span class="glyphicon glyphicon-plus cc-add-file" aria-hidden="true"></span>
        </div>
      </div>
      </div>
      <div class="inside_form_buttons text-rgt">
        <button type="submit" name="submit" class="btn btn-default">Send the request</button>
      </div>
    </form>
  </div>
</div>
</div>
</div>
<script type="text/javascript">
$(document).ready(function(e) {
	$("#microhealthuserid").keyup(function(){
		var search_string = $("#microhealthuserid").val();
		if(search_string == ''){$("#searchres").html('');}
		else{postdata = {'checkmicrosenduser' : search_string}
		$.post("checkmicrosenduser.php",postdata,function(data){	
		
		$("#searchres").html(data);	
		});
	}});
	$(document).on('click', ".mhusermid li", function() {
		$("#microhealthuserid").val($(this).text());
		//$(".mhusermid").toggle();
		
	});
	$('body').click(function() {
$(".mhusermid").hide();

});
$(document).on('click','.cc-add-file',function() {
	$(".cc-file-container").append("<div class='form-group'><label for='to_email_request' class='col-sm-4 control-label'>File</label><div class='col-sm-6'><input type='file' name='userfiles[]' id='fileone' class='form-control required' value='' placeholder='upload file' /></div><div class='col-sm-2'><span class='glyphicon glyphicon-plus cc-add-file' aria-hidden='true'></span><span class='glyphicon glyphicon-remove cc-remove-file' aria-hidden='true'></span></div></div>")
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
</style>