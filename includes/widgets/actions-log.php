<?php
	$render_container = true;
	if ( isset( $_GET['ajax_call'] ) ) {
		require_once '../../bootstrap.php';
		$render_container = false;
	}
	
	$actions_buttons = array(
							array(
								'action'	=> '',
								'title'		=> __('All activities','cftp_admin'),
							),
							array(
								'action'	=> '1',
								'title'		=> __('Logins','cftp_admin'),
							),
							array(
								'action'	=> '8',
								'title'		=> __('Downloads','cftp_admin'),
							),
						);

	if ( CLIENTS_CAN_REGISTER == 1 ) {
		$actions_buttons[] = array(
									'action'	=> '4',
									'title'		=> __('Clients self-registrations','cftp_admin'),
								);
	}
	$default_actions = '';

	if ( $render_container == true ) {
?>
		<div class="widget">
			<h4><?php _e('Recent activites','cftp_admin'); ?></h4>
			<div class="widget_int">
				<div class="log_change_action">
					<?php
						foreach ( $actions_buttons as $index => $button ) {
					?>
							<a href="#" class="log_action btn btn-sm btn-default <?php if ( $button['action'] == $default_actions ) { echo 'btn-inverse'; } ?>" data-action="<?php echo $button['action']; ?>">
								<?php echo $button['title']; ?>
							</a>
					<?php
						}
					?>
				</div>
				<ul class="activities_log">
				</ul>
				<div class="view_full_log">
					<a href="actions-log.php" class="btn btn-primary btn-wide"><?php _e('View all','cftp_admin'); ?></a>
				</div>
			</div>
		</div>
<?php
	}

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
			$log_action = isset( $_GET['action'] ) ? $_GET['action'] : '';

			$params = array();
			$log_query = "SELECT * FROM " . TABLE_LOG;
			if (!empty($log_action)) {
				$log_query .= " WHERE action = :action";
				$params[':action'] = $log_action;
			}
			$log_query .= " ORDER BY id DESC LIMIT :max";
			$params[':max'] = $max_show_log;

			$sql_log = $dbh->prepare( $log_query );
			$sql_log->execute( $params );
			if ( $sql_log->rowCount() > 0 ) {
				$sql_log->setFetchMode(PDO::FETCH_ASSOC);
				while ( $log = $sql_log->fetch() ) {
					$rendered = render_log_action(
										array(
											'action'				=> $log['action'],
											'timestamp'				=> $log['timestamp'],
											'owner_id'				=> $log['owner_id'],
											'owner_user'			=> $log['owner_user'],
											'affected_file'			=> $log['affected_file'],
											'affected_file_name'	=> $log['affected_file_name'],
											'affected_account'		=> $log['affected_account'],
											'affected_account_name'	=> $log['affected_account_name']
										)
					);
				?>
					<li>
						<div class="log_ico">
							<img src="<?php echo ASSETS_IMG_URL; ?>/log_icons/<?php echo html_output($rendered['icon']); ?>.png" alt="Action icon">
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