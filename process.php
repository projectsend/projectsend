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
	function process() {
		$this->database = new MySQLDB;
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
		$this->database->Close();
	}
	
	function download_file() {
		$this->check_level = array(9,8,7,0);
		if (isset($_GET['id']) && isset($_GET['client'])) {
			/** Do a permissions check for logged in user */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				
					/**
					 * Get the file name
					 */
					$this->get_file_uri_sql	= 'SELECT url, expires, expiry_date FROM tbl_files WHERE id="' . (int)$_GET['id'] .'"';
					$this->get_file_uri		= $this->database->query($this->get_file_uri_sql);
					$this->got_url			= mysql_fetch_array($this->get_file_uri);
					$this->real_file_url	= $this->got_url['url'];
					$this->expires			= $this->got_url['expires'];
					$this->expiry_date		= $this->got_url['expiry_date'];
					
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
							$sql_groups = $this->database->query("SELECT DISTINCT group_id FROM tbl_members WHERE client_id='".CURRENT_USER_ID."'");
							$count_groups = mysql_num_rows($sql_groups);
							if ($count_groups > 0) {
								while($row_groups = mysql_fetch_array($sql_groups)) {
									$groups_ids[] = $row_groups["group_id"];
								}
								$found_groups = implode(',',$groups_ids);
							}

							/** Then, check on the client's own or gruops files */
							$files_own_query = 'SELECT * FROM tbl_files_relations WHERE (client_id="' . CURRENT_USER_ID . '"';
							if (!empty($found_groups)) {
								$files_own_query .= ' OR group_id IN ("' . $found_groups . '")';
							}
							$files_own_query .= ') AND file_id="' . (int)$_GET['id'] .'" AND hidden = "0"';

							$files_own = $this->database->query($files_own_query);
							$count_files = mysql_num_rows($files_own);
							if ($count_files > 0) {
								$this->can_download = true;
							}
							
							/** Continue */
							if ($this->can_download == true) {
								/**
								 * If the file is being downloaded by a client, add +1 to
								 * the download count
								 */
								$this->add_download_sql = 'INSERT INTO tbl_downloads (user_id , file_id) VALUES ("' . CURRENT_USER_ID .'", "' . (int)$_GET['id'] .'")';
								$this->sql = $this->database->query($this->add_download_sql);

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
												'action' => $log_action,
												'owner_id' => $log_action_owner_id,
												'affected_file' => (int)$_GET['id'],
												'affected_file_name' => $this->real_file_url,
												'affected_account' => (int)$_GET['client_id'],
												'affected_account_name' => mysql_real_escape_string($_GET['client']),
												'get_user_real_name' => true,
												'get_file_real_name' => true
											);
						$new_record_action = $new_log_action->log_action_save($log_action_args);
						$this->real_file = UPLOADED_FILES_FOLDER.$this->real_file_url;
						if (file_exists($this->real_file)) {
							while (ob_get_level()) ob_end_clean();
							header('Content-Type: application/octet-stream');
							header('Content-Disposition: attachment; filename='.basename($this->real_file));
							header('Expires: 0');
							header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
							header('Pragma: public');
							header('Cache-Control: private',false);
							header('Content-Length: ' . get_real_size($this->real_file));
							header('Connection: close');
							readfile($this->real_file);
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
				foreach($_GET['files'] as $file_id) {
					$this->sql = $this->database->query('SELECT * FROM tbl_files WHERE id="' . (int)$file_id .'"');
					$this->row = mysql_fetch_array($this->sql);
					$this->url = $this->row['url'];
					$file = UPLOADED_FILES_FOLDER.$this->url;
					if (file_exists($file)) {
						$file_list .= $this->url.',';
					}
				}
				ob_clean();
				flush();
				echo $file_list;
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
				
				$this->sql = $this->database->query('SELECT id, uploader, filename FROM tbl_files WHERE id="' . $file_id .'"');
				$this->row = mysql_fetch_array($this->sql);
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

				
				$this->sql_who = $this->database->query('SELECT user_id, COUNT(*) AS downloads FROM tbl_downloads WHERE file_id="' . $file_id .'" GROUP BY user_id');
				
				while ($this->wrow = mysql_fetch_array($this->sql_who)) {
					$this->downloaders_ids[] = $this->wrow['user_id'];
					$this->downloaders_count[$this->wrow['user_id']] = $this->wrow['downloads'];
				}
				$this->users_ids = implode(',',array_unique(array_filter($this->downloaders_ids)));

				$this->downloaders_list = array();
				$this->sql_who = $this->database->query("SELECT id, name, email, level FROM tbl_users WHERE id IN ($this->users_ids)");
				$i = 0;
				while ($this->urow = mysql_fetch_array($this->sql_who)) {
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
								'action' => 31,
								'owner_id' => $logged_id,
								'affected_account_name' => $global_name
							);
		$new_record_action = $new_log_action->log_action_save($log_action_args);

		header("location:index.php");
	}
}

$process = new process;
?>