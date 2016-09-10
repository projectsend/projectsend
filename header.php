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

	<title><?php echo $page_title; ?> &raquo; <?php echo THIS_INSTALL_SET_TITLE; ?></title>
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
					<ul class="nav navbar-nav">
						<?php
							/**
							 * Show the HOME menu item only to
							 * system users.
							 */
							$groups_allowed = array(9,8,7);
							if (in_session_or_cookies($groups_allowed)) {
						?>
								<li <?php if (!empty($active_nav) && $active_nav == 'dashboard') { ?>class="active"<?php } ?>>
									<a href="<?php echo BASE_URI; ?>home.php"><?php _e('Dashboard', 'cftp_admin'); ?></a>
								</li>
	
								<li class="divider-vertical">
	
								<li class="dropdown <?php if (!empty($active_nav) && $active_nav == 'files') { ?>active<?php } ?>">
									<a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php _e('Files', 'cftp_admin'); ?> <b class="caret"></b></a>
									<ul class="dropdown-menu">
										<li><a href="<?php echo BASE_URI; ?>upload-from-computer.php"><?php _e('Upload', 'cftp_admin'); ?></a></li>
										<li class="divider"></li>
										<li><a href="<?php echo BASE_URI; ?>manage-files.php"><?php _e('Manage files', 'cftp_admin'); ?></a></li>
										<li><a href="<?php echo BASE_URI; ?>upload-import-orphans.php"><?php _e('Find orphan files', 'cftp_admin'); ?></a></li>
										<li class="divider"></li>
										<li><a href="<?php echo BASE_URI; ?>categories.php"><?php _e('Categories', 'cftp_admin'); ?></a></li>
									</ul>
								</li>
	
								<li class="divider-vertical">
	
							<?php
								/**
								 * Show the CLIENTS menu only to
								 * System administrators and Account managers
								 */
								$clients_allowed = array(9,8);
								if (in_session_or_cookies($clients_allowed)) {
							?>
								<li class="dropdown <?php if (!empty($active_nav) && $active_nav == 'clients') { ?>active<?php } ?>">
									<a href="#" class="dropdown-toggle" data-toggle="dropdown">
										<?php _e('Clients', 'cftp_admin'); ?>
										<?php
											$sql_inactive = $dbh->prepare( "SELECT DISTINCT user FROM " . TABLE_USERS . " WHERE active = '0' AND level = '0'" );
											$sql_inactive->execute();
											$count_inactive = $sql_inactive->rowCount();
											if ($count_inactive > 0) {
										?>
												<span class="badge">
													<?php echo $count_inactive; ?>
												</span>
										<?php
											}
										?>
										<b class="caret"></b>
									</a>
									<ul class="dropdown-menu">
										<li><a href="<?php echo BASE_URI; ?>clients-add.php"><?php _e('Add new', 'cftp_admin'); ?></a></li>
										<li><a href="<?php echo BASE_URI; ?>clients.php"><?php _e('Manage clients', 'cftp_admin'); ?></a></li>
									</ul>
								</li>
	
								<li class="divider-vertical">
	
						<?php
								}
						?>
			
						<?php
							/**
							 * Show the GROUPS menu only to
							 * System administrators and Account managers
							 */
							$groups_allowed = array(9,8);
							if (in_session_or_cookies($groups_allowed)) {
						?>
								<li class="dropdown <?php if (!empty($active_nav) && $active_nav == 'groups') { ?>active<?php } ?>">
									<a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php _e('Clients groups', 'cftp_admin'); ?> <b class="caret"></b></a>
									<ul class="dropdown-menu">
										<li><a href="<?php echo BASE_URI; ?>groups-add.php"><?php _e('Add new', 'cftp_admin'); ?></a></li>
										<li><a href="<?php echo BASE_URI; ?>groups.php"><?php _e('Manage groups', 'cftp_admin'); ?></a></li>
									</ul>
								</li>
						<?php
								}
						?>
	
								<li class="divider-vertical">
	
						<?php
							/**
							 * Show the USERS menu only to
							 * System administrators
							 */
							$users_allowed = array(9);
							if (in_session_or_cookies($users_allowed)) {
						?>
								<li class="dropdown <?php if (!empty($active_nav) && $active_nav == 'users') { ?>active<?php } ?>">
									<a href="#" class="dropdown-toggle" data-toggle="dropdown">
										<?php _e('System Users', 'cftp_admin'); ?>
										<?php
											$sql_inactive = $dbh->prepare( "SELECT DISTINCT user FROM " . TABLE_USERS . " WHERE active = '0' AND level != '0'" );
											$sql_inactive->execute();
											$count_inactive = $sql_inactive->rowCount();
											if ($count_inactive > 0) {
										?>
												<span class="badge">
													<?php echo $count_inactive; ?>
												</span>
										<?php
											}
										?>
										<b class="caret"></b>
									</a>
									<ul class="dropdown-menu">
										<li><a href="<?php echo BASE_URI; ?>users-add.php"><?php _e('Add new', 'cftp_admin'); ?></a></li>
										<li><a href="<?php echo BASE_URI; ?>users.php"><?php _e('Manage system users', 'cftp_admin'); ?></a></li>
									</ul>
								</li>
						<?php
								}
						?>
	
								<li class="divider-vertical">
	
						<?php
							/**
							 * Show the OPTIONS menu only to
							 * System administrators
							 */
							$options_allowed = array(9);
							if (in_session_or_cookies($options_allowed)) {
						?>
								<li class="dropdown <?php if (!empty($active_nav) && $active_nav == 'options') { ?>active<?php } ?>">
									<a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php _e('Options', 'cftp_admin'); ?> <b class="caret"></b></a>
									<ul class="dropdown-menu">
										<li><a href="<?php echo BASE_URI; ?>options.php"><?php _e('General options', 'cftp_admin'); ?></a></li>
										<li class="divider"></li>
										<li><a href="<?php echo BASE_URI; ?>branding.php"><?php _e('Branding', 'cftp_admin'); ?></a></li>
										<li><a href="<?php echo BASE_URI; ?>email-templates.php"><?php _e('E-mail templates', 'cftp_admin'); ?></a></li>
									</ul>
								</li>
					<?php
							}
						}
						/** Generate the menu for clients */
						else {
							if (CLIENTS_CAN_UPLOAD == 1) {
					?>
								<li><a href="<?php echo BASE_URI; ?>upload-from-computer.php"><?php _e('Upload', 'cftp_admin'); ?></a></li>
					<?php
							}
					?>
							<li><a href="<?php echo BASE_URI; ?>manage-files.php"><?php _e('Manage files', 'cftp_admin'); ?></a></li>
							<li><a href="<?php echo BASE_URI.'my_files/'; ?>"><?php _e('View my files', 'cftp_admin'); ?></a></li>
					<?php
						}
					?>
					</ul>
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