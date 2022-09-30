<?php
define( 'IS_INSTALL', true );
define( 'ABS_PARENT', dirname( dirname(__FILE__) ) );

require_once ABS_PARENT . '/bootstrap.php';

global $dbh;

$page_id = 'installer_success';

include_once '../header-unlogged.php';
?>
<div class="row justify-content-md-center">
    <div class="col-12 col-sm-12 col-lg-4">
        <div class="white-box">
            <div class="white-box-interior">
                <h3><?php _e('Congratulations!','cftp_admin'); ?></h3>
                <p><?php _e('Everything is up and running.','cftp_admin'); ?></p>
                <p><?php _e('You may proceed to log in with your newely created username and password.','cftp_admin'); ?></p>
                <div class="text-center">
                    <a href="<?php echo BASE_URI; ?>" class="btn btn-primary btn-wide" target="_self">
                        <?php _e('Log in','cftp_admin'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    include_once '../footer.php';
