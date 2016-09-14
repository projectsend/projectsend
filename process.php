<?php
/**
 * Class that handles the log out and file download actions.
 *
 * @package		ProjectSend
 */
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');
require_once('header.php');

class process {
	function __construct() {
		global $dbh;
		$this->dbh = $dbh;
		$this->process();
	}

	function process() {
		switch ($_GET['do']) {
			case 'download':
				$this->download_file();
				break;
			case 'zip_download':
				$this->download_zip();
				break;
			case 'get_downloaders':
				$this->get_downloaders();
				break;
			case 'logout':
				$this->logout();
				break;
			default:
				header('Location: '.BASE_URI);
				break;
		}
	}
	
	function download_file() {
		$this->check_level = array(9,8,7,0);
		if (isset($_GET['id']) && isset($_GET['client'])) {
			/** Do a permissions check for logged in user */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				
					/**
					 * Get the file name
					 */
					$this->statement = $this->dbh->prepare("SELECT url, expires, expiry_date FROM " . TABLE_FILES . " WHERE id=:id");
					$this->statement->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
					$this->statement->execute();
					$this->statement->setFetchMode(PDO::FETCH_ASSOC);
					$this->row = $this->statement->fetch();
					$this->real_file_url	= $this->row['url'];
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
							$this->groups = $this->dbh->prepare("SELECT DISTINCT group_id FROM " . TABLE_MEMBERS . " WHERE client_id=:id");
							$this->groups->bindValue(':id', CURRENT_USER_ID, PDO::PARAM_INT);
							$this->groups->execute();

							if ( $this->groups->rowCount() > 0 ) {
								$this->groups->setFetchMode(PDO::FETCH_ASSOC);
								while ( $this->row_groups = $this->groups->fetch() ) {
									$this->groups_ids[] = $this->row_groups["group_id"];
								}
								if ( !empty( $this->groups_ids ) ) {
									$this->found_groups = implode( ',', $this->groups_ids );
								}
							}


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
								 * If the file is being downloaded by a client, add +1 to
								 * the download count
								 */
								$this->statement = $this->dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (user_id , file_id) VALUES (:user_id, :file_id)");
								$this->statement->bindValue(':user_id', CURRENT_USER_ID, PDO::PARAM_INT);
								$this->statement->bindParam(':file_id', $_GET['id'], PDO::PARAM_INT);
								$this->statement->execute();

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
						$global_id = get_logged_account_id($global_user);
						$log_action_owner_id = $global_id;
					}

					if ($this->can_download == true) {
						/** Record the action log */
						$new_log_action = new LogActions();
						$log_action_args = array(
												'action'				=> $log_action,
												'owner_id'				=> $log_action_owner_id,
												'affected_file'			=> (int)$_GET['id'],
												'affected_file_name'	=> $this->real_file_url,
												'affected_account'		=> (int)$_GET['client_id'],
												'affected_account_name'	=> $_GET['client'],
												'get_user_real_name'	=> true,
												'get_file_real_name'	=> true
											);
						$new_record_action = $new_log_action->log_action_save($log_action_args);
						$this->real_file = UPLOADED_FILES_FOLDER.$this->real_file_url;
						if (file_exists($this->real_file)) {
							session_write_close();
							while (ob_get_level()) ob_end_clean();
							header('Content-Type: application/octet-stream');
							header('Content-Disposition: attachment; filename='.basename($this->real_file));
							header('Expires: 0');
							header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
							header('Pragma: public');
							header('Cache-Control: private',false);
							header('Content-Length: ' . get_real_size($this->real_file));
							header('Connection: close');
							//readfile($this->real_file);
							
							$context = stream_context_create();
							$file = fopen($this->real_file, 'rb', FALSE, $context);
							while( !feof( $file ) ) {
								//usleep(1000000); //Reduce download speed
								echo stream_get_contents($file, 2014);
							}
							
							fclose( $file );
							exit;
						}
						else {
							header("HTTP/1.1 404 Not Found");
							exit;
						}
					}
			}
		}
	}

	function download_zip() {
		$this->check_level = array(9,8,7,0);
		if (isset($_GET['files']) && isset($_GET['client'])) {
			// do a permissions check for logged in user
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$file_list = array();
				$requested_files = $_GET['files'];
				foreach($requested_files as $file_id) {
					echo $file_id;
					$this->statement = $this->dbh->prepare("SELECT * FROM " . TABLE_FILES . " WHERE id=:file_id");
					$this->statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
					$this->statement->execute();
					$this->statement->setFetchMode(PDO::FETCH_ASSOC);
					$this->row = $this->statement->fetch();
					$this->url = $this->row['url'];
					$file = UPLOADED_FILES_FOLDER.$this->url;
					if (file_exists($file)) {
						$file_list[] = $this->url;
					}
				}
				ob_clean();
				flush();
				echo implode( ',', $file_list );
			}
		}
	}

	function get_downloaders() {
		$this->check_level = array(9,8,7);
		if (isset($_GET['sys_user']) && isset($_GET['file_id'])) {
			// do a permissions check for logged in user
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$file_id = (int)$_GET['file_id'];
				$current_level = get_current_user_level();
				$this->statement = $this->dbh->prepare("SELECT id, uploader, filename FROM " . TABLE_FILES . " WHERE id=:file_id");
				$this->statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
				$this->statement->execute();
				$this->statement->setFetchMode(PDO::FETCH_ASSOC);
				$this->row = $this->statement->fetch();
				$this->uploader = $this->row['uploader'];

				/** Uploaders can only generate this for their own files */
				if ($current_level == '7') {
					if ($this->uploader != $_GET['sys_user']) {
						ob_clean();
						flush();
						_e("You don't have the required permissions to view the requested information about this file.",'cftp_admin');
						exit;
					}
				}

				$this->filename = $this->row['filename'];

				

				$this->sql_who = $this->dbh->prepare("SELECT user_id, COUNT(*) AS downloads FROM " . TABLE_DOWNLOADS . " WHERE file_id=:file_id GROUP BY user_id");
				$this->sql_who->bindParam(':file_id', $file_id, PDO::PARAM_INT);
				$this->sql_who->execute();
				$this->sql_who->setFetchMode(PDO::FETCH_ASSOC);
				while ( $this->wrow = $this->sql_who->fetch() ) {
					$this->downloaders_ids[] = $this->wrow['user_id'];
					$this->downloaders_count[$this->wrow['user_id']] = $this->wrow['downloads'];
				}

				$this->users_ids = implode(',',array_unique(array_filter($this->downloaders_ids)));

				$this->downloaders_list = array();



				$this->sql_who = $this->dbh->prepare("SELECT id, name, email, level FROM " . TABLE_USERS . " WHERE FIND_IN_SET(id,:users)");
				$this->sql_who->bindParam(':users', $this->users_ids);
				$this->sql_who->execute();
				$this->sql_who->setFetchMode(PDO::FETCH_ASSOC);

				$i = 0;
				while ( $this->urow = $this->sql_who->fetch() ) {
					$this->downloaders_list[$i] = array(
														'name' => $this->urow['name'],
														'email' => $this->urow['email']
													);
					$this->downloaders_list[$i]['type'] = ($this->urow['name'] == 0) ? 'client' : 'user';
					$this->downloaders_list[$i]['count'] = isset($this->downloaders_count[$this->urow['id']]) ? $this->downloaders_count[$this->urow['id']] : null;
					$i++;
				}

				ob_clean();
				flush();
				echo json_encode($this->downloaders_list);
			}
		}
	}

	function logout() {
		header("Cache-control: private");
		unset($_SESSION['loggedin']);
		unset($_SESSION['access']);
		unset($_SESSION['userlevel']);
		session_destroy();

		/** If there is a cookie, unset it */
		setcookie("loggedin","",time()-COOKIE_EXP_TIME);
		setcookie("password","",time()-COOKIE_EXP_TIME);
		setcookie("access","",time()-COOKIE_EXP_TIME);
		setcookie("userlevel","",time()-COOKIE_EXP_TIME);

		/** Record the action log */
		$new_log_action = new LogActions();
		$log_action_args = array(
								'action'	=> 31,
								'owner_id'	=> CURRENT_USER_ID,
								'affected_account_name' => $global_name
							);
		$new_record_action = $new_log_action->log_action_save($log_action_args);

		header("location:index.php");
	}
}

$process = new process;
?>
