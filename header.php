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
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0 ,user-scalable=no">


	<title><?php echo html_output( $page_title . ' &raquo; ' . THIS_INSTALL_SET_TITLE ); ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="shortcut icon" href="<?php echo BASE_URI; ?>/favicon.ico" />
    <!----- Added B) --------->
    <script data-pace-options='{ "restartOnRequestAfter": true }' src="<?php echo BASE_URI; ?>assets/wrap/js/plugin/pace/pace.min.js"></script>
    <script>
		if (!window.jQuery) {
			document.write('<script src="<?php echo BASE_URI; ?>assets/wrap/js/libs/jquery-2.1.1.min.js"><\/script>');
		}
	</script>
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>
    <script>
		if (!window.jQuery.ui) {
			document.write('<script src="<?php echo BASE_URI; ?>assets/wrap/js/libs/jquery-ui-1.10.3.min.js"><\/script>');
		}
	</script>
	<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    



    <!------B)---------------->
	
	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
	
	<?php
		require_once( 'assets.php' );

		load_css_files();
	?>
        							<?php
								if (CURRENT_USER_LEVEL == 0) {
									$my_account_link = 'clients-edit.php';
								}
								else {
									$my_account_link = 'users-edit.php';
								}
								$my_account_link .= '?id='.CURRENT_USER_ID;
							?>
</head>


<body class="pace-done smart-style-1">


	<!-- HEADER -->
    <!----------------theme ------------------------>
		<header id="header">
			<div id="logo-group">
					
				<!-- PLACE YOUR LOGO HERE -->
				<span id="logo"><?php //echo THIS_INSTALL_SET_TITLE; ?>
			<?php echo BRAND_NAME; ?>
                	<!--<img src="img/logo.png" alt="SmartAdmin"> -->
                </span>
                </div>
				<!-- END LOGO PLACEHOLDER -->

				<!-- Note: The activity badge color changes when clicked and resets the number to 0
				Suggestion: You may want to set a flag when this happens to tick off all checked messages / notifications -->


			<!-- pulled right: nav area -->
			<div class="pull-right cc-pull-right">
            	<div id="hide-menu" class="btn-header pull-right">
					<span> <a href="javascript:void(0);" data-action="toggleMenu" title="Collapse Menu"><i class="fa fa-reorder"></i></a> </span>
				</div>
				<!-- logout button -->
                <span style="color:#ffffff;"><?php _e('Welcome', 'cftp_admin'); ?>, <?php echo $global_name; ?></span>
                <a class="cc-my-account" href="<?php echo BASE_URI.$my_account_link; ?>">
				<?php _e('My Account', 'cftp_admin'); ?> </span> </span> 
                </a>
				<div id="logout" class="btn-header transparent pull-right">
					<span> <a href="<?php echo BASE_URI; ?>process.php?do=logout" title="Sign Out" data-action="userLogout" data-logout-msg="You can improve your security further after logging out by closing this opened browser"><i class="fa fa-sign-out"></i></a> </span>
				</div>
				<!-- end logout button -->

				
			</div>
			<!-- end pulled right: nav area -->

		</header>

	<!-- END HEADER -->
    <!-- Left panel : Navigation area -->
		<!-- Note: This width of the aside area can be adjusted through LESS variables -->
		<aside id="left-panel">

			<!-- User info -->
			<div class="login-info">
				<span> <!-- User image size is adjusted inside CSS, it should stay as it --> 
					
					<a href="javascript:void(0);" id="show-shortcut" data-action="toggleShortcut">
						<img src="<?php echo BASE_URI; ?>/img/avatars/sunny.png" alt="me" class="online" /> 
						<span>
							<?php echo $global_name; ?>
						</span>
						<i class="fa fa-angle-down"></i>
					</a> 
					
				</span>
			</div>
			<!-- end user info -->

			<!-- NAVIGATION : This navigation is also responsive-->
            <nav>
            <?php
			include('header-menu.php'); 
			?>
            </nav>
			

			<!--<span class="minifyme" data-action="minifyMenu"> 
				<i class="fa fa-arrow-circle-left hit"></i> 
			</span>-->

		</aside>
		<!-- END NAVIGATION -->

<?php
	/**
	 * Check if the current user has permission to view this page.
	 * If not, an error message is generated instead of the actual content.
	 * The allowed levels are defined on each individual page before the
	 * inclusion of this file.
	 */
	can_see_content($allowed_levels);
?>
