<?php
/**
 * Class that handles the log out and file download actions.
 *
 * @package		ProjectSend
 */
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

$_SESSION['last_call']	= time();

$header = 'header.php';

if ( !empty( $_GET['do'] ) && $_GET['do'] == 'login' ) {
}
else {
	require_once($header);
}

class process {
	function __construct() {
		global $dbh;
		$this->dbh = $dbh;
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
			case 'zip_download':
				$this->download_zip();
				break;
			default:
				header('Location: '.BASE_URI);
				break;
		}
	}
	
	private function login() {
		global $hasher;
		$this->sysuser_password		= $_GET['password'];
		$this->selected_form_lang	= (!empty( $_GET['language'] ) ) ? $_GET['language'] : SITE_LANG;
	
		/** Look up the system users table to see if the entered username exists */
		$this->statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE user= :username OR email= :email");
		$this->statement->execute(
						array(
							':username'	=> $_GET['username'],
							':email'	=> $_GET['username'],
						)
					);
		$this->count_user = $this->statement->rowCount();
		if ($this->count_user > 0){
			/** If the username was found on the users table */
			$this->statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $this->row = $this->statement->fetch() ) {
				$this->sysuser_username		= $this->row['user'];
				$this->db_pass			= $this->row['password'];
				$this->user_level		= $this->row["level"];
				$this->active_status		= $this->row['active'];
				$this->logged_id		= $this->row['id'];
				$this->global_name		= $this->row['name'];
				$this->start_observation_window = $this->row['start_observation_window'];
				$this->invalid_auth_attempts	= $this->row['invalid_auth_attempts'];
			}
			$this->check_password = $hasher->CheckPassword($this->sysuser_password, $this->db_pass);
			if ($this->check_password) {
			//if ($db_pass == $sysuser_password) {
				if ($this->active_status != '0') {
					/** Set SESSION values */
					$_SESSION['loggedin']	= $this->sysuser_username;
					$_SESSION['userlevel']	= $this->user_level;
					$_SESSION['lang']		= $this->selected_form_lang;

					/**
					 * Language cookie
					 * TODO: Implement.
					 * Must decide how to refresh language in the form when the user
					 * changes the language <select> field.
					 * By using a cookie and not refreshing here, the user is
					 * stuck in a language and must use it to recover password or
					 * create account, since the lang cookie is only at login now.
					 */
					//setcookie('projectsend_language', $selected_form_lang, time() + (86400 * 30), '/');

					if ($this->user_level != '0') {
						$this->access_string	= 'admin';
						$_SESSION['access']		= $this->access_string;
					}
					else {
						$this->access_string	= $this->sysuser_username;
						$_SESSION['access']		= $this->sysuser_username;
					}

					/** If "remember me" checkbox is on, set the cookie */
					if (!empty($_POST['login_form_remember'])) {
						/*
						setcookie("loggedin",$sysuser_username,time()+COOKIE_EXP_TIME);
						setcookie("password",$sysuser_password,time()+COOKIE_EXP_TIME);
						setcookie("access",$access_string,time()+COOKIE_EXP_TIME);
						setcookie("userlevel",$user_level,time()+COOKIE_EXP_TIME);
						*/
						setcookie("rememberwho",$sysuser_username,time()+COOKIE_EXP_TIME);
					}
					/** Record the action log */
					$this->new_log_action = new LogActions();
					$this->log_action_args = array(
											'action' => 1,
											'owner_id' => $this->logged_id,
											'owner_user' => $this->global_name,
											'affected_account_name' => $this->global_name
										);
					$this->new_record_action = $this->new_log_action->log_action_save($this->log_action_args);


					$results = array(
									'status'	=> 'success',
									'message'	=> system_message('ok','Login success. Redirecting...','login_response'),
								);
					if ($this->user_level == '0') {
						$results['location']	= BASE_URI."my_files/";
					}
					else {
						$results['location']	= BASE_URI."home.php";
					}

					/** Using an external form */
					if ( !empty( $_GET['external'] ) && $_GET['external'] == '1' && empty( $_GET['ajax'] ) ) {
						/** Success */
						if ( $results['status'] == 'success' ) {
							header('Location: ' . $results['location']);
							exit;
						}
					}

					echo json_encode($results);
					exit;
				}
				else {
					// active_status
					$this->errorstate = 'inactive_client';
				}
			}
			else {
				//
				// account lockout logic
				//

				// set the correct limits based on the user_level of the account
				if ($this->user_level != '0') {
					$this->max_invalid_auth_attempts = USER_MAX_INVALID_AUTH_ATTEMPTS;
					$this->observation_window = USER_OBSERVATION_WINDOW;
				} else {
					$this->max_invalid_auth_attempts = CLIENT_MAX_INVALID_AUTH_ATTEMPTS;
					$this->observation_window = CLIENT_OBSERVATION_WINDOW;
				}

				// only bother where the account is active and lockout functionality is enabled (i.e. _MAX_INVALID_AUTH_ATTEMPTS > 0)
				if ($this->active_status != '0' && $this->max_invalid_auth_attempts != '0') {

					if ($this->invalid_auth_attempts < $this->max_invalid_auth_attempts) {

						if (time() <= $this->start_observation_window + $this->observation_window * 60) {
							// this invalid login is in an existing observation_window

							// update user table incrementing invalid_auth_attempts by one if current value < MAX_INVALID_AUTH_ATTEMPTS
							if ($this->invalid_auth_attempts < MAX_INVALID_AUTH_ATTEMPTS) {
                                				$this->sql = "UPDATE " . TABLE_USERS . " SET invalid_auth_attempts = :invalid_auth_attempts WHERE id = :logged_id";
                                				$this->statement = $this->dbh->prepare($this->sql);
                                				$this->statement->bindValue(':invalid_auth_attempts', $this->invalid_auth_attempts + 1, PDO::PARAM_INT);
      								$this->statement->bindParam(':logged_id', $this->logged_id, PDO::PARAM_INT);
                                				$this->statement->execute();
							}

							// requery to refresh $this
							$this->refresh_account_status($this->logged_id);

							if ($this->invalid_auth_attempts >= $this->max_invalid_auth_attempts) {
								
								// maximum attempts in window exceded so disable user account and refresh status
								$this->disable_account($this->logged_id);
								$this->refresh_account_status($this->logged_id);
					
								/** Record the lockout action */
								$action = ($this->user_level != '0' ? 43 : 42);

								$this->new_log_action = new LogActions();
								$this->log_action_args = array(
											'action' => $action,
											'owner_id' => $this->logged_id,
											'owner_user' => $this->global_name,
											'affected_account_name' => $this->global_name
										);
								$this->new_record_action = $this->new_log_action->log_action_save($this->log_action_args);
							}
						}
						else {
							// this invalid login is in a new observation_window
							
                                			$this->sql = "UPDATE " . TABLE_USERS . " SET invalid_auth_attempts = :invalid_auth_attempts, start_observation_window = :start_observation_window WHERE id = :logged_id";
                                			$this->statement = $this->dbh->prepare($this->sql);
                                			$this->statement->bindValue(':invalid_auth_attempts', 1, PDO::PARAM_INT);
                                			$this->statement->bindValue(':start_observation_window', time(), PDO::PARAM_INT);
      							$this->statement->bindParam(':logged_id', $this->logged_id, PDO::PARAM_INT);
                                			$this->statement->execute();

							// requery to refresh $this
							$this->refresh_account_status($this->logged_id);
						}
					} 
					else {
						// this could happen if _MAX_INVALID_AUTH_ATTEMPTS is reduced

						// maximum attempts exceded so disable user account and refresh status
						$this->disable_account($this->logged_id);
						$this->refresh_account_status($this->logged_id);

						/** Record the lockout action */
						$action = ($this->user_level != '0' ? 43 : 42);

						$this->new_log_action = new LogActions();
						$this->log_action_args = array(
									'action' => $action,
									'owner_id' => $this->logged_id,
									'owner_user' => $this->global_name,
									'affected_account_name' => $this->global_name
								);
						$this->new_record_action = $this->new_log_action->log_action_save($this->log_action_args);
					}
					
					// user hasn't authenticated correctly so don't bleed any state information about the account
					$this->errorstate = 'invalid_credentials';
				} 
				else {
					// user hasn't authenticated correctly so don't bleed any state information about the account
					$this->errorstate = 'invalid_credentials';
				}
			}
		}
		else {
			// count_user = 'wrong_username';
			$this->errorstate = 'invalid_credentials';
		}

		if (isset($this->errorstate)) {
			switch ($this->errorstate) {
				case 'invalid_credentials':
					$this->login_err_message = __("The supplied credentials are not valid.",'cftp_admin');
					break;
				case 'wrong_username':
					$this->login_err_message = __("The supplied username doesn't exist.",'cftp_admin');
					break;
				case 'wrong_password':
					$this->login_err_message = __("The supplied password is incorrect.",'cftp_admin');
					break;
				case 'inactive_client':
					$this->login_err_message = __("This account is not active.",'cftp_admin');
					if (CLIENTS_AUTO_APPROVE == 0) {
						$this->login_err_message .= ' '.__("If you just registered, please wait until a system administrator approves your account.",'cftp_admin');
					}
					break;
				case 'no_self_registration':
					$this->login_err_message = __('Client self registration is not allowed. If you need an account, please contact a system administrator.','cftp_admin');
					break;
				case 'no_account':
					$this->login_err_message = __('Sign-in with Google cannot be used to create new accounts at this time.','cftp_admin');
					break;
				case 'access_denied':
					$this->login_err_message = __('You must approve the requested permissions to sign in with Google.','cftp_admin');
					break;
			}
		}

		$results = array(
						'status'	=> 'error',
						'message'	=> system_message('error',$this->login_err_message,'login_error'),
					);

		/** Using an external form */
		if ( !empty( $_GET['external'] ) && $_GET['external'] == '1' && empty( $_GET['ajax'] ) ) {
			/** Error */
			if ( $results['status'] == 'error' ) {
				header('Location: ' . BASE_URI . '?error=1');
			}
			exit;
		}

		echo json_encode($results);
		exit;
	}

	private function refresh_account_status($id) {
		
		$this->statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id = :logged_id");
		$this->statement->bindParam(':logged_id', $id, PDO::PARAM_INT);
		$this->statement->execute();
		$this->statement->setFetchMode(PDO::FETCH_ASSOC);
	
		while ( $this->row = $this->statement->fetch() ) {
			$this->active_status		= $this->row['active'];
			$this->start_observation_window = $this->row['start_observation_window'];
			$this->invalid_auth_attempts	= $this->row['invalid_auth_attempts'];
		}
	}

	private function disable_account($id) {
		$this->sql = "UPDATE " . TABLE_USERS . " SET active = :active_status WHERE id = :logged_id";
		$this->statement = $this->dbh->prepare($this->sql);
		$this->statement->bindValue(':active_status', ACCOUNT_INACTIVE, PDO::PARAM_INT);
		$this->statement->bindParam(':logged_id', $id, PDO::PARAM_INT);
		$this->statement->execute();
	}	

	private function logout() {
		header("Cache-control: private");
		unset($_SESSION['loggedin']);
		unset($_SESSION['access']);
		unset($_SESSION['userlevel']);
		unset($_SESSION['lang']);
		unset($_SESSION['last_call']);
		session_destroy();

		/** If there is a cookie, unset it */
		setcookie("loggedin","",time()-COOKIE_EXP_TIME);
		setcookie("password","",time()-COOKIE_EXP_TIME);
		setcookie("access","",time()-COOKIE_EXP_TIME);
		setcookie("userlevel","",time()-COOKIE_EXP_TIME);

		/*
		$language_cookie = 'projectsend_language';
		setcookie ($language_cookie, "", 1);
		setcookie ($language_cookie, false);
		unset($_COOKIE[$language_cookie]);
		*/

		/** Record the action log */
		$new_log_action = new LogActions();
		$log_action_args = array(
								'action'	=> 31,
								'owner_id'	=> CURRENT_USER_ID,
								'affected_account_name' => $global_name
							);
		$new_record_action = $new_log_action->log_action_save($log_action_args);
		
		$redirect_to = 'index.php';
		if ( isset( $_GET['timeout'] ) ) {
			$redirect_to .= '?error=timeout';
		}

		header("Location: " . $redirect_to);
		die();
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
							$this->get_groups		= new MembersActions();
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
						$global_user = get_current_user_username();
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
						$new_log_action = new LogActions();
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
				foreach($requested_files as $file_id) {
					$file_list[] = $file_id;
				}
				ob_clean();
				flush();
				echo implode( ',', $file_list );
			}
		}
	}
}

$process = new process;
