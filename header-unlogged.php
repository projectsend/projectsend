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
	define( 'BASE_URI', '../' );

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
						'title'			=> $page_title . ' &raquo; ' . THIS_INSTALL_SET_TITLE,
						'header_title'	=> THIS_INSTALL_SET_TITLE,
					);

	if ( !is_projectsend_installed() ) {
		header("Location:install/index.php");
		exit;
	}
	
	if(isset($active_nav) != 'dropoff') {
	$load_scripts = array(
						'social_login',
						'recaptcha',
					);
	}
	else {
		 $load_scripts	= array(
						'plupload',
					);
	}
	
	/**
	 * This is defined on the public download page.
	 * So even logged in users can access it.
	 */
	if (!isset($dont_redirect_if_logged)) { 
		if(isset($active_nav) != 'dropoff') {
		/** If logged as a system user, go directly to the back-end homepage */
		if (in_session_or_cookies($allowed_levels)) {
			header("Location:".BASE_URI."home.php");
		}
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
<!doctype html>
<html lang="<?php echo $header_vars['html_lang']; ?>" id="extr-page">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?php echo html_output( $header_vars['title'] ); ?></title>
	<link rel="shortcut icon" href="<?php echo BASE_URI; ?>favicon.ico" />
	<script src="<?php echo BASE_URI; ?>includes/js/jquery.1.12.4.min.js"></script>

    	<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    
<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->

	<?php
		require_once( 'assets.php' );

		load_css_files();
	?>


<!-- script for office 365 -->
<script src="https://secure.aadcdn.microsoftonline-p.com/lib/1.0.12/js/adal.min.js"></script>
<script>
  var ADAL = new AuthenticationContext({
      instance: 'https://login.microsoftonline.com/',
      tenant: 'common', //COMMON OR YOUR TENANT ID

      clientId: '<?php echo WINDOWS_CLIENT_ID; ?>', //This is your client ID
	  // msend a301616f-53ad-45da-b63f-9ba907ccc66f
	  //pw local aohLgNis3YpAdFWTLS2WgzF
      //redirectUri: 'http://localhost:3333/ms/oauth.php', //This is your redirect URI
	  redirectUri: 'https://msend.microhealthllc.com',

      callback: userSignedIn,
      popUp: true
  });

  function signIn() {
      ADAL.login();
  }

  function userSignedIn(err, token) {
      console.log('userSignedIn called');
      if (!err) {
          console.log("token: " + token);
          showWelcomeMessage();
      }
      else {
          console.error("error: " + err);
      }
  }

  function showWelcomeMessage() {
      var user = ADAL.getCachedUser();
	  //console.log(user.profile);
      //var divWelcome = document.getElementById('WelcomeMessage');
      //divWelcome.innerHTML = "Welcome " + user.profile.name + "Email : " + user.userName;
	  
	  var data = {provider:"office365", user:user.profile.name, useremail:user.userName};
	  //console.log(data);
	  
	  $.ajax({ url: '<?php echo BASE_URI; ?>sociallogin/login-with.php',
         data: data,
         type: 'get',
         success: function(output) {
					//alert(output);
                 //    console.log('vvvvvv '+output);
					  if(output == 0) { 
					  //console.log('showw '+output);
						  //$("#office365").show();
					  }
					  else if (output == 1) {
						  location.reload();
					  }
					  location.reload();
                  }
});
  }

</script> 
<!-- script for office 365 -->
</head>

<body class="desktop-detected pace-done animated fadeInDown" id="unlogged">
<header id="header">

			<div id="logo-group">
				<span id="logo"> <a class="title-link" href="<?php echo BASE_URI; ?>" ?><?php echo BRAND_NAME; ?></a></span>
			</div>
<?php
 /*?>								if (CLIENTS_CAN_REGISTER == '1') {
							?>
									<span id="extr-page-header-space"> <span><?php _e("Don't have an account yet?",'cftp_admin'); ?></span> <a class="btn btn-danger cc-signup" href="<?php echo BASE_URI; ?>register.php"><?php _e('Register as a new client','cftp_admin'); ?></a></span>
							<?php
								} else {
							?>
									<span><?php _e("This server does not allow self registrations.",'cftp_admin'); ?></span>
									<span><?php _e("If you need an account, please contact a server administrator.",'cftp_admin'); ?></span>
							<?php
								}
							?><?php */?>

</header>

	<div id="main">
