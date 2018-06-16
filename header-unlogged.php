<?php
/**
 * This file generates the header for pages shown to unlogged users and
 * clients (log in form and, if allowed, self registration form).
 *
 * @package ProjectSend
 */

/**
 * This file is shared with the installer. Let's start by checking
 * where is it being called from.
 */
if ( defined('IS_INSTALL') ) {
	if ( !defined('BASE_URI') ) {
		define( 'BASE_URI', '../' );
	}

	$lang = ( defined('SITE_LANG') ) ? SITE_LANG : 'en';

	$header_vars = array(
						'html_lang'		=> $lang,
						'title'			=> $page_title_install . ' &raquo; ' . SYSTEM_NAME,
						'header_title'	=> SYSTEM_NAME . ' ' . __('setup','cftp_admin'),
					);
}

else {
	/**
	 * Check if the ProjectSend is installed. Done only on the log in form
	 * page since all other are inaccessible if no valid session or cookie
	 * is set.
	 */
	$header_vars = array(
						'html_lang'		=> SITE_LANG,
						'title'			=> $page_title . ' &raquo; ' . html_output(THIS_INSTALL_SET_TITLE),
						'header_title'	=> html_output(THIS_INSTALL_SET_TITLE),
					);

	if ( !is_projectsend_installed() ) {
		header("Location:install/index.php");
		exit;
	}

	$load_scripts = array(
						'social_login',
						'recaptcha',
						'chosen',
					);

	/**
	 * This is defined on the public download page.
	 * So even logged in users can access it.
	 */
	if (!isset($dont_redirect_if_logged)) {
		/** If logged as a system user, go directly to the back-end homepage */
		if (in_session_or_cookies($allowed_levels)) {
			header("Location:".BASE_URI."dashboard.php");
		}

		/** If client is logged in, redirect to the files list. */
		check_for_client();
	}
	/**
	 * Silent updates that are needed even if no user is logged in.
	 */
	require_once(ROOT_DIR.'/includes/core.update.silent.php');
}

if ( !isset( $body_class ) ) { $body_class = ''; }
?>
<!doctype html>
<html lang="<?php echo $header_vars['html_lang']; ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<?php meta_noindex(); ?>

	<title><?php echo html_output( $header_vars['title'] ); ?></title>
	<?php meta_favicon(); ?>

	<?php
		$load_theme_css = true;
		require_once( 'assets.php' );

		load_css_files();

		require_jquery();
	?>
</head>

<body <?php echo add_body_class( $body_class ); ?>>
	<div class="container-custom">
		<header id="header" class="navbar navbar-static-top navbar-fixed-top header_unlogged">
			<div class="navbar-header text-center">
				<span class="navbar-brand">
					<?php echo $header_vars['header_title']; ?>
				</span>
			</div>
		</header>

		<div class="main_content_unlogged">
			<div class="container-fluid">
				<div class="row">
