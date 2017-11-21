<?php
/**
 * Shows the list of public groups and files
 *
 * @package		ProjectSend
 * @subpackage	Files
 *
 */
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

/**
 * If the option to show this page is not enabled, redirect
 */
if ( PUBLIC_LISTING_ENABLE != 1 ) {
	header("location:" . BASE_URI . "index.php");
	die();	
}

/**
 * Check the option to show the page to logged in users only
 */
if ( PUBLIC_LISTING_LOGGED_ONLY == 1 ) {
	check_for_session();
}

$page_title = __('Public groups and files','cftp_admin');

$dont_redirect_if_logged = 1;

include('header-unlogged.php');
?>
<div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">

	<div class="white-box">
		<div class="white-box-interior">
			<div class="text-center">
				<h3><?php echo $page_title; ?></h3>
			</div>
		</div>
	</div>

	<div class="login_form_links">
		<?php
			if ( !check_for_session(false) && CLIENTS_CAN_REGISTER == '1') {
		?>
				<p id="register_link"><?php _e("Don't have an account yet?",'cftp_admin'); ?> <a href="<?php echo BASE_URI; ?>register.php"><?php _e('Register as a new client.','cftp_admin'); ?></a></p>
		<?php
			}
		?>
		<p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.','cftp_admin'); ?></a></p>
	</div>
</div>

<?php
	include('footer.php');
