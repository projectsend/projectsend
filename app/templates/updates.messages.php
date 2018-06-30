<?php
/**
 * Define the common functions used on the installer and updates.
 *
 * @package		ProjectSend
 * @subpackage	Updates
 */
	/**
	 * If any update was made to the database structure, show the message
	 */
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
			/**
			 * Reset the values on the database.
			 */
            global $core_updates;
			$core_updates->reset_update_status();
        }
    }
	else {
        /**
         * Used when a new version is found, but not
         * if the current installation has just been
         * updated.
         */
        if ( CURRENT_USER_LEVEL != '0' ) {
            if ( true === $core_updates->has_update_available() ) {
                $new_version_info = $core_updates->get_new_version_info();
	?>
                <div class="system_msg update_msg">
                    <div class="row">
                        <div class="col-sm-9">
                            <p><strong><?php _e('Update available!', 'cftp_admin');?></strong> <?php _e('version', 'cftp_admin'); ?> <?php echo $new_version_info['version']; ?> <?php _e('has been released.', 'cftp_admin');?></p>
                            <div class="buttons">
                                <a href="<?php echo $new_version_info['download']; ?>" class="btn btn-default btn-xs" target="_blank"><?php _e('Download', 'cftp_admin');?></a> <a href="<?php echo $new_version_info['changelog']; ?>" target="_blank" class="btn btn-default btn-xs"><?php _e('Changelog', 'cftp_admin');?></a>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <ul>
                                <li class="update_icon update_icon_status_<?php echo $new_version_info['features']; ?>">
                                    <span><i class="fa fa-plus fa-inverse fa-fw"></i></span>
                                </li>
                                <li class="update_icon update_icon_status_<?php echo $new_version_info['security']; ?>">
                                    <span><i class="fa fa-shield fa-inverse fa-fw"></i></span>
                                </li>
                                <li class="update_icon update_icon_status_<?php echo $new_version_info['important']; ?>">
                                    <span><i class="fa fa-exclamation fa-inverse fa-fw"></i></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
	<?php
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
