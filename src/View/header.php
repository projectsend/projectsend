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

/**
 * Check if the current user has permission to view this page.
 * If not, an error message is generated instead of the actual content.
 * The allowed levels are defined on each individual page before the
 * inclusion of this file.
 */
can_see_content($allowed_levels);

/** Check if the active account belongs to a system user or a client. */
//check_for_admin();

/** If no page title is defined, revert to a default one */
if (!isset($page_title)) { $page_title = __('System Administration','cftp_admin'); }

if (!isset($body_class)) { $body_class = array(); }

if ( !empty( $_COOKIE['menu_contracted'] ) && $_COOKIE['menu_contracted'] == 'true' ) {
	$body_class[] = 'menu_contracted';
}

$body_class[] = 'menu_hidden';
?>
<!doctype html>
<html lang="<?php echo SITE_LANG; ?>">
<head>
	<meta charset="<?php echo CHARSET; ?>">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?php echo html_output( $page_title . ' &raquo; ' . htmlspecialchars(THIS_INSTALL_TITLE, ENT_QUOTES, CHARSET) ); ?></title>
	<?php meta_favicon(); ?>

	<?php
		include_once INCLUDES_DIR . DS . 'assets.load.php';

		load_css_files();

		require_jquery();
	?>
</head>

<body <?php echo add_body_class( $body_class ); ?>>
	<div class="container-custom">
		<header id="header" class="navbar navbar-static-top navbar-fixed-top">
			<ul class="nav pull-left nav_toggler">
				<li>
					<a href="#" class="toggle_main_menu"><i class="fa fa-bars" aria-hidden="true"></i><span><?php _e('Toogle menu', 'cftp_admin'); ?></span></a>
				</li>
			</ul>

			<div class="navbar-header">
				<span class="navbar-brand"><a href="<?php echo SYSTEM_URI; ?>" target="_blank"><?php include(ASSETS_IMG_DIR . 'ps-icon.svg'); ?></a> <?php echo html_output(THIS_INSTALL_TITLE); ?></span>
			</div>

			<ul class="nav pull-right nav_account">
				<li id="header_welcome">
					<span><?php //_e('Welcome', 'cftp_admin'); ?> <?php echo CURRENT_USER_NAME; ?></span>
				</li>
				<li>
					<?php
						$my_account_link = (CURRENT_USER_LEVEL == 0) ? 'clients-edit.php' : 'users-edit.php';
						$my_account_link .= '?id='.CURRENT_USER_ID;
					?>
					<a href="<?php echo BASE_URI.$my_account_link; ?>" class="my_account"><i class="fa fa-user-circle" aria-hidden="true"></i> <?php _e('My Account', 'cftp_admin'); ?></a>
				</li>
				<li>
					<a href="<?php echo BASE_URI; ?>process.php?do=logout" ><i class="fa fa-sign-out" aria-hidden="true"></i> <?php _e('Logout', 'cftp_admin'); ?></a>
				</li>
			</ul>
		</header>

		<div class="main_side_menu">
			<?php
				include(ADMIN_VIEWS_DIR . DS . 'header-menu.php');
			?>
		</div>

		<div class="main_content">
			<div class="container-fluid">
                <?php
                    /**
					 * Gets the mark up and values for the System Updated and
					 * errors messages.
					 */
                    include_once ADMIN_VIEWS_DIR . DS . 'updates.messages.php';

                    // Renders all system messages
                    require_once ADMIN_VIEWS_DIR . DS . 'system.messages.php';
                ?>

				<div class="row">
					<div id="section_title">
						<div class="col-xs-12">
							<h2><?php echo $page_title; ?></h2>
						</div>
					</div>
				</div>

				<div class="row">
