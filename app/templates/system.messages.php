<?php
/**
 * Display all logged system messages
 */
?>
<div class="row row_system_messages">
    <div class="col-sm-12">
        <?php
            // Start with special messages */
            global $messages;
            $get_specials = $messages->get_specials();
            if ( !empty( $get_specials ) ) {

                if ( CURRENT_USER_LEVEL != '0' ) {

                    // Donations message :)
                    if ( in_array( 'donations', $get_specials ) ) {
        ?>
                        <div id="donations_message">
                            <h3><strong><?php _e('Do you want to support ProjectSend?', 'cftp_admin');?></strong></h3>
                            <p><?php _e('Please remember that this tool is free software. If you find the system useful', 'cftp_admin'); ?>
                                <a href="<?php echo DONATIONS_URL; ?>" target="_blank"><?php _e('please consider making a donation to support further development.', 'cftp_admin'); ?></a><br>
                                <?php _e('Thank you!', 'cftp_admin'); ?>
                            </p>
                        </div>
        <?php
                    }

                    // Update available
                    if ( in_array( 'update_available', $get_specials ) ) {
        ?>
                        <div class="alert alert-warning update_msg">
                            <div class="row">
                                <div class="col-sm-8">
                                    <strong><?php _e('Update available!', 'cftp_admin'); ?></strong> <?php echo sprintf( __('ProjectSend %s has been released', 'cftp_admin'), $new_version_info['version']); ?>
                                </div>
                                <div class="col-sm-4 text-right">
                                    <a href="<?php echo $new_version_info['download']; ?>" class="btn btn-default btn-xs" target="_blank"><?php _e('Download', 'cftp_admin');?></a> <a href="<?php echo $new_version_info['changelog']; ?>" target="_blank" class="btn btn-default btn-xs"><?php _e('Changelog', 'cftp_admin');?></a>
                                </div>
                            </div>
                        </div>
        <?php
                    }
                }
            }

            // Continue with the standard messages that have been added
            $get_messages = $messages->get();
            if ( !empty( $get_messages ) ) {
                foreach ( $get_messages as $message ) {
        ?>
                    <div class="alert alert-<?php echo $message['type']; ?>">
                        <?php if ( $message['add_notice'] === true ) { ?>
                            <strong><?php _e('System Notice:', 'cftp_admin'); ?></strong>
                        <?php } ?>
                        <?php echo $message['message']; ?>
                    </div>
        <?php
                }
            }
        ?>        
    </div>
</div>
