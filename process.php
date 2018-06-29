<?php
/**
 * Class that handles the log out and file download actions.
 *
 * @package		ProjectSend
 */
use ProjectSend\Auth;

$allowed_levels = array(9,8,7,0);
require_once('bootstrap.php');

$_SESSION['last_call']	= time();

if ( !empty( $_GET['do'] ) && $_GET['do'] == 'login' ) {
}
else {
	include_once ADMIN_TEMPLATES_DIR . DS . 'header.php';
}

class process {
	function __construct() {
		global $dbh;
		$this->dbh = $dbh;
        global $auth;
        $this->auth = $auth;

        $this->process();
    }

	function process() {
		switch ($_GET['do']) {
			case 'login':
				$this->login();
				break;
			case 'logout':
				$this->logout();
				break;
			case 'download':
				$this->download_file();
				break;
			case 'download_zip':
				$this->download_zip();
				break;
			default:
                header('Location: '.BASE_URI);
                exit;
				break;
		}
	}

	private function login() {
        $this->selected_form_lang = (!empty( $_GET['language'] ) ) ? $_GET['language'] : SITE_LANG;
        $this->auth->login($_GET['username'], $_GET['password'], $this->selected_form_lang);
	}

	private function logout() {
        $this->auth->logout();
	}

	private function download_file() {
		$this->check_level = array(9,8,7,0);
		if (isset($_GET['id'])) {
			/** Do a permissions check for logged in user */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {

					/**
					 * Get the file name
					 */
					$this->statement = $this->dbh->prepare("SELECT url, original_url, expires, expiry_date FROM " . TABLE_FILES . " WHERE id=:id");
					$this->statement->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
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
							 */
							$this->get_groups		= new ProjectSend\MembersActions();
							$this->get_arguments	= array(
															'client_id'	=> CURRENT_USER_ID,
															'return'	=> 'list',
														);
							$this->found_groups	= $this->get_groups->client_get_groups($this->get_arguments);

							/**
							 * Get assignments
							 */
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
							$this->params[':file_id'] = (int)$_GET['id'];

							$this->files = $this->dbh->prepare( $this->fq );
							$this->files->execute( $this->params );

							if ( $this->files->rowCount() > 0 ) {
								$this->can_download = true;
							}

							/** Continue */
							if ($this->can_download == true) {
								/**
								 * The owner ID is generated here to prevent false results
								 * from a modified GET url.
								 */
								$log_action = 8;
								$log_action_owner_id = CURRENT_USER_ID;
							}
						}
					}
					else {
						$this->can_download = true;
						$log_action = 7;
						$log_action_owner_id = CURRENT_USER_ID;
					}

					if ($this->can_download == true) {
						/**
						 * Add +1 to the download count
						 */
						$this->statement = $this->dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (user_id , file_id, remote_ip, remote_host) VALUES (:user_id, :file_id, :remote_ip, :remote_host)");
						$this->statement->bindValue(':user_id', CURRENT_USER_ID, PDO::PARAM_INT);
						$this->statement->bindParam(':file_id', $_GET['id'], PDO::PARAM_INT);
						$this->statement->bindParam(':remote_ip', $_SERVER['REMOTE_ADDR']);
						$this->statement->bindParam(':remote_host', $_SERVER['REMOTE_HOST']);
						$this->statement->execute();

						/** Record the action log */
						$new_log_action = new ProjectSend\LogActions();
						$log_action_args = array(
												'action'				=> $log_action,
												'owner_id'				=> $log_action_owner_id,
												'affected_file'			=> (int)$_GET['id'],
												'affected_file_name'	=> $this->filename_find,
												'affected_account'		=> CURRENT_USER_ID,
												'affected_account_name'	=> CURRENT_USER_USERNAME,
												'get_user_real_name'	=> true,
												'get_file_real_name'	=> true
											);
						$new_record_action = $new_log_action->log_action_save($log_action_args);
						$this->real_file = UPLOADED_FILES_FOLDER.$this->filename_find;
						$this->save_file = UPLOADED_FILES_FOLDER.$this->filename_save;
						if (file_exists($this->real_file)) {
							session_write_close();
							while (ob_get_level()) ob_end_clean();
							header('Content-Type: application/octet-stream');
							header('Content-Disposition: attachment; filename='.basename($this->save_file));
							header('Expires: 0');
							header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
							header('Pragma: public');
							header('Cache-Control: private',false);
							header('Content-Length: ' . get_real_size($this->real_file));
							header('Connection: close');
							//readfile($this->real_file);

							$context = stream_context_create();
							$file = fopen($this->real_file, 'rb', false, $context);
							while( !feof( $file ) ) {
								//usleep(1000000); //Reduce download speed
								echo stream_get_contents($file, 2014);
							}

							fclose( $file );
							exit;
						}
						else {
							header("HTTP/1.1 404 Not Found");
							?>
								<div class="col-xs-12">
									<div class="file_404">
										<h2><?php _e('File not found','cftp_admin'); ?></h2>
									</div>
								</div>
							<?php
							exit;
						}
					}
			}
		}
	}

	private function download_zip() {
		$this->check_level = array(9,8,7,0);
		if (isset($_GET['files'])) {
			// do a permissions check for logged in user
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$file_list = array();
				$requested_files = $_GET['files'];
				foreach($requested_files as $key => $data) {
					$file_list[] = $data['value'];
				}
				ob_clean();
				flush();
				echo implode( ',', $file_list );
			}
		}
	}
}

$process = new process;
