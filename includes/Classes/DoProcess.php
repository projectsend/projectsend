<?php
/**
 * Class that handles actions that do not return any UI.
 * 
 * @todo replace! This functions should go into routes and more specific classes
 *
 * @package		ProjectSend
 */
namespace ProjectSend\Classes;

use \ProjectSend\Classes\MembersActions;
use \PDO;
use \ZipArchive;

class DoProcess
{
    private $dbh;
    private $logger;

    private $username;
    private $password;
    private $language;

    public function __construct(PDO $dbh = null, Auth $auth = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        if (empty($auth)) {
            $this->auth = new \ProjectSend\Classes\Auth($this->dbh);
        }
    }

    public function login($username, $password, $language = SITE_LANG)
    {
        return $this->auth->login($username, $password, $language);
	}

    public function socialLogin($provider)
    {
        $this->auth->socialLogin($provider);
	}

    public function logout()
    {
        return $this->auth->logout();
    }
    
    public function login_ldap($email, $password, $language = SITE_LANG)
    {
        return $this->auth->login_ldap($email, $password, $language);
    }


    /**
     * @todo From here on, move everything into a Download class
     */

    
    public function download($file_id)
    {
        if ( !$file_id )
            return false;

        /** Do a permissions check for logged in user */
		$this->check_level = array(9,8,7,0);
        if (isset($this->check_level) && current_role_in($this->check_level)) {

            /** Get the file name */
            $file = new \ProjectSend\Classes\Files();
            $file->get($file_id);

            $can_download = false;

            if (CURRENT_USER_LEVEL == 0) {
                if ($file->user_id == CURRENT_USER_ID) {
                    $can_download = true;
                    $log_action = 8;
                } else {
                    if ($file->expires == '0' || $file->expired == false) {
                        /**
                         * Does the client have permission to download the file?
                         * First, get the list of different groups the client belongs to.
                         * @todo move into a method for an yet to create File class, for example can_download_this_file($client_id)
                         */
                        $get_groups		= new \ProjectSend\Classes\MembersActions();
                        $get_arguments	= array(
                                                        'client_id'	=> CURRENT_USER_ID,
                                                        'return'	=> 'list',
                                                    );
                        $found_groups	= $get_groups->client_get_groups($get_arguments);
    
                        /** Get assignments */
                        $params = array(
                                            ':client_id'	=> CURRENT_USER_ID,
                                        );
                        $fq = "SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE (client_id=:client_id";
                        // Add found groups, if any
                        if ( !empty( $found_groups ) ) {
                            $fq .= ' OR FIND_IN_SET(group_id, :groups)';
                            $params[':groups'] = $found_groups;
                        }
                        // Continue assembling the query
                        $fq .= ') AND file_id=:file_id AND hidden = "0"';
                        $params[':file_id'] = (int)$file->id;
    
                        $files = $this->dbh->prepare( $fq );
                        $files->execute( $params );
    
                        /** Continue */
                        if ( $files->rowCount() > 0 ) {
                            $can_download = true;
                            $log_action = 8;
                        }
                    }
                }
            }
            else {
                $can_download = true;
                $log_action = 7;
            }

            if ($can_download == true) {
                /**
                 * Add +1 to the download count
                 * @todo move into a method for an yet to create File class, for example add_to_download_count($file, $amount = 1)
                 */
                $statement = $this->dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (user_id , file_id, remote_ip, remote_host) VALUES (:user_id, :file_id, :remote_ip, :remote_host)");
                $statement->bindValue(':user_id', CURRENT_USER_ID, PDO::PARAM_INT);
                $statement->bindParam(':file_id', $file->id, PDO::PARAM_INT);
                $statement->bindParam(':remote_ip', get_client_ip());
                $statement->bindParam(':remote_host', $_SERVER['REMOTE_HOST']);
                $statement->execute();

                $this->downloadFile($file->filename_on_disk, $file->filename_unfiltered, $file->id, $log_action);
            }
            else {
                header('Location:' . PAGE_STATUS_CODE_403);
                exit;
            }
        }
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
			if (isset($this->check_level) && current_role_in($this->check_level)) {
				$file_list = array();
				foreach($file_ids as $key => $data) {
					$file_list[] = $data['value'];
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
        
        foreach ($files_to_zip as $file_id) {
            $file = new \ProjectSend\Classes\Files();
            $file->get($file_id);
            if (!$file->existsOnDisk()) {
                unset( $files_to_zip[$file_id] );
            }
        }
        
        $added_files = 0;
        
        /**
         * Get the list of different groups the client belongs to.
         */
        $get_groups		= new \ProjectSend\Classes\MembersActions();
        $get_arguments	= array(
                                        'client_id'	=> CURRENT_USER_ID,
                                        'return'	=> 'list',
                                    );
        $found_groups	= $get_groups->client_get_groups($get_arguments);

        $allowed_to_zip = []; // Files allowed to be downloaded

        foreach ($files_to_zip as $file_id) {
            $file = new \ProjectSend\Classes\Files();
            $file->get($file_id);
        
            /**
             * Check download permission
             */
            if (CURRENT_USER_LEVEL == 0) {
                if ($file->user_id == CURRENT_USER_ID) {
                    $allowed_to_zip[$file->id] = $file;
                }
                else {
                    if ($file->expires == '0' || $file->expired == false) {
                        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE (client_id = :client_id OR FIND_IN_SET(group_id, :groups)) AND file_id = :file_id AND hidden = '0'");
                        $statement->bindValue(':client_id', CURRENT_USER_ID, PDO::PARAM_INT);
                        $statement->bindParam(':groups', $found_groups);
                        $statement->bindParam(':file_id', $file->id, PDO::PARAM_INT);
                        $statement->execute();
                        $statement->setFetchMode(PDO::FETCH_ASSOC);
                        $row = $statement->fetch();
            
                        if ($row) {
                            /** Add the file */
                            $allowed_to_zip[$file->id] = $file;
                        }
                    }
                }
            }
            else {
                $allowed_to_zip[$file->id] = $file;
            }
        }
        
        /** Start adding the files to the zip */
        if ( count( $allowed_to_zip ) > 0 ) {
            $zip_file = tempnam("tmp", "zip");
            $zip = new \ZipArchive();
            $zip->open($zip_file, ZipArchive::OVERWRITE);

            foreach ($allowed_to_zip as $file_id => $file_data) {
                if ( $zip->addFile($file_data->full_path, $file_data->filename_unfiltered) ) {
                    $added_files++;

                    /**
                     * Add +1 to the download count
                     * @todo move into a method for an yet to create File class, for example add_to_download_count($file, $amount = 1)
                     */
                    $statement = $this->dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (user_id , file_id, remote_ip, remote_host)"
                                                ." VALUES (:user_id, :file_id, :remote_ip, :remote_host)");
                    $statement->bindValue(':user_id', CURRENT_USER_ID, PDO::PARAM_INT);
                    $statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
                    $statement->bindParam(':remote_ip', $_SERVER['REMOTE_ADDR']);
                    $statement->bindParam(':remote_host', $_SERVER['REMOTE_HOST']);
                    $statement->execute();

                    /** @todo log this specific file download */
                }
            }
        
            $zip->close();
        
            if ($added_files > 0) {
                /** Record the action log */
                $record = $this->logger->addEntry([
                    'action' => 9,
                    'owner_id' => CURRENT_USER_ID,
                    'affected_account_name' => CURRENT_USER_USERNAME
                ]);
            
                if (file_exists($zip_file)) {
                    setCookie("download_started", 1, time() + 20, '/', "", false, false);

                    $save_as = 'files_'.generateRandomString().'.zip';
                    $this->serveFile($zip_file, $save_as);

                    unlink($zip_file);
                }
            }
        }
    }

    /**
     * Sends the file to the browser
     *
     * @return void
     */
    private function downloadFile($filename, $save_as, $file_id, $log_action_number)
    {
        $this->file_location = UPLOADED_FILES_DIR . DS . $filename;

        if (file_exists($this->file_location)) {
            /** Record the action log */
            $record = $this->logger->addEntry([
                'action' => $log_action_number,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => (int)$file_id,
                'affected_file_name' => $filename,
                'affected_account' => CURRENT_USER_ID,
                'file_title_column' => true
            ]);
            
            $this->save_file_as = UPLOADED_FILES_DIR . DS . $save_as;

            $this->serveFile($this->file_location, $this->save_file_as);
            exit;
        }
        else {
            header('Location:' . PAGE_STATUS_CODE_404);
            exit;
        }
    }

    /**
     * Send file to the browser
     *
     * @param string $filename absolute full path to the file on disk
     * @param string $save_as original filename
     * @return void
     */
    private function serveFile($filename, $save_as)
    {
        if (file_exists($filename)) {
            session_write_close();
            while (ob_get_level()) ob_end_clean();

            if (defined('XSENDFILE_ENABLE') && XSENDFILE_ENABLE == 1) {
                header("X-Sendfile: $filename");
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename='.basename($save_as));
                exit;
            } else {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename='.basename($save_as));
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Cache-Control: private',false);
                header('Content-Length: ' . get_real_size($filename));
                header('Connection: close');
                //readfile($this->file_location);

                $this->context = stream_context_create();
                $this->file = fopen($filename, 'rb', false, $this->context);
                while ( !feof( $this->file ) ) {
                    //usleep(1000000); //Reduce download speed
                    echo stream_get_contents($this->file, 2014);
                }

                fclose( $this->file );
                exit;
            }
        }
        else {
            header('Location:' . PAGE_STATUS_CODE_404);
            exit;
        }
    }
}
