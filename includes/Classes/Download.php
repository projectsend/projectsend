<?php
/**
 * Class that handles actions that do not return any UI.
 * 
 * @todo replace! This functions should go into routes and more specific classes
 *
 * @package		ProjectSend
 */
namespace ProjectSend\Classes;

use \PDO;
use \ZipArchive;

class Download
{
    private $dbh;
    private $logger;

    public function __construct(PDO $dbh = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;
    }

    public function download($file_id)
    {
        if (!$file_id || !defined('CURRENT_USER_LEVEL') || !userCanDownloadFile(CURRENT_USER_ID, $file_id)) {
            exitWithErrorCode(403);
        }

        $file = new \ProjectSend\Classes\Files();
        $file->get($file_id);
        recordNewDownload(CURRENT_USER_ID, $file->id);
        $this->downloadFile($file->filename_on_disk, $file->filename_unfiltered, $file->id);
    }

    /**
     * Make a list of files ids to download on a compressed zip file
     * 
     * @return string
     */
    public function returnFilesIds($file_ids)
    {
		$this->check_level = array(9,8,7,0);
		if (isset($file_ids)) {
			// do a permissions check for logged in user
			if (current_role_in($this->check_level)) {
				$file_list = array();
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
            $zip->open($zip_file, ZipArchive::OVERWRITE);

            foreach ($files_to_zip as $file_id) {
                $file = new \ProjectSend\Classes\Files();
                $file->get($file_id);
                if (!$file->existsOnDisk()) {
                    continue;
                }
                if (!userCanDownloadFile(CURRENT_USER_ID, $file_id)) {
                    continue;
                }
                if ( $zip->addFile($file->full_path, $file->filename_unfiltered) ) {
                    $added_files++;
                    recordNewDownload(CURRENT_USER_ID, $file_id);
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
                $record = $this->logger->addEntry([
                    'action' => 9,
                    'owner_id' => CURRENT_USER_ID,
                    'affected_account_name' => CURRENT_USER_USERNAME,
                    'details' => $log_details,
                ]);
            
                if (file_exists($zip_file)) {
                    setCookie("download_started", 1, time() + 20, '/', "", false, false);

                    $save_as = 'files_'.generateRandomString().'.zip';
                    switch (get_option('download_method')) {
                        default:
                        case 'php':
                        case 'apache_xsendfile':
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
     * Sends the file to the browser
     *
     * @return void
     */
    private function downloadFile($filename, $save_as, $file_id)
    {
        $file_location = UPLOADED_FILES_DIR . DS . $filename;

        switch (CURRENT_USER_LEVEL) {
            case 0:
                $log_action_number = 8;
            break;
            default:
            case 9:
            case 8:
            case 7:
                $log_action_number = 7;
            break;
        }

        if (file_exists($file_location)) {
            /** Record the action log */
            $record = $this->logger->addEntry([
                'action' => $log_action_number,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => (int)$file_id,
                'affected_file_name' => $filename,
                'affected_account' => CURRENT_USER_ID,
                'file_title_column' => true
            ]);
            
            $save_file_as = UPLOADED_FILES_DIR . DS . $save_as;

            $file = new Files;
            $file->get($file_id);
            $alias=$this->getAlias($file);
            $this->serveFile($file_location, $save_file_as, $alias);
            exit;
        }
        else {
            exitWithErrorCode(404);
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
                return null;
            case 'nginx_xaccel':
                return $file->download_link_xaccel;
        }

    }

    /**
     * Send file to the browser
     *
     * @param string $file_location absolute full path to the file on disk
     * @param string $save_as original filename
     * @return void
     */
    public function serveFile($file_location, $save_as, $xaccel = null)
    {
        if (file_exists($file_location)) {
            session_write_close();
            while (ob_get_level()) ob_end_clean();
            $save_as = sanitize_filename_for_download($save_as);

            switch (get_option('download_method')) {
                default:
                case 'php':
					$this->downloadPHP($file_location, $save_as);
                break;
                case 'apache_xsendfile':
                    header("X-Sendfile: $file_location");
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename='.basename($save_as));
                break;
                case 'nginx_xaccel':
                    header("X-Accel-Redirect: $xaccel");
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename='.basename($save_as));
                break;
            }

            return;
        }
        else {
            exitWithErrorCode(404);
        }
    }
	
    /**
     * handles the filedownload in pure PHP
	 * 
	 * script-origin: https://www.media-division.com/php-download-script-with-resume-option/
     *
     * @param string $filename absolute full path to the file on disk
     * @param string $save_as original filename
     * @return void
     */
	public function downloadPHP($file_location, $save_as)
	{
		$path_parts = pathinfo($file_location);
		$file_name  = $path_parts['basename'];
		$file_ext   = $path_parts['extension'];
		

		// make sure the file exists
		if (is_file($file_location))
		{
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
				$seek_end   = (empty($seek_end)) ? ($file_size - 1) : min(abs(intval($seek_end)),($file_size - 1));
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
					print(@fread($file, 1024*8));
					ob_flush();
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
