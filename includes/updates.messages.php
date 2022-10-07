<?php
/**
 * Show updates status messages.
 */
    if (!empty($db_upgrade)) {
        if (!empty($db_upgrade->getAppliedUpdates())) {
            $updates_made = 1;
        };
    }

    // If any update was made to the database structure, show the message
	if (isset( $updates_made ) ) {
		if ( $updates_made > 0 ) {
?>
			<div class="row">
				<div class="col-sm-12">
					<div class="system_msg">
						<p><strong><?php _e('System Notice:', 'cftp_admin');?></strong> <?php _e('The database was updated to support this version of the software.', 'cftp_admin'); ?></p>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-12">
					<div id="donations_message">
						<h3><strong><?php _e('Do you want to support ProjectSend?', 'cftp_admin');?></strong></h3>
						<p><?php _e('Please remember that this tool is free software. If you find the system useful', 'cftp_admin'); ?>
							<a href="<?php echo DONATIONS_URL; ?>" target="_blank"><?php _e('please consider making a donation to support further development.', 'cftp_admin'); ?></a><br>
							<?php _e('Thank you!', 'cftp_admin'); ?>
						</p>
					</div>
				</div>
			</div>
<?php
			// Resets the database so it doesn't show the 'vew version' message.
			// reset_update_status();
		}
		else {
			// Used when a new version is found, but not if the current installation has just been updated.
            if ( CURRENT_USER_LEVEL != '0') {
                $update_data = get_latest_version_data();
                $update_data = json_decode($update_data);
                if ($update_data->update_available == '1') {
?>
                    <div class="alert alert-warning update_msg">
                        <div class="row">
                            <div class="col-sm-8">
                                <strong><?php _e('Update available!', 'cftp_admin'); ?></strong> <?php echo sprintf( __('ProjectSend %s has been released', 'cftp_admin'), $update_data->latest_version); ?>
                            </div>
                            <div class="col-sm-4 text-right">
                                <a href="<?php echo $update_data->url; ?>" class="btn btn-pslight btn-sm" target="_blank"><?php _e('Download', 'cftp_admin');?></a> <a href="<?php echo $update_data->chlog; ?>" target="_blank" class="btn btn-pslight btn-sm"><?php _e('Changelog', 'cftp_admin');?></a>
                            </div>
                        </div>
                    </div>
<?php
                }
			}
		}
	}

	if ( isset( $updates_error_messages ) && !empty( $updates_error_messages ) ) {
?>
		<div class="row">
			<div class="col-sm-12">
				<?php
					foreach ( $updates_error_messages as $updates_error_msg ) {
						echo system_message( 'error', $updates_error_msg );
					}
				?>
			</div>
		</div>
<?php
	}
