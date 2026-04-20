<?php
/**
 * Class that handles actions that do not return any UI.
 * 
 * @todo replace! This functions should go into routes and more specific classes
 */
namespace ProjectSend\Classes;

use \PDO;
use \ZipArchive;

class Download
{
    private $dbh;
    private $logger;

    public function __construct()
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;
    }

    public function download($file_id)
    {
        if (!$file_id || !user_can_download_file(CURRENT_USER_ID, $file_id)) {
            exit_with_error_code(403);
        }

        $file = new \ProjectSend\Classes\Files($file_id);
        $download_result = record_new_download(CURRENT_USER_ID, $file->id);

        // Check if download limit was reached
        if (is_array($download_result) && !$download_result['allowed']) {
            header("HTTP/1.0 403 Forbidden");
            $msg = $download_result['message'];
            echo system_message('danger', $msg);
            exit;
        }

        // Handle external files differently
        if ($file->storage_type !== 'local' && !empty($file->integration_id)) {
            $this->downloadExternalFile($file);
        } else {
            $this->downloadFile($file->filename_on_disk, $file->filename_unfiltered, $file->id);
        }
    }

    /**
     * Handle downloads for external storage files
     */
    private function downloadExternalFile($file)
    {
        // Get the integration and create storage instance
        $integrations_handler = new \ProjectSend\Classes\Integrations();
        $integration = $integrations_handler->getById($file->integration_id);

        if (!$integration) {
            exit_with_error_code(404);
        }

        $storage = $integrations_handler->createStorageInstance($integration);
        if (!$storage) {
            exit_with_error_code(500);
        }

        // Record the download log
        if (current_role_in(['Client'])) {
            $log_action_number = 8;
        } else {
            $log_action_number = 7;
        }

        $this->logger->addEntry([
            'action' => $log_action_number,
            'owner_id' => CURRENT_USER_ID,
            'affected_file' => (int)$file->id,
            'affected_file_name' => $file->filename_original,
            'affected_account' => CURRENT_USER_ID,
            'file_title_column' => true
        ]);

        // For S3 and similar services, redirect to presigned URL for direct download
        if (method_exists($storage, 'getPresignedUrl')) {
            // Generate a presigned URL with 1 hour expiration
            $presigned_url = $storage->getPresignedUrl($file->external_path, 3600);
            if ($presigned_url) {
                // Set headers to force download with correct filename
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $file->filename_original . '"');
                header('Location: ' . $presigned_url);
                exit;
            }
        }

        // Fallback: Stream the file through PHP (slower but more compatible)
        $download_result = $storage->downloadFile($file->external_path, tempnam(sys_get_temp_dir(), 'ps_download_'));
        if ($download_result['success']) {
            $temp_file = $download_result['local_path'];

            // Serve the temporary file
            $alias = $this->getAlias($file);
            $this->serveFile($temp_file, $file->filename_original, $alias, $file);

            // Clean up temporary file
            unlink($temp_file);
            exit;
        }

        // If all else fails, return 404
        exit_with_error_code(404);
    }

    /**
     * Make a list of files ids to download on a compressed zip file
     * 
     * @return string
     */
    public function returnFilesIds($file_ids)
    {
		$check_level = ['System Administrator', 'Account Manager', 'Uploader', 'Client'];
		if (isset($file_ids)) {
			// do a permissions check for logged in user
			if (current_role_in($check_level)) {
				$file_list = [];
				foreach($file_ids as $key => $data) {
					$file_list[] = (int)$data['value']; //file-id must be int
				}
				ob_clean();
				flush();
				$return = implode( ',', $file_list );
            }
            else {
                return false;
            }
        }
        else {
            return false;
        }

        echo $return;
    }

    /**
     * Make and serve a zip file
     */
    public function downloadZip($file_ids)
    {
        $files_to_zip = array_map( 'intval', explode( ',', $file_ids ) );
        $added_files = 0;
        $log_details = [
            'files' => []
        ];
        
        /** Start adding the files to the zip */
        if ( count( $files_to_zip ) > 0 ) {
            $zip_file = tempnam(UPLOADS_TEMP_DIR, "zip_");
            $zip = new \ZipArchive();
            $zip->open($zip_file, \ZipArchive::OVERWRITE);

            foreach ($files_to_zip as $file_id) {
                $file = new \ProjectSend\Classes\Files($file_id);
                if (!$file->existsOnDisk()) {
                    continue;
                }
                if (!user_can_download_file(CURRENT_USER_ID, $file_id)) {
                    continue;
                }
                if ( $zip->addFile($file->full_path, $file->filename_unfiltered) ) {
                    $added_files++;
                    $download_result = record_new_download(CURRENT_USER_ID, $file_id);
                    // Skip file in zip if limit reached, but continue with other files
                    if (is_array($download_result) && !$download_result['allowed']) {
                        continue;
                    }
                    $log_details['files'][] = [
                        'id' => $file_id,
                        'filename' => $file->filename_original
                    ];
                }
            }        
            $zip_name = basename($zip->filename);
            $zip->close();

            if ($added_files > 0) {
                /** Record the action log */
                $this->logger->addEntry([
                    'action' => 9,
                    'owner_id' => CURRENT_USER_ID,
                    'affected_account_name' => CURRENT_USER_USERNAME,
                    'details' => $log_details,
                ]);
            
                if (file_exists($zip_file)) {
                    setCookie("download_started", "1", time() + 20, '/', "", false, false);

                    $save_as = 'files_'.generate_random_string().'.zip';
                    switch (get_option('download_method')) {
                        default:
                        case 'php':
                        case 'apache_xsendfile':
                        case 'litespeed':
                            $alias = null;
                        break;
                        case 'nginx_xaccel':
                            $alias = XACCEL_FILES_URL.'/temp/'.$zip_name;
                        break;
                    }
                    $this->serveFile($zip_file, $save_as, $alias);

                    //unlink($zip_file);
                    exit;
                }
            }
        }
    }

    /**
     * Decrypt file key if file is encrypted
     *
     * @param Files $file File object
     * @return string|false Binary file key or false if not encrypted or decryption fails
     */
    private function getDecryptedFileKey($file)
    {
        if (!$file->encrypted || empty($file->encryption_key_encrypted) || empty($file->encryption_iv)) {
            return false;
        }

        try {
            $encryption = new \ProjectSend\Classes\Encryption();
            return $encryption->decryptFileKey($file->encryption_key_encrypted, $file->encryption_iv);
        } catch (\Exception $e) {
            error_log('Failed to decrypt file key for file ID ' . $file->id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sends the file to the browser
     *
     * @return void
     */
    private function downloadFile($filename, $save_as, $file_id)
    {
        $file = new \ProjectSend\Classes\Files($file_id);
        $file_location = $file->full_path;

        if (current_role_in(['Client'])) {
            $log_action_number = 8;
        } else {
            $log_action_number = 7;
        }

        if (file_exists($file_location)) {
            /** Record the action log */
            $this->logger->addEntry([
                'action' => $log_action_number,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => (int)$file_id,
                'affected_file_name' => $filename,
                'affected_account' => CURRENT_USER_ID,
                'file_title_column' => true
            ]);
            
            $save_file_as = UPLOADED_FILES_DIR . DS . $save_as;

            $alias=$this->getAlias($file);
            $this->serveFile($file_location, $save_file_as, $alias, $file);
            exit;
        }
        else {
            exit_with_error_code(404);
        }
    }


    /**
     * @param object $file
     * @return string
     */
    public function getAlias($file)
    {
        switch (get_option('download_method')) {
            default:
            case 'php':
            case 'apache_xsendfile':
            case 'litespeed':
                return null;
            case 'nginx_xaccel':
                return $file->download_link_xaccel;
        }

    }

    /**
     * Serve file inline for embedding (PDFs, videos, audio)
     * Unlike serveFile(), this uses Content-Disposition: inline
     *
     * @param object $file File object
     * @return void
     */
    public function serveFileInline($file)
    {
        $file_location = $file->full_path;

        if (!file_exists($file_location)) {
            exit_with_error_code(404);
        }

        session_write_close();
        while (ob_get_level()) ob_end_clean();

        // Check if file is encrypted
        $file_key = false;
        if ($file->encrypted) {
            $file_key = $this->getDecryptedFileKey($file);
            if ($file_key === false) {
                error_log('Failed to decrypt file key for inline serving');
                exit_with_error_code(500);
            }
        }

        // Determine MIME type
        $mime_type = $file->mime_type ?: 'application/octet-stream';

        // For encrypted files, we need to handle differently
        if ($file_key !== false) {
            try {
                $encryption = new \ProjectSend\Classes\Encryption();

                header("Pragma: public");
                header("Expires: -1");
                header("Cache-Control: public, must-revalidate, post-check=0, pre-check=0");
                header('Content-Disposition: inline; filename="' . $file->filename_original . '"');
                header('Content-Type: ' . $mime_type);
                header('Accept-Ranges: none');

                set_time_limit(0);

                $success = $encryption->decryptFileStream($file_location, $file_key);

                if (!$success) {
                    error_log('Failed to decrypt and stream file inline');
                    exit_with_error_code(500);
                }

                exit;

            } catch (\Exception $e) {
                error_log('Decryption error during inline serving: ' . $e->getMessage());
                exit_with_error_code(500);
            }
        }

        // Unencrypted file - serve with range support for video/audio seeking
        $file_size = get_real_size($file_location);
        $fp = @fopen($file_location, "rb");

        if (!$fp) {
            exit_with_error_code(500);
        }

        header("Pragma: public");
        header("Expires: -1");
        header("Cache-Control: public, must-revalidate, post-check=0, pre-check=0");
        header('Content-Disposition: inline; filename="' . $file->filename_original . '"');
        header('Content-Type: ' . $mime_type);

        // Handle range requests for video/audio seeking
        if (isset($_SERVER['HTTP_RANGE'])) {
            list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if ($size_unit == 'bytes') {
                list($range, $extra_ranges) = explode(',', $range_orig, 2);
            } else {
                $range = '';
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                fclose($fp);
                exit;
            }
        } else {
            $range = '';
        }

        list($seek_start, $seek_end) = explode('-', $range, 2);
        $seek_end = (empty($seek_end)) ? ($file_size - 1) : min(abs(intval($seek_end)), ($file_size - 1));
        $seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)), 0);

        if ($seek_start > 0 || $seek_end < ($file_size - 1)) {
            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . $file_size);
            header('Content-Length: ' . ($seek_end - $seek_start + 1));
        } else {
            header("Content-Length: $file_size");
        }

        header('Accept-Ranges: bytes');

        set_time_limit(0);
        fseek($fp, $seek_start);

        while (!feof($fp)) {
            echo @fread($fp, 1024 * 2048);
            if (ob_get_level()) ob_flush();
            flush();
            if (connection_status() != 0) {
                @fclose($fp);
                exit;
            }
        }

        @fclose($fp);
        exit;
    }

    /**
     * Send file to the browser
     *
     * @param string $file_location absolute full path to the file on disk
     * @param string $save_as original filename
     * @param string $xaccel optional xaccel path
     * @param object $file optional file object (for encryption metadata)
     * @return void
     */
    public function serveFile($file_location, $save_as, $xaccel = null, $file = null)
    {
        if (file_exists($file_location)) {
            session_write_close();
            while (ob_get_level()) ob_end_clean();
            $save_as = sanitize_filename_for_download($save_as);

            // Check if file is encrypted
            $file_key = false;
            if ($file && $file->encrypted) {
                $file_key = $this->getDecryptedFileKey($file);
                if ($file_key === false) {
                    error_log('Failed to decrypt file key for download');
                    exit_with_error_code(500);
                }
            }

            switch (get_option('download_method')) {
                default:
                case 'php':
					$this->downloadPHP($file_location, $save_as, $file_key);
                break;
                case 'apache_xsendfile':
                case 'litespeed':
                case 'nginx_xaccel':
                    // For XSendFile, LiteSpeed, and X-Accel, we need to decrypt to a temp file first if encrypted
                    if ($file_key) {
                        $temp_file = tempnam(UPLOADS_TEMP_DIR, 'ps_decrypt_');
                        $encryption = new \ProjectSend\Classes\Encryption();
                        $decrypt_result = $encryption->decryptFileToPath($file_location, $temp_file, $file_key);

                        if (!$decrypt_result['success']) {
                            error_log('Failed to decrypt file for XSendFile/X-Accel/LiteSpeed: ' . $decrypt_result['error']);
                            exit_with_error_code(500);
                        }

                        $file_location = $temp_file;
                        // Update X-Accel path to point to the decrypted temp file
                        $xaccel = XACCEL_FILES_URL . '/temp/' . basename($temp_file);
                        // Note: temp file will be deleted after download by register_shutdown_function
                        register_shutdown_function(function() use ($temp_file) {
                            if (file_exists($temp_file)) {
                                unlink($temp_file);
                            }
                        });
                    }

                    if (get_option('download_method') == 'apache_xsendfile') {
                        header("X-Sendfile: $file_location");
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename='.basename($save_as));
                    } elseif (get_option('download_method') == 'litespeed') {
                        header("X-LiteSpeed-Location: $file_location");
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename='.basename($save_as));
                    } else {
                        header("X-Accel-Redirect: $xaccel");
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename='.basename($save_as));
                    }
                break;
            }

            return;
        }
        else {
            exit_with_error_code(404);
        }
    }
	
    /**
     * handles the filedownload in pure PHP
	 *
	 * script-origin: https://www.media-division.com/php-download-script-with-resume-option/
     *
     * @param string $file_location absolute full path to the file on disk
     * @param string $save_as original filename
     * @param string|false $file_key optional binary file key for decryption
     * @return void
     */
	public function downloadPHP($file_location, $save_as, $file_key = false)
	{
		$path_parts = pathinfo($file_location);
		$file_name = $path_parts['basename'];
		$file_ext = (!empty($path_parts['extension'])) ? $path_parts['extension'] : null;
        ini_set('display_errors', 'Off');
        ini_set('error_reporting', '0');
        ini_set('display_startup_errors', 'Off');

		// make sure the file exists
		if (is_file($file_location))
		{
            // If file is encrypted, use streaming decryption
            if ($file_key !== false) {
                try {
                    $encryption = new \ProjectSend\Classes\Encryption();

                    // Set headers for encrypted file download
                    header("Pragma: public");
                    header("Expires: -1");
                    header("Cache-Control: public, must-revalidate, post-check=0, pre-check=0");
                    header('Content-Disposition: attachment; filename='.basename($save_as));
                    header('Content-Type: application/octet-stream');

                    // Note: We cannot provide accurate Content-Length for encrypted files without decrypting first
                    // Also, range requests are not supported for encrypted files
                    header('Accept-Ranges: none');

                    set_time_limit(0);

                    // Stream decrypted content
                    $success = $encryption->decryptFileStream($file_location, $file_key);

                    if (!$success) {
                        error_log('Failed to decrypt and stream file');
                        exit_with_error_code(500);
                    }

                    exit;

                } catch (\Exception $e) {
                    error_log('Decryption error during download: ' . $e->getMessage());
                    exit_with_error_code(500);
                }
            }

            // Standard unencrypted file download with range support
			$file_size  = get_real_size($file_location);
			$file = @fopen($file_location,"rb");
			if ($file)
			{
				// set the headers, prevent caching
				header("Pragma: public");
				header("Expires: -1");
				header("Cache-Control: public, must-revalidate, post-check=0, pre-check=0");
                header('Content-Disposition: attachment; filename='.basename($save_as));
                header('Content-Type: application/octet-stream');

				//check if http_range is sent by browser (or download manager)
				if(isset($_SERVER['HTTP_RANGE']))
				{
					list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
					if ($size_unit == 'bytes')
					{
						//multiple ranges could be specified at the same time, but for simplicity only serve the first range
						//http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
						list($range, $extra_ranges) = explode(',', $range_orig, 2);
					}
					else
					{
						$range = '';
						header('HTTP/1.1 416 Requested Range Not Satisfiable');
						exit;
					}
				}
				else
				{
					$range = '';
				}

				//figure out download piece from range (if set)
                list($seek_start, $seek_end) = explode('-', $range, 2);

                //set start and end based on range (if set), else set defaults
                //also check for invalid ranges.
                $seek_end = (empty($seek_end)) ? ($file_size - 1) : min(abs(intval($seek_end)),($file_size - 1));
                $seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)),0);

			 
				//Only send partial content header if downloading a piece of the file (IE workaround)
				if ($seek_start > 0 || $seek_end < ($file_size - 1))
				{
					header('HTTP/1.1 206 Partial Content');
					header('Content-Range: bytes '.$seek_start.'-'.$seek_end.'/'.$file_size);
					header('Content-Length: '.($seek_end - $seek_start + 1));
				}
				else
				    header("Content-Length: $file_size");

				header('Accept-Ranges: bytes');
			
				set_time_limit(0);
				fseek($file, $seek_start);
				
				while(!feof($file)) 
				{
					echo @fread($file, 1024 * 2048);
					if (ob_get_level()) ob_flush();
					flush();
					if (connection_status()!=0) 
					{
						@fclose($file);
						exit;
					}			
				}
				
				// file save was a success
				@fclose($file);
				exit;
			}
			else 
			{
				// file couldn't be opened
				header("HTTP/1.0 500 Internal Server Error");
				exit;
			}
		}
		else
		{
			// file does not exist
			header("HTTP/1.0 404 Not Found");
			exit;
		}
		
	}
	
}
