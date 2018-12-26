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
        $message = __('The database was updated to support this version of the software.', 'cftp_admin');
        $messages->add(
            'warning', $message, array(
                'id' => 'database_updated',
                'add_notice' => true,
            )
        );

        $messages->add_special('donations');

        /**
         * Reset the values on the database.
         */
        global $core_updates;
        $core_updates->save_database_version_number();
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
            $messages->add_special('update_available');
        }
    }
}

if ( isset( $updates_error_messages ) && !empty( $updates_error_messages ) ) {
    foreach ( $updates_error_messages as $updates_error_msg ) {
        $messages->add('danger', $updates_error_msg);
    }
}