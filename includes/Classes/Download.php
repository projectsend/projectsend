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
            header('Location:' . PAGE_STATUS_CODE_403);
            exit;
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
					$file_list[] = int($data['value']); //file-id must be int
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
            header('Location:' . PAGE_STATUS_CODE_404);
            exit;
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

            switch (get_option('download_method')) {
                default:
                case 'php':
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename='.basename($save_as));
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Pragma: public');
                    header('Cache-Control: private',false);
                    header('Content-Length: ' . get_real_size($file_location));
                    header('Connection: close');
                    //readfile($this->file_location);

                    $context = stream_context_create();
                    $file = fopen($file_location, 'rb', false, $context);
                    while ( !feof( $file ) ) {
                        //usleep(1000000); //Reduce download speed
                        echo stream_get_contents($file, 2014);
                    }

                    fclose( $file );
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
            header('Location:' . PAGE_STATUS_CODE_404);
            exit;
        }
    }
}
