<?php
define( 'IS_INSTALL', true );
define( 'ABS_PARENT', dirname( dirname(__FILE__) ) );

require_once ABS_PARENT . '/bootstrap.php';

global $dbh;

$page_id = 'installer_installed';

include_once '../header-unlogged.php';
?>
<div class="row">
    <div class="col-12 col-sm-12 col-lg-4 col-lg-offset-4">
        <div class="white-box">
            <div class="white-box-interior">
                <h3><?php _e('The configuration file already exists.','cftp_admin'); ?></h3>
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
