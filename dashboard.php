<?php
/**
 * Home page for logged in system users.
 *
 * @package		ProjectSend
 *
 */
$allowed_levels = array(9,8,7);
require_once('bootstrap.php');
$page_title = __('Dashboard', 'cftp_admin');

$active_nav = 'dashboard';

$body_class = array('dashboard', 'home', 'hide_title');

include_once ADMIN_TEMPLATES_DIR . DS . 'header.php';

define('CAN_INCLUDE_FILES', true);

$log_allowed = array(9);

$show_log = false;
$sys_info = false;

if (in_session_or_cookies($log_allowed)) {
	$show_log = true;
	$sys_info = true;
}
?>
	<div class="col-sm-8">
		<div class="row">
			<div class="col-sm-12 container_widget_statistics">
				<?php include_ONCE WIDGETS_FOLDER.'statistics.php'; ?>
			</div>
		</div>
		<div class="row">
			<div class="col-sm-6 container_widget_projectsend_news">
				<?php include_once WIDGETS_FOLDER.'news.php'; ?>
			</div>
			<?php
				if ( $sys_info == true ) {
			?>
					<div class="col-sm-6">
						<?php include_once WIDGETS_FOLDER.'system-information.php'; ?>
					</div>
			<?php
				}
			?>
		</div>
	</div>
		
	<?php
		if ( $show_log == true ) {
	?>
			<div class="col-sm-4 container_widget_actions_log">
				<?php include(WIDGETS_FOLDER.'actions-log.php'); ?>
			</div>
	<?php
		}
	?>

    <script>
        $(document).ready(function(e) {
            // Get the widgets after loading the page
            ajax_widget_statistics(15);
            ajax_widget_log();
            ajax_widget_news();
        });
    </script>
<?php
	include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
