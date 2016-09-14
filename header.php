<?php
/**
 * This file generates the header for the back-end and also for the default
 * template.
 *
 * Other checks for user level are performed later to generate the different
 * menu items, and the content of the page that called this file.
 *
 * @package ProjectSend
 * @see check_for_session
 * @see check_for_admin
 * @see can_see_content
 */

/** Check for an active session or cookie */
check_for_session();

/** Check if the active account belongs to a system user or a client. */
//check_for_admin();

/** If no page title is defined, revert to a default one */
if (!isset($page_title)) { $page_title = __('System Administration','cftp_admin'); }

/**
 * Silent updates that are needed even if no user is logged in.
 */
require_once(ROOT_DIR.'/includes/core.update.silent.php');

/**
 * Call the database update file to see if any change is needed,
 * but only if logged in as a system user.
 */
$core_update_allowed = array(9,8,7);
if (in_session_or_cookies($core_update_allowed)) {
	require_once(ROOT_DIR.'/includes/core.update.php');
}
?>
<!doctype html>
<html lang="<?php echo SITE_LANG; ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?php echo html_output( $page_title . ' &raquo; ' . THIS_INSTALL_SET_TITLE ); ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="shortcut icon" href="<?php echo BASE_URI; ?>/favicon.ico" />
	<script type="text/javascript" src="<?php echo BASE_URI; ?>includes/js/jquery.1.12.4.min.js"></script>

	<link rel="stylesheet" media="all" type="text/css" href="<?php echo BASE_URI; ?>assets/bootstrap/css/bootstrap.min.css" />
	
	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
	
	<?php
		require_once( 'assets.php' );

		load_css_files();
	?>
</head>

<body>
	<header>
		<div id="header">
			<div class="container-fluid">
				<div class="row">
					<div class="col-xs-12 col-sm-6 main_title">
						<h1><?php echo THIS_INSTALL_SET_TITLE; ?></h1>
					</div>
					<div class="col-xs-12 col-sm-6">
						<div id="account">
							<span><?php _e('Welcome', 'cftp_admin'); ?>, <?php echo $global_name; ?></span>
							<?php
								if (CURRENT_USER_LEVEL == 0) {
									$my_account_link = 'clients-edit.php';
								}
								else {
									$my_account_link = 'users-edit.php';
								}
								$my_account_link .= '?id='.CURRENT_USER_ID;
							?>
							<a href="<?php echo BASE_URI.$my_account_link; ?>" class="my_account"><?php _e('My Account', 'cftp_admin'); ?></a>
							<a href="<?php echo BASE_URI; ?>process.php?do=logout" ><?php _e('Logout', 'cftp_admin'); ?></a>
						</div>
					</div>
				</div>
			</div>
		</div>
	
		<script type="text/javascript">
			$(document).ready(function() {
				<?php
					if ( !empty( $load_scripts ) && in_array( 'footable', $load_scripts ) ) {
				?>
						$("#select_all").click(function(){
							var status = $(this).prop("checked");
							/** Uncheck all first in case you used pagination */
							$("tr td input[type=checkbox]").prop("checked",false);
							$("tr:visible td input[type=checkbox]").prop("checked",status);
						});
	
						$('.footable').footable().find('> tbody > tr:not(.footable-row-detail):nth-child(even)').addClass('odd');
				<?php
					}
				?>
			});

			var dataExtraction = function(node) {
				if (node.childNodes.length > 1) {
					return node.childNodes[1].innerHTML;
				} else {
					return node.innerHTML;
				}
			}
		</script>

		<div class="navbar navbar-inverse">
			<div class="container-fluid">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#header-navbar-collapse" aria-expanded="false">
						<span class="sr-only"><?php _e('Menu', 'cftp_admin'); ?></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
				</div>

				<div class="collapse navbar-collapse" id="header-navbar-collapse">
					<?php
						include('header-menu.php');
					?>
				</div>
			</div>
		</div>

		<?php
			/**
			 * Gets the mark up abd values for the System Updated and
			 * errors messages.
			 */
			include(ROOT_DIR.'/includes/updates.messages.php');
		?>
	</header>

<?php
	/**
	 * Check if the current user has permission to view this page.
	 * If not, an error message is generated instead of the actual content.
	 * The allowed levels are defined on each individual page before the
	 * inclusion of this file.
	 */
	can_see_content($allowed_levels);
?>