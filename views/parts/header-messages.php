<?php
// Check if we are on a development version
if ( IS_DEV == true ) {
?>
    <div class="row">
        <div class="col-sm-12">
            <div class="system_msg">
                <p><strong><?php _e('System Notice:', 'cftp_admin');?></strong> <?php _e('You are using a development version. Some features may be unfinished or not working correctly.', 'cftp_admin'); ?></p>
            </div>
        </div>
    </div>
<?php
}

// Check important directories write permissions
$write_errors = [];
$directories = [
    ADMIN_UPLOADS_DIR,
    UPLOADED_FILES_DIR,
    THUMBNAILS_FILES_DIR,
    UPLOADS_TEMP_DIR,
];
foreach ($directories as $directory) {
    if (!file_exists($directory)) {
        @mkdir($directory, 0775, true);
    }

    if (!is_writable($directory)) {
        $write_errors[] = $directory;
    }
}

if ( !empty($write_errors) && in_array(CURRENT_USER_LEVEL, [9,8,7]) ) {
    $msg = '<p><strong>'.__('Warning:', 'cftp_admin').'</strong>' . ' ' . __('The following directories do not exist or have write permissions errors.', 'cftp_admin').'</p>';
    $msg .= '<p>'.__('File uploading or other important functions might not work.', 'cftp_admin').'</p>';
    foreach ($write_errors as $directory) {
        $msg .= '<p>'.$directory.'</p>';
    }

    echo system_message('danger', $msg);
}

// Delete old zip files
$zip_files = glob(UPLOADS_TEMP_DIR.'/zip_*');
if (!empty($zip_files)) {
    $found = 0;
    $deleted = 0;
    foreach ($zip_files as $file) {
        if(is_file($file) and time()-filemtime($file) > ZIP_TMP_EXPIRATION_TIME) {
            $found++;
            if (@unlink($file)) {
                $deleted++;
            }
        }
    }

    if ($deleted < $found && in_array(CURRENT_USER_LEVEL, [9,8,7])) {
        $msg = '<p><strong>'.__('Warning:', 'cftp_admin').'</strong>' . ' ' . sprintf(__('One or more temporary zip files could not be deleted. Files older than %s hours are generally considered safe to delete.', 'cftp_admin'), convert_seconds(ZIP_TMP_EXPIRATION_TIME)['hours']);
        $msg .= '<p>'.sprintf(__('To make space on your disk, you can manually delete old files from %s', 'cftp_admin'), UPLOADS_TEMP_DIR).'</p>';
        echo system_message('danger', $msg);
    }
}
