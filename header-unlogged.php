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
	$header_vars = array(
						'base_url'	=> '../',
						'html_lang'	=> 'en',
						'title'		=> $page_title_install . ' &raquo ' . SYSTEM_NAME,
						'header'	=> SYSTEM_NAME . ' ' . __('setup','cftp_admin'),
					);
}

else {
	/**
	 * Check if the ProjectSend is installed. Done only on the log in form
	 * page since all other are inaccessible if no valid session or cookie
	 * is set.
	 */
	$header_vars = array(
						'base_url'	=> BASE_URI,
						'html_lang'	=> SITE_LANG,
						'title'		=> $page_title . ' &raquo ' . THIS_INSTALL_SET_TITLE,
						'header'	=> THIS_INSTALL_SET_TITLE,
					);


	if ( !is_projectsend_installed() ) {
		header("Location:install/index.php");
		exit;
	}
	
	/**
	 * This is defined on the public download page.
	 * So even logged in users can access it.
	 */
	if (!isset($dont_redirect_if_logged)) {
		/** If logged as a system user, go directly to the back-end homepage */
		if (in_session_or_cookies($allowed_levels)) {
			header("Location:".BASE_URI."home.php");
		}
	
		/** If client is logged in, redirect to the files list. */
		check_for_client();
	}
	/**
	 * Silent updates that are needed even if no user is logged in.
	 */
	require_once(ROOT_DIR.'/includes/core.update.silent.php');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $header_vars['html_lang']; ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?php echo $header_vars['title']; ?></title>
	<link rel="shortcut icon" href="<?php echo $header_vars['base_url']; ?>/favicon.ico" />
	<script src="<?php echo $header_vars['base_url']; ?>includes/js/jquery.1.12.4.min.js"></script>

	<link rel="stylesheet" media="all" type="text/css" href="<?php echo $header_vars['base_url']; ?>assets/bootstrap/css/bootstrap.min.css" />

	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->

	<link rel="stylesheet" media="all" type="text/css" href="<?php echo $header_vars['base_url']; ?>css/base.css" />
	<link rel="stylesheet" media="all" type="text/css" href="<?php echo $header_vars['base_url']; ?>css/shared.css" />
	<link rel="stylesheet" media="all" type="text/css" href="<?php echo BASE_URI; ?>css/social-login.css" />
	
	<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Open+Sans:400,700,300' rel='stylesheet' type='text/css'>
	<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Abel' rel='stylesheet' type='text/css'>

	<script src="<?php echo $header_vars['base_url']; ?>assets/bootstrap/js/bootstrap.min.js"></script>
	<script src="<?php echo $header_vars['base_url']; ?>includes/js/jquery.validations.js"></script>
</head>

<body>

	<header>
		<div id="header">
			<div id="lonely_logo">
				<h1><?php echo $header_vars['header']; ?></h1>
			</div>
		</div>
		<div id="login_header_low">
		</div>
	</header>

	<div id="main">