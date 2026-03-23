<?php
/**
 *  Call the required system files
 */
require_once '../bootstrap.php';

/**
 * If there is no valid session/user block the upload of files
 */
if ( !user_is_logged_in() ) {
	exit;
}

// Release session lock to prevent blocking keep-alive AJAX requests during upload
// All session data has been read and CURRENT_USER_* constants are already set
session_write_close();

function dieWithError($message = null, $code = 400)
{
    header('Content-Type: application/json');
    $response = [
        'OK' => 0,
        'error' => [
            'code' => $code,
            'message' => $message,
            'filename' => $_POST["name"]
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
$chunk = isset($_POST["chunk"]) ? intval($_POST["chunk"]) : 0;
$chunks = isset($_POST["chunks"]) ? intval($_POST["chunks"]) : 0;
$fileName = isset($_POST["name"]) ? $_POST["name"] : '';

// Validate file has an acceptable extension
if (!file_is_allowed($fileName)) {
    dieWithError('Invalid Extension');
}

// Create target dir
if (!file_exists($targetDir))
	@mkdir($targetDir);

// Check for directory traversal
$basePath = $targetDir . DS;
$realBase = realpath($basePath);

$filePath = dirname($basePath . $fileName);
$realFilePath = realpath($filePath);

if ($realFilePath === false || strpos($realFilePath, $realBase) !== 0) {
    dieWithError("Directory Traversal Detected!");
}

$filePath = $targetDir . DS . $fileName;

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

    // Get storage selection from request or use default
    $storage_selection = isset($_POST['storage_selection']) ? $_POST['storage_selection'] : get_option('default_upload_storage', 'local');

    // Check if encryption is requested
    $encrypt_file = false;
    if (isset($_POST['encrypt_file']) && $_POST['encrypt_file'] === '1') {
        $encrypt_file = true;
    } elseif (\ProjectSend\Classes\Encryption::isRequired()) {
        // Encryption is required globally
        $encrypt_file = true;
    } elseif (\ProjectSend\Classes\Encryption::isEnabled()) {
        // Encryption is enabled by default but not required
        $encrypt_file = true;
    }

    // Encrypt file if requested/required
    if ($encrypt_file) {
        try {
            $encryption = new \ProjectSend\Classes\Encryption();

            // Generate unique file key
            $file_key = $encryption->generateFileKey();

            // Encrypt the file
            $encrypted_path = $filePath . '.encrypted';
            $encrypt_result = $encryption->encryptFile($filePath, $encrypted_path, $file_key);

            if (!$encrypt_result['success']) {
                dieWithError('Encryption failed: ' . $encrypt_result['error']);
            }

            // Encrypt the file key with master key
            $encrypted_key_data = $encryption->encryptFileKey($file_key);

            // Replace original file with encrypted version
            unlink($filePath);
            rename($encrypted_path, $filePath);

            // Store encryption metadata for later use
            $encryption_metadata = [
                'encrypted' => 1,
                'encryption_key_encrypted' => $encrypted_key_data['encrypted_key'],
                'encryption_iv' => $encrypted_key_data['iv'],
                'encryption_algorithm' => $encryption->getAlgorithm(),
                'encryption_file_iv' => $encrypt_result['iv']
            ];

        } catch (\Exception $e) {
            error_log('File encryption error: ' . $e->getMessage());
            dieWithError('File encryption failed');
        }
    } else {
        $encryption_metadata = [
            'encrypted' => 0,
            'encryption_key_encrypted' => null,
            'encryption_iv' => null,
            'encryption_algorithm' => null,
            'encryption_file_iv' => null
        ];
    }

    // Add to database
    $file = new \ProjectSend\Classes\Files;

    // Set encryption metadata
    $file->encrypted = $encryption_metadata['encrypted'];
    $file->encryption_key_encrypted = $encryption_metadata['encryption_key_encrypted'];
    $file->encryption_iv = $encryption_metadata['encryption_iv'];
    $file->encryption_algorithm = $encryption_metadata['encryption_algorithm'];
    $file->encryption_file_iv = $encryption_metadata['encryption_file_iv'];

    // Route to appropriate storage based on selection
    $route_result = $file->routeToStorage($filePath, $storage_selection, $fileName);

    if ($route_result && isset($route_result['filename_original'])) {
        // setDefaults() must be called after routeToStorage() because it sets
        // title from filename_original, which is populated by generateSafeFilename()
        $file->setDefaults();
        $result = $file->addToDatabase();
    } else {
        $result = [
            'status' => 'error',
            'message' => __('Failed to process file upload to selected storage.', 'cftp_admin')
        ];
    }

    if ($result['status'] === 'success') {
        // Return JSON-RPC response
        $response = [
            'OK' => 1,
            'info' => [
                'id' => $file->getId(),
                'NewFileName' => $fileName,
                'encrypted' => $encryption_metadata['encrypted']
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
        http_response_code(200);
    } else {
        // Return error response in same format as dieWithError()
        $response = [
            'OK' => 0,
            'error' => [
                'code' => 400,
                'message' => $result['message'],
                'filename' => $fileName
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
        http_response_code(400);
    }
    exit;
}
