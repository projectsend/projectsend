<?php
	require_once('sys.includes.php');
	/** Low level accesses are not permited */
	if (CURRENT_USER_LEVEL != 9) {
		prevent_direct_access();
	}
	else {
		/**
		 * Very simple way to check if the file was
		 * called through ajax.
		 */
		if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')) {
			$max_show_log = 10;
			$log_action = mysql_real_escape_string($_GET['action']);
		
			$log_query = "SELECT * FROM tbl_actions_log";
		
			if (!empty($log_action)) {
				$log_query .= " WHERE action = '$log_action'";
			}
		
			$log_query .= " ORDER BY id DESC LIMIT $max_show_log";
			$sql_log = $database->query($log_query);
			$log_count = mysql_num_rows($sql_log);
			if ($log_count > 0) {
				while($log = mysql_fetch_array($sql_log)) {
					$rendered = render_log_action(
										array(
											'action' => $log['action'],
											'timestamp' => $log['timestamp'],
											'owner_id' => $log['owner_id'],
											'owner_user' => $log['owner_user'],
											'affected_file' => $log['affected_file'],
											'affected_file_name' => $log['affected_file_name'],
											'affected_account' => $log['affected_account'],
											'affected_account_name' => $log['affected_account_name']
										)
					);
				?>
					<li>
						<div class="log_ico">
							<img src="img/log_icons/<?php echo html_output($rendered['icon']); ?>.png" alt="Action icon">
						</div>
						<div class="home_log_text">
							<div class="date"><?php echo html_output($rendered['timestamp']); ?></div>
							<div class="action">
								<?php
									if (!empty($rendered['1'])) { echo '<span>'.html_output($rendered['1']).'</span> '; }
									echo html_output($rendered['text']).' ';
									if (!empty($rendered['2'])) { echo '<span class="secondary">'.html_output($rendered['2']).'</span> '; }
									if (!empty($rendered['3'])) { echo ' '.html_output($rendered['3']).' '; }
									if (!empty($rendered['4'])) { echo '<span>'.html_output($rendered['4']).'</span> '; }
								?>
							</div>
						</div>
					</li>
				<?php
				}
			}
			else {
			?>
				<li><?php _e('There are no results','cftp_admin'); ?></li>
			<?php
			}
		}
	}
?>
