<?php
/**
 * Home page for logged in system users.
 *
 * @package		ProjectSend
 *
 */
$allowed_levels = array(9,8,7);
require_once('sys.includes.php');
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
			<div class="col-sm-6">
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

	<script type="text/javascript">
		$(document).ready(function(){
			/** STATISTICS */
			function ajax_widget_statistics( days ) {
				var target = $('.statistics_graph');
				target.html('<div class="loading-graph">'+
								'<img src="<?php echo ASSETS_IMG_URI; ?>ajax-loader.gif" alt="Loading" />'+
							'</div>'
						);
				$.ajax({
					url: '<?php echo WIDGETS_URL; ?>statistics.php',
					data: { days:days, ajax_call:true },
					success: function(response){
								target.html(response);
							},
					cache:false
				});					
			}

			$('.stats_days').click(function(e) {
				e.preventDefault();

				if ($(this).hasClass('btn-inverse')) {
					return false;
				}
				else {
					var days = $(this).data('days');
					$('.stats_days').removeClass('btn-inverse');
					$(this).addClass('btn-inverse');
					ajax_widget_statistics(days);
				}
			});
	
			ajax_widget_statistics(15);

			/** ACTION LOG */
			function ajax_widget_log( action ) {
				var target = $('.activities_log');
				target.html('<div class="loading-graph">'+
								'<img src="<?php echo ASSETS_IMG_URI; ?>ajax-loader.gif" alt="Loading" />'+
							'</div>'
						);
				$.ajax({
					url: '<?php echo WIDGETS_URL; ?>actions-log.php',
					data: { action:action, ajax_call:true },
					success: function(response){
								target.html(response);
							},
					cache:false
				});					
			}

			// Generate the action log
			$('.log_action').click(function(e) {
				e.preventDefault();

				if ($(this).hasClass('btn-inverse')) {
					return false;
				}
				else {
					var action = $(this).data('action');
					$('.log_action').removeClass('btn-inverse');
					$(this).addClass('btn-inverse');
					ajax_widget_log(action);
				}
			});					

			ajax_widget_log();
		});
	</script>

<?php
	include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
