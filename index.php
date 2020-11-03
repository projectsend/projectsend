<?php
/**
 * ProjectSend (previously cFTP) is a free, clients-oriented, private file
 * sharing web application.
 * Clients are created and assigned a username and a password. Then you can
 * upload as much files as you want under each account, and optionally add
 * a name and description to them. 
 *
 * ProjectSend is hosted on Google Code.
 * Feel free to participate!
 *
 * @link		https://github.com/projectsend/projectsend
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GNU GPL version 2
 * @package		ProjectSend
 *
 */
$allowed_levels = array(9,8,7,0);
require_once 'bootstrap.php';

$page_title = __('Log in','cftp_admin');

$body_class = array('login');
$page_id = 'login';

if ( isset($_SESSION['errorstate'] ) ) {
    $errorstate = $_SESSION['errorstate'];
    unset($_SESSION['errorstate']);
}

$csrf_token = getCsrfToken();

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';
?>
<div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">

	<?php echo get_branding_layout(true); ?>

    <?php
        $login_types = array(
            'local' => '1',
            'ldap' => get_option('ldap_signin_enabled'),
        );
    ?>
	<div class="white-box">
		<div class="white-box-interior">
			<div class="ajax_response">
				<?php
					/** Coming from an external form */
					if ( isset( $_GET['error'] ) ) {
                        echo system_message('danger', $auth->getLoginError($_GET['error']));
					}
				?>
			</div>
        
            <?php /*
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active"><a href="#local" aria-controls="local" role="tab" data-toggle="tab">Local account</a></li>
                <?php if ($login_types['ldap'] == 'true') { ?>
                    <li role="presentation"><a href="#ldap" aria-controls="ldap" role="tab" data-toggle="tab">LDAP</a></li>
                <?php } ?>
            </ul> */ ?>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane fade in active" id="local">
                    <?php include_once FORMS_DIR . DS . 'login.php'; ?>

                    <div class="login_form_links">
                        <p id="reset_pass_link"><?php _e("Forgot your password?",'cftp_admin'); ?> <a href="<?php echo BASE_URI; ?>reset-password.php"><?php _e('Set up a new one.','cftp_admin'); ?></a></p>
                        <?php
                            if (get_option('clients_can_register') == '1') {
                        ?>
                                <p id="register_link"><?php _e("Don't have an account yet?",'cftp_admin'); ?> <a href="<?php echo BASE_URI; ?>register.php"><?php _e('Register as a new client.','cftp_admin'); ?></a></p>
                        <?php
                            } else {
                        ?>
                                <p><?php _e("This server does not allow self registrations.",'cftp_admin'); ?></p>
                                <p><?php _e("If you need an account, please contact a server administrator.",'cftp_admin'); ?></p>
                        <?php
                            }
                        ?>
                    </div>
                </div>

                <?php /* if ($login_types['ldap'] == 'true') { ?>
                    <div role="tabpanel" class="tab-pane fade" id="ldap">
                        <?php include_once FORMS_DIR . DS . 'login-ldap.php'; ?>
                    </div>
                <?php } */ ?>
            </div>
		</div>
	</div>
</div>

<?php
	include_once ADMIN_VIEWS_DIR . DS . 'footer.php';