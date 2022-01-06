<?php
/**
 * Allows the administrator to customize the emails
 * sent by the system.
 *
 * @package ProjectSend
 * @subpackage Options
 */
$allowed_levels = array(9);
require_once 'bootstrap.php';

$page_title = __('Test email configuration','cftp_admin');

$page_id = 'email_test';

$active_nav = 'tools';
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

if ($_POST) {
    $email = new \ProjectSend\Classes\Emails;
    $email->send([
        'type' => 'test_settings',
        'to' => $_POST['to'],
        'message' => $_POST['message'],
    ]);
}
?>
<div class="row">
    <div class="col-xs-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <form action="email-test.php" name="email_test" method="post" enctype="multipart/form-data" class="form-horizontal">
                    <?php addCsrf(); ?>
                    <input type="hidden" name="section" value="<?php echo $section; ?>">

                    <?php if ($_POST) { ?>
                        <div class="form-group">
                            <div class="col-sm-12">
                                <label for="result"><?php _e('Result','cftp_admin'); ?></label>
                                <textarea name="result" id="result" class="form-control textarea_high" readonly><?php echo $email->getDebugResult(); ?></textarea>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="form-group">
                        <div class="col-sm-12">
                            <label for="to"><?php _e('Send to:','cftp_admin'); ?></label>
                            <input type="text" name="to" id="to" class="form-control" value="<?php echo CURRENT_USER_EMAIL; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-12">
                            <label for="message"><?php _e('Message','cftp_admin'); ?></label>
                            <textarea name="message" id="message" class="form-control textarea_high"></textarea>
                        </div>
                    </div>

                    <div class="after_form_buttons">
                        <button type="submit" name="submit" class="btn btn-wide btn-primary empty"><?php _e('Send test email','cftp_admin'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
