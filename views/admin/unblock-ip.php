<?php
/**
 * Unblock an IP from the failed logins table
 */
$allowed_levels = array(9);

$page_title = __('Unblock IP', 'cftp_admin');

$page_id = 'unblock_ip';

$active_nav = 'tools';
include_once VIEWS_PARTS_DIR.DS.'header.php';

$flash = get_container_item('flash');

if ($_POST) {
    global $bfchecker;
    $unblock = $bfchecker->unblockIp($_POST['ip']);
    if ($unblock['status'] == 'success') {
        $flash->success(__('IP address succesfully unblocked', 'cftp_admin'));
    } else {
        $flash->error($unblock['message']);
    }

    ps_redirect(BASE_URI . 'unblock-ip.php');
}
?>
<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <form action="unblock-ip.php" name="unblock_ip" method="post" enctype="multipart/form-data" class="form-horizontal">
                    <?php \ProjectSend\Classes\Csrf::addCsrf(); ?>
                    <input type="hidden" name="section" value="<?php echo $section; ?>">

                    <div class="form-group row">
                        <div class="col-sm-12">
                            <label for="ip"><?php _e('IP address', 'cftp_admin'); ?></label>
                            <input type="text" name="ip" id="ip" class="form-control" value="">
                        </div>
                    </div>

                    <div class="after_form_buttons">
                        <button type="submit" name="submit" class="btn btn-wide btn-primary empty"><?php _e('Unblock IP', 'cftp_admin'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
include_once VIEWS_PARTS_DIR.DS.'footer.php';
