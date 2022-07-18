<?php
use \Tamtamchik\SimpleFlash\Flash;

/**
 * Unblock an IP from the failed logins table
 *
 * @package ProjectSend
 * @subpackage Tools
 */
$allowed_levels = array(9);
require_once 'bootstrap.php';

$page_title = __('Unblock IP','cftp_admin');

$page_id = 'unblock_ip';

$active_nav = 'tools';
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

if ($_POST) {
    global $bfchecker;
    $unblock = $bfchecker->unblockIp($_POST['ip']);
    if ($unblock['status'] == 'success') {
        Flash::success(__('IP address succesfully unblocked', 'cftp_admin'));
    } else {
        Flash::error($unblock['message']);
    }

    header("Location: unblock-ip.php");
    exit;
}
?>
<div class="row">
    <div class="col-xs-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <form action="unblock-ip.php" name="unblock_ip" method="post" enctype="multipart/form-data" class="form-horizontal">
                    <?php addCsrf(); ?>
                    <input type="hidden" name="section" value="<?php echo $section; ?>">

                    <div class="form-group">
                        <div class="col-sm-12">
                            <label for="ip"><?php _e('IP address','cftp_admin'); ?></label>
                            <input type="text" name="ip" id="ip" class="form-control" value="">
                        </div>
                    </div>

                    <div class="after_form_buttons">
                        <button type="submit" name="submit" class="btn btn-wide btn-primary empty"><?php _e('Unblock IP','cftp_admin'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
