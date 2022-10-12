<?php
define( 'IS_INSTALL', true );
define( 'ABS_PARENT', dirname( dirname(__FILE__) ) );

require_once ABS_PARENT . '/bootstrap.php';

$dbh = get_dbh();

$page_id = 'installer_installed';

include_once '../header-unlogged.php';
?>
<div class="row justify-content-md-center">
    <div class="col-12 col-sm-12 col-lg-4">
        <div class="white-box">
            <div class="white-box-interior">
                <h3><?php _e('Already installed','cftp_admin'); ?></h3>
                <p><?php _e('It seems that ProjectSend is already installed here.','cftp_admin'); ?></p>
                <p><?php _e('If you want to reinstall, please delete the system tables from the database and come back to the installation form.','cftp_admin'); ?></p>
                <div class="text-center">
                    <a href="<?php echo BASE_URI; ?>" class="btn btn-primary btn-wide" target="_self">
                        <?php _e('Go back','cftp_admin'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    include_once '../footer.php';
