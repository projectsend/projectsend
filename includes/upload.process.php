<?php
define('FILE_UPLOADING', true);

/**
 *  Call the required system files
 */
$allowed_levels = array(9,8,7,0);
require_once '../bootstrap.php';

/**
 * If there is no valid session/user block the upload of files
 */
if ( !check_for_session() ) {
	exit;
}

function dieWithError($message = null, $code = 400)
{
    header('Content-Type: application/json');
    $response = [
        'OK' => 0,
        'error' => [
            'code' => $code,
            'message' => $message,
            'filename' => $_REQUEST["name"]
        ]
    ];

    echo json_encode($response);
    http_response_code($code);
    exit;
}

/**
 * upload.php
 *
 * Copyright 2009, Moxiecode Systems AB
 * Released under GPL License.
 *
 * License: http://www.plupload.com/license
 * Contributing: http://www.plupload.com/contributing
 */
// HTTP headers for no cache etc
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Settings
$targetDir = UPLOADED_FILES_DIR;

$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds

@set_time_limit(UPLOAD_TIME_LIMIT);

// Uncomment this one to fake upload time
// usleep(5000);

// Get parameters
$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';

// Validate file has an acceptable extension
$fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
$allowedExt = explode(',', get_option('allowed_file_types') );
if ( false === CAN_UPLOAD_ANY_FILE_TYPE ) {
    if (!in_array($fileExt, $allowedExt)) {
        dieWithError('Invalid Extension');
    };
}

// Make sure the fileName is unique but only if chunking is disabled
if ($chunks < 2 && file_exists($targetDir . DS . $fileName)) {
	$ext = strrpos($fileName, '.');
	$fileName_a = substr($fileName, 0, $ext);
	$fileName_b = substr($fileName, $ext);

	$count = 1;
	while (file_exists($targetDir . DS . $fileName_a . '_' . $count . $fileName_b))
		$count++;

	$fileName = $fileName_a . '_' . $count . $fileName_b;
}

$filePath = $targetDir . DS . $fileName;

// Create target dir
if (!file_exists($targetDir))
	@mkdir($targetDir);

// Remove old temp files	
if ($cleanupTargetDir && is_dir($targetDir) && ($dir = @opendir($targetDir))) {
	while (($file = readdir($dir)) !== false) {
		$tmpfilePath = $targetDir . DS . $file;

		// Remove temp file if it is older than the max age and is not the current file
		if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge) && ($tmpfilePath != "{$filePath}.part")) {
			@unlink($tmpfilePath);
		}
	}

	closedir($dir);
} else
    dieWithError('Failed to open temp directory');
	

// Look for the content type header
if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
	$contentType = $_SERVER["HTTP_CONTENT_TYPE"];

if (isset($_SERVER["CONTENT_TYPE"]))
	$contentType = $_SERVER["CONTENT_TYPE"];

// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
if (strpos($contentType, "multipart") !== false) {
	if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
		// Open temp file
		$out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
		if ($out) {
			// Read binary input stream and append it to temp file
			$in = fopen($_FILES['file']['tmp_name'], "rb");

			if ($in) {
				while ($buff = fread($in, 4096))
					fwrite($out, $buff);
            } else
                dieWithError('Failed to open input stream');
			fclose($in);
			fclose($out);
			@unlink($_FILES['file']['tmp_name']);
        } else {
            dieWithError('Failed to open output stream');
        }
    } else {
        dieWithError('Failed to move uploaded file');
    }
} else {
	// Open temp file
	$out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
	if ($out) {
		// Read binary input stream and append it to temp file
		$in = fopen("php://input", "rb");

		if ($in) {
			while ($buff = fread($in, 4096))
				fwrite($out, $buff);
        } else {
            dieWithError('Failed to open input stream');
        }

		fclose($in);
		fclose($out);
    } else {
        dieWithError('Failed to open output stream');
    }
}

// Check if file has been uploaded
if (!$chunks || $chunk == $chunks - 1) {
	// Strip the temp .part suffix off 
	rename("{$filePath}.part", $filePath);

    // Add to database
    $this_file = new \ProjectSend\Classes\Files;
    $move = $this_file->moveToUploadDirectory($filePath);
    $set_defaults = $this_file->setDefaults();
    $add_to_db = $this_file->addToDatabase();

    // Return JSON-RPC response
    $response = [
        'OK' => 1,
        'info' => [
            'id' => $add_to_db['id'],
            'NewFileName' => $fileName
        ]
    ];
    
    echo json_encode($response);
    http_response_code(200);
    exit;
}