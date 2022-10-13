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
	if (get_option('show_upgrade_success_message') == 'true' && in_array(CURRENT_USER_LEVEL, [9, 8, 7])) {
?>
        <div class="row">
            <div class="col-sm-12">
                <div id="donations_message">
                    <p id="db_upgraded"><i class="fa fa-info-circle"></i> <?php _e('The database was updated to support this version of the software.', 'cftp_admin'); ?> <a href="https://www.projectsend.org/change-log/" class="text-decoration-underline" target="_blank"><?php _e('View change log','cftp_admin'); ?></a></p>
                    <h3><strong><?php _e('Do you want to support ProjectSend?', 'cftp_admin');?></strong></h3>
                    <p><?php _e('Please remember that this tool is free software.', 'cftp_admin'); ?></p>
                    <p><?php _e('It is made with love during the hard-to-find free time of mainly one developer.','cftp_admin'); ?></p>
                    <p><?php _e('With as little as <strong>$2 per month</strong> you can help the project stay active and updated.','cftp_admin'); ?></p>
                    <p>
                        <a href="<?php echo DONATIONS_URL; ?>" target="_blank" class="text-decoration-underline"><?php _e('Please consider making a donation to support further development.', 'cftp_admin'); ?>
                    </a>
                    <p><?php _e('Thank you!', 'cftp_admin'); ?></p>
                    <div id="upgrade_actions mt-5">
                        <a class="btn btn-lg btn-primary" role="button" href="<?php echo DONATIONS_URL; ?>" target="_blank"><?php _e('I want to collaborate monthly','cftp_admin'); ?></a>
                        <a class="btn btn-lg btn-primary" role="button" href="mailto:contact@projectsend.org" target="_blank"><?php _e('I want to help in other ways','cftp_admin'); ?></a>
                        <a class="btn btn-md btn-default" role="button" href="<?php echo BASE_URI; ?>process.php?do=dismiss_upgraded_notice"><?php _e('Dismiss message','cftp_admin'); ?></a>
                    </div>
                </div>
            </div>
        </div>
<?php
    }

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
