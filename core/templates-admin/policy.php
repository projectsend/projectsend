<?php
/**
 * Privacy policy page
 *
 * @package		ProjectSend
 *
 */
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

/** Check if the page is enabled */
if ( defined('PRIVACY_POLICY_PAGE_ENABLE') && PRIVACY_POLICY_PAGE_ENABLE == '1' ) {
	$page_title = html_output(PRIVACY_POLICY_PAGE_TITLE) ?? __('Privacy policy', 'cftp_admin');
	$dont_redirect_if_logged = 1;
	include('header-unlogged.php');
}
else {
	header("Location:".BASE_URI."index.php");
	exit;
}
?>

<div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">

	<?php echo generate_branding_layout(); ?>

	<div class="white-box">
		<div class="white-box-interior">
			<h3><?php echo $page_title; ?></h3>

			<?php echo strip_tags(PRIVACY_POLICY_PAGE_CONTENT, '<p><br><span><a><strong><em><b><i><u><s><ul><ol><li>'); ?>
		</div>
	</div>

	<div class="login_form_links">
		<p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.','cftp_admin'); ?></a></p>
	</div>
</div>

<?php
	include('footer.php');
