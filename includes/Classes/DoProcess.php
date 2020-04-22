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
        $this->try_login = $this->auth->login($username, $password, $language);

        return $this->try_login;
	}

    public function socialLogin($provider)
    {
        $this->try_login = $this->auth->socialLogin($provider);
	}

    public function logout()
    {
        return $this->auth->logout();
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
            $this->statement = $this->dbh->prepare("SELECT url, original_url, expires, expiry_date FROM " . TABLE_FILES . " WHERE id=:id");
            $this->statement->bindParam(':id', $file_id, PDO::PARAM_INT);
            $this->statement->execute();
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
            $this->row				= $this->statement->fetch();
            $this->filename_find	= $this->row['url'];
            $this->filename_save	= (!empty( $this->row['original_url'] ) ) ? $this->row['original_url'] : $this->row['url'];
            $this->expires			= $this->row['expires'];
            $this->expiry_date		= $this->row['expiry_date'];

            $this->expired			= false;
            if ($this->expires == '1' && time() > strtotime($this->expiry_date)) {
                $this->expired		= true;
            }

            $this->can_download = false;

            if (CURRENT_USER_LEVEL == 0) {
                if ($this->expires == '0' || $this->expired == false) {
                    /**
                     * Does the client have permission to download the file?
                     * First, get the list of different groups the client belongs to.
                     * @todo move into a method for an yet to create File class, for example can_download_this_file($client_id)
                     */
                    $this->get_groups		= new \ProjectSend\Classes\MembersActions();
                    $this->get_arguments	= array(
                                                    'client_id'	=> CURRENT_USER_ID,
                                                    'return'	=> 'list',
                                                );
                    $this->found_groups	= $this->get_groups->client_get_groups($this->get_arguments);

                    /** Get assignments */
                    $this->params = array(
                                        ':client_id'	=> CURRENT_USER_ID,
                                    );
                    $this->fq = "SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE (client_id=:client_id";
                    // Add found groups, if any
                    if ( !empty( $this->found_groups ) ) {
                        $this->fq .= ' OR FIND_IN_SET(group_id, :groups)';
                        $this->params[':groups'] = $this->found_groups;
                    }
                    // Continue assembling the query
                    $this->fq .= ') AND file_id=:file_id AND hidden = "0"';
                    $this->params[':file_id'] = (int)$file_id;

                    $this->files = $this->dbh->prepare( $this->fq );
                    $this->files->execute( $this->params );

                    /** Continue */
                    if ( $this->files->rowCount() > 0 ) {
                        $this->can_download = true;
                        $this->log_action = 8;
                    }
                }
            }
            else {
                $this->can_download = true;
                $this->log_action = 7;
            }

            if ($this->can_download == true) {
                /**
                 * Add +1 to the download count
                 * @todo move into a method for an yet to create File class, for example add_to_download_count($file, $amount = 1)
                 */
                $this->statement = $this->dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (user_id , file_id, remote_ip, remote_host) VALUES (:user_id, :file_id, :remote_ip, :remote_host)");
                $this->statement->bindValue(':user_id', CURRENT_USER_ID, PDO::PARAM_INT);
                $this->statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
                $this->statement->bindParam(':remote_ip', get_client_ip());
                $this->statement->bindParam(':remote_host', $_SERVER['REMOTE_HOST']);
                $this->statement->execute();

                $this->downloadFile($this->filename_find, $this->filename_save, $file_id, $this->log_action);
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
        $this->files_to_zip = array_map( 'intval', explode( ',', $file_ids ) );
        
        foreach ($this->files_to_zip as $this->idx => $this->file) {
            $this->file = UPLOADED_FILES_DIR . DS . $this->file;
            if ( !( realpath( $this->file ) && substr( realpath( $this->file ),0, strlen( UPLOADED_FILES_DIR ) ) ) === UPLOADED_FILES_DIR ){
               unset( $this->files_to_zip[$this->idx] );
            }
        }
        
        $this->added_files = 0;
        
        /**
         * Get the list of different groups the client belongs to.
         */
        $this->get_groups		= new \ProjectSend\Classes\MembersActions();
        $this->get_arguments	= array(
                                        'client_id'	=> CURRENT_USER_ID,
                                        'return'	=> 'list',
                                    );
        $this->found_groups	= $this->get_groups->client_get_groups($this->get_arguments);

        $this->allowed_to_zip = []; // Files allowed to be downloaded

        foreach ($this->files_to_zip as $this->file_to_zip) {
            $this->statement = $this->dbh->prepare("SELECT id, url, original_url, expires, expiry_date FROM " . TABLE_FILES . " WHERE id = :file");
            $this->statement->bindParam(':file', $this->file_to_zip, PDO::PARAM_INT);
            $this->statement->execute();
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
            $this->row = $this->statement->fetch();
        
            $this->this_file_id			    = $this->row['id'];
            $this->this_file_on_disk		= $this->row['url'];
            $this->this_file_save_as		= (!empty( $this->row['original_url'] ) ) ? $this->row['original_url'] : $this->row['url'];
            $this->this_file_expires		= $this->row['expires'];
            $this->this_file_expiry_date	= $this->row['expiry_date'];
        
            $this->this_file_expired		= false;
            if ($this->this_file_expires == '1' && time() > strtotime($this->this_file_expiry_date)) {
                $this->this_file_expired	= true;
            }
        
            /**
             * Check download permission
             */
            if (CURRENT_USER_LEVEL == 0) {
                if ($this->this_file_expires == '0' || $this->this_file_expired == false) {
                    $this->statement = $this->dbh->prepare("SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE (client_id = :client_id OR FIND_IN_SET(group_id, :groups)) AND file_id = :file_id AND hidden = '0'");
                    $this->statement->bindValue(':client_id', CURRENT_USER_ID, PDO::PARAM_INT);
                    $this->statement->bindParam(':groups', $this->found_groups);
                    $this->statement->bindParam(':file_id', $this->this_file_id, PDO::PARAM_INT);
                    $this->statement->execute();
                    $this->statement->setFetchMode(PDO::FETCH_ASSOC);
                    $this->row = $this->statement->fetch();
        
                    if ( $this->row ) {
                        /** Add the file */
                        $this->allowed_to_zip[$this->row['file_id']] = array(
                                                                'on_disk'	=> $this->this_file_on_disk,
                                                                'save_as'	=> $this->this_file_save_as
                                                            );
                    }
                }
            }
            else {
                $this->allowed_to_zip[] = array(
                                        'on_disk'	=> $this->this_file_on_disk,
                                        'save_as'	=> $this->this_file_save_as
                                    );
            }
        
        }
        
        /** Start adding the files to the zip */
        if ( count( $this->allowed_to_zip ) > 0 ) {
            $this->zip_file = tempnam("tmp", "zip");
            $this->zip = new \ZipArchive();
            $this->zip->open($this->zip_file, ZipArchive::OVERWRITE);

            //echo $this->zip_file;print_array($this->allowed_to_zip); die();

            foreach ($this->allowed_to_zip as $this->allowed_file_id => $this->allowed_file_info) {
                if ( $this->zip->addFile(UPLOADED_FILES_DIR.DS.$this->allowed_file_info['on_disk'],$this->allowed_file_info['save_as']) ) {
                    $this->added_files++;

                    /**
                     * Add +1 to the download count
                     * @todo move into a method for an yet to create File class, for example add_to_download_count($file, $amount = 1)
                     */
                    $this->statement = $this->dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (user_id , file_id, remote_ip, remote_host)"
                                                ." VALUES (:user_id, :file_id, :remote_ip, :remote_host)");
                    $this->statement->bindValue(':user_id', CURRENT_USER_ID, PDO::PARAM_INT);
                    $this->statement->bindParam(':file_id', $this->this_file_id, PDO::PARAM_INT);
                    $this->statement->bindParam(':remote_ip', $_SERVER['REMOTE_ADDR']);
                    $this->statement->bindParam(':remote_host', $_SERVER['REMOTE_HOST']);
                    $this->statement->execute();

                    /** @todo log this specific file download */
                }
            }
        
            $this->zip->close();
        
            if ($this->added_files > 0) {
                /** Record the action log */
                $record = $this->logger->addEntry([
                    'action' => 9,
                    'owner_id' => CURRENT_USER_ID,
                    'affected_account_name' => CURRENT_USER_USERNAME
                ]);
            
                if (file_exists($this->zip_file)) {
                    setCookie("download_started", 1, time() + 20, '/', "", false, false);

                    $this->save_as = 'files_'.generateRandomString().'.zip';
                    $this->serveFile($this->zip_file, $this->save_as);

                    unlink($this->zip_file);
                }
            }
        }
    }

    /**
     * Sends the file to the browser
     * @todo move into a Download class
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
        }
        else {
            header('Location:' . PAGE_STATUS_CODE_404);
            exit;
        }
    }
}
