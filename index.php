<?php
/**
 * ProjectSend (previously cFTP) is a free, clients-oriented, private file
 * sharing web application.
 * Clients are created and assigned a username and a password. Then you can
 * upload as much files as you want under each account, and optionally add
 * a name and description to them. 
 *
 * ProjectSend is hosted on Google Code.
 * Feel free to participate!
 *
 * @link		http://code.google.com/p/clients-oriented-ftp/
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GNU GPL version 2
 * @package		ProjectSend
 *
 */



$allowed_levels = array(9,8,7,0);

require_once('sys.includes.php');

$page_title = __('Log in','cftp_admin');

include('header-unlogged.php');
	//session_destroy();
	/**
	 * Google Sign-in
	 */
	if ( GOOGLE_SIGNIN_ENABLED == '1' ) {
		$googleClient = new Google_Client();
		$googleClient->setApplicationName(THIS_INSTALL_SET_TITLE);
		$googleClient->setClientSecret(GOOGLE_CLIENT_SECRET);
		$googleClient->setClientId(GOOGLE_CLIENT_ID);
		$googleClient->setAccessType('online');
		$googleClient->setApprovalPrompt('auto');
		$googleClient->setRedirectUri(BASE_URI . 'sociallogin/google/callback.php');
		$googleClient->setScopes(array('profile','email'));
		$auth_url = $googleClient->createAuthUrl();
	}
	
	/** The form was submitted */
	if ($_POST) {
	//echo "post";exit;
		global $dbh;
		$sysuser_password	= $_POST['login_form_pass'];
		$selected_form_lang	= $_POST['login_form_lang'];
	
		/** Look up the system users table to see if the entered username exists */
		$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE user= :username OR email= :email");
		$statement->execute(
						array(
							':username'	=> $_POST['login_form_user'],
							':email'	=> $_POST['login_form_user'],
						)
					);
		$count_user = $statement->rowCount();
	//echo '---------'.$count_user;exit();
		if ($count_user > 0){
			/** If the username was found on the users table */
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $row = $statement->fetch() ) {
				$sysuser_username	= $row['user'];
				$db_pass			= $row['password'];
				$user_level			= $row["level"];
				$active_status		= $row['active'];
				$logged_id			= $row['id'];
				$global_name		= $row['name'];
			}
			$check_password = $hasher->CheckPassword($sysuser_password, $db_pass);
			if ($check_password) {
	//		echo $check_password;exit();
			//if ($db_pass == $sysuser_password) {
				if ($active_status != '0') {
					/** Set SESSION values */
					$_SESSION['loggedin_id'] = $logged_id;
					$_SESSION['loggedin'] = $sysuser_username;
					$_SESSION['userlevel'] = $user_level;
					$_SESSION['lang'] = $selected_form_lang;

					/**
					 * Language cookie
					 * TODO: Implement.
					 * Must decide how to refresh language in the form when the user
					 * changes the language <select> field.
					 * By using a cookie and not refreshing here, the user is
					 * stuck in a language and must use it to recover password or
					 * create account, since the lang cookie is only at login now.
					 */
					//setcookie('projectsend_language', $selected_form_lang, time() + (86400 * 30), '/');

					if ($user_level != '0') {
						$access_string = 'admin';
						$_SESSION['access'] = $access_string;
					}
					else {
						$access_string = $sysuser_username;
						$_SESSION['access'] = $sysuser_username;
					}

					/** If "remember me" checkbox is on, set the cookie */
					if (!empty($_POST['login_form_remember'])) {
						/*
						setcookie("loggedin",$sysuser_username,time()+COOKIE_EXP_TIME);
						setcookie("password",$sysuser_password,time()+COOKIE_EXP_TIME);
						setcookie("access",$access_string,time()+COOKIE_EXP_TIME);
						setcookie("userlevel",$user_level,time()+COOKIE_EXP_TIME);
						*/
						setcookie("rememberwho",$sysuser_username,time()+COOKIE_EXP_TIME);
					}
					
					/** Record the action log */
					$new_log_action = new LogActions();
					$log_action_args = array(
											'action' => 1,
											'owner_id' => $logged_id,
											'affected_account_name' => $global_name
										);
					$new_record_action = $new_log_action->log_action_save($log_action_args);

					if ($user_level == '0') {
						header("location:".BASE_URI."my_files/");
					}
					else {
						header("location:home.php");
					}
					exit;
				}
				else {
					$errorstate = 'inactive_client';
				}
			}
			else {
				//$errorstate = 'wrong_password';
				$errorstate = 'invalid_credentials';
			}
		}
		else {
			//$errorstate = 'wrong_username';
			$errorstate = 'invalid_credentials';
		}
	
	}
	//echo "else";exit;

if ( isset($_SESSION['errorstate'] ) ) {
	$errorstate = $_SESSION['errorstate'];
	unset($_SESSION['errorstate']);
}
?>
<?php //echo generate_branding_layout(); ?>

<div id="content" class="container">
  <div class="row">
    <div class="col-xs-12 col-sm-12 col-md-7 col-lg-8 hidden-xs hidden-sm">
      <h1 class="txt-color-red login-header-big"></h1>
<img alt="Logo Placeholder" src="<?php echo BASE_URI?>/includes/timthumb/timthumb.php?src=/img/custom/logo/<?php echo LOGO_FILENAME; ?>&amp;w=220">
      <div class="hero">
        <div class="pull-left login-desc-box-l">
          <h4 class="paragraph-header">It's Okay to be Smart. Experience the simplicity of MicroHealth Send, everywhere you go!</h4>
          <div class="login-app-icons"> <a href="register.php" class="btn btn-danger btn-sm">Not a member? Join now</a><a href="dropoff_guest.php" class="btn btn-danger btn-sm" style="margin-left:5px"><i class="fa fa-upload" aria-hidden="true"></i>&nbsp;&nbsp;drop off</a></div>
        </div>
        <img src="img/demo/iphoneview.png" class="pull-right display-image" alt="" style="width:210px"> </div>
      <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">
          <h5 class="about-heading">About MicroHealth Send - Are you up to date?</h5>
          <p> Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa. </p>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">
          <h5 class="about-heading">Not just your average template!</h5>
          <p> Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi voluptatem accusantium! </p>
        </div>
      </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-5 col-lg-4">
      <div class="well no-padding"> 
        <script type="text/javascript">
							$(document).ready(function() {
								$("form").submit(function() {
									clean_form(this);
					
									is_complete(this.login_form_user,'<?php _e('Username was not completed','cftp_admin'); ?>');
									is_complete(this.login_form_pass,'<?php _e('Password was not completed','cftp_admin'); ?>');
					
									// show the errors or continue if everything is ok
									if (show_form_errors() == false) { return false; }
								});
							});
						</script>
        <form action="index.php" method="post" name="login_admin" role="form" id="login-form" class="smart-form client-form">
          <header> Sign In </header>
          <?php
							/**
							 * Show login errors
							 */
							if (isset($errorstate)) {
								switch ($errorstate) {
									case 'invalid_credentials':
										$login_err_message = __("The supplied credentials are not valid.",'cftp_admin');
										break;
									case 'wrong_username':
										$login_err_message = __("The supplied username doesn't exist.",'cftp_admin');
										break;
									case 'wrong_password':
										$login_err_message = __("The supplied password is incorrect.",'cftp_admin');
										break;
									case 'inactive_client':
										$login_err_message = __("This account is not active.",'cftp_admin');
										if (CLIENTS_AUTO_APPROVE == 0) {
											$login_err_message .= ' '.__("If you just registered, please wait until a system administrator approves your account.",'cftp_admin');
										}
										break;
									case 'no_self_registration':
										$login_err_message = __('Client self registration is not allowed. If you need an account, please contact a system administrator.','cftp_admin');
										break;
									case 'no_account':
										$login_err_message = __('Sign-in with Google cannot be used to create new accounts at this time.','cftp_admin');
										break;
									case 'access_denied':
										$login_err_message = __('You must approve the requested permissions to sign in with Google.','cftp_admin');
										break;
								}
				
								echo system_message('error',$login_err_message,'login_error');
							}
						?>
          <fieldset>
            <section>
              <label class="label">E-mail / Username</label>
              <label class="input"> <i class="icon-append fa fa-user"></i>
                <input type="text" name="login_form_user" id="login_form_user" value="<?php if (isset($sysuser_username)) { echo htmlspecialchars($sysuser_username); } ?>" class="form-control" />
                <b class="tooltip tooltip-top-right"><i class="fa fa-user txt-color-teal"></i> Please enter email address/username</b></label>
            </section>
            <section>
              <label class="label">Password</label>
              <label class="input"> <i class="icon-append fa fa-lock"></i>
                <input type="password" name="login_form_pass" id="login_form_pass" class="form-control" />
                <b class="tooltip tooltip-top-right"><i class="fa fa-lock txt-color-teal"></i> Enter your password</b> </label>
              <div class="note"> <a href="<?php echo BASE_URI; ?>reset-password.php">Forgot password?</a> </div>
            </section>
            <section>
              <label for="login_form_lang">
                <?php _e('Language','cftp_admin'); ?>
              </label>
              <select name="login_form_lang" id="login_form_lang" class="form-control">
                <?php
											// scan for language files
											$available_langs = get_available_languages();
											foreach ($available_langs as $filename => $lang_name) {
										?>
                <option value="<?php echo $filename;?>" <?php echo ( LOADED_LANG == $filename ) ? 'selected' : ''; ?>>
                <?php
														echo $lang_name;
														if ( $filename == SITE_LANG ) {
															echo ' [' . __('default','cftp_admin') . ']';
														}
													?>
                </option>
                <?php
											}
										?>
              </select>
            </section>
            <section>
              <label class="checkbox">
                <input type="checkbox" name="remember" checked="">
                <i></i>Stay signed in</label>
            </section>
          </fieldset>
          <footer>
            <button type="submit" name="submit" class="btn  btn-primary">
            <?php _e('Log in','cftp_admin'); ?>
            </button>
          </footer>
        </form>
      </div>
      <h5 class="text-center"> - Or sign in using -</h5>
      <ul class="list-inline text-center">
	    
		<?php
/*
		require_once('simplesamlsp/lib/_autoload.php');
		$auth = new SimpleSAML_Auth_Simple('default-sp');
		$login_url =  $auth->getLoginURL();
		if (!$auth->isAuthenticated()) {
			print('<li><a href="'.htmlspecialchars($login_url).'">SAML</a></li>');
		}else{
			$attributes = $auth->getAttributes();
			echo "Attributes present!!";
			echo "<pre>";print_r($attributes);echo "</pre>";	
			$url = $auth->getLogoutURL();
			print('<li><a href="' . htmlspecialchars($url) . '">SAML Logout</a></li>');
		}

*/		
		print('<li><a href="https://msend.microhealthllc.com/saml_app">SAML</a></li>');
        ?>
        
        <li>
          <?php if(GOOGLE_SIGNIN_ENABLED == '1'): ?>
          <a href="<?php echo $auth_url; ?>" name="Sign in with Google" class="btn btn-default btn-circle"><i class="fa fa-google"></i></a></a>
          <?php endif; ?>
        </li>
        <?php if(FACEBOOK_SIGNIN_ENABLED == '1'): ?>
        <li> <a href="sociallogin/facebook_special/fbconfig.php" name="Sign in with Facebook" class="btn btn-primary btn-circle" title="facebook"><i class="fa fa-facebook"></i></a> </li>
        <?php endif; ?>
        <?php if(TWITTER_SIGNIN_ENABLED == '1'): ?>
        <li> <a href="sociallogin/login-with.php?provider=Twitter" name="Sign in with Twitter" class="btn btn-info btn-circle" title="twitter"><i class="fa fa-twitter"></i></a> </li>
        <?php endif; ?>
        <?php if(YAHOO_SIGNIN_ENABLED == '1'): ?>
        <li> <a href="sociallogin/login-with.php?provider=yahoo" name="Sign in with yahoo" class="btn btn-danger btn-circle" title="Yahoo"><i class="fa fa-yahoo" aria-hidden="true"></i></a> </li>
        <?php endif; ?>
        <?php if(LINKEDIN_SIGNIN_ENABLED == '1'): ?>
        <li> <a href="sociallogin/login-with.php?provider=LinkedIn" name="Sign in with linkedin" class="btn btn-warning btn-circle" title="Linkedin"><i class="fa fa-linkedin"></i></a> </li>
        <?php endif; ?>
		<?php if(LDAP_SIGNIN_ENABLED == '1'): ?>
        <li> <a href="sociallogin/ldap-login.php" name="Sign in with LDAP" class="btn btn-success btn-circle" id="display_ldap_form" title="LDAP"> <i class="fa fa-universal-access"></i></a> </li>
		<?php endif; ?>
        <?php if(WINDOWS_SIGNIN_ENABLED == '1'): ?>
        <li> <a href="#" name="office365" class="btn btn-default btn-circle" id="office365" onclick="signIn()" title="Sign in with office 365"> <i class="fa fa-windows"></i></a> </li>
        <?php endif; ?>
      </ul>
<!--	<div id="ldap_login_div" style="display:none">
		<div id="message"></div>
		Email<br/>
		<input type="text" id="ldap_email" placeholder="email" value="riemann@ldap.forumsys.com"><br/>
		Password:<br/>						
		<input type="password" id="ldap_password" placeholder="password" value="password"><br/>
		<a href="#" id="ldap_submit">LOGIN</a>

	</div>-->
            <div id="ldap_login_div" style="display:none">
            <div class="well no-padding"> 
            <form role="form" id="login-form1" class="smart-form client-form">
            <fieldset>
            	<section>
                    <div id="message"></div>
                    <label class="label">Email</label>
                    <label class="input"> <i class="icon-append fa fa-user"></i>
                    <input type="text" id="ldap_email" placeholder="email" value="riemann@ldap.forumsys.com" class="form-control">
                    </label>
                    </section>
                    <section>
                    <label class="label">Password</label>	
                    <label class="input"> <i class="icon-append fa fa-lock"></i>				
                    <input type="password" id="ldap_password" placeholder="password" value="password" class="form-control">
                    </label>
                    </section>
                    <a href="#" id="ldap_submit" class="btn  btn-primary">LOGIN</a>
            	
            </fieldset>
            </form>
            </div>
		</div>
    </div>
  </div>
</div>
<!------------------------------------------------------------------------------------------------>
</div>
<!-- main (from header) -->

<div class="cc-footer">
<script type="text/javascript">
function validateEmail($email) {
  var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
  if( !emailReg.test( $email ) ) {
    return false;
  } else {
    return true;
  }
}

	$(document).ready(function(){
		$('#display_ldap_form').click(function(e){
			e.preventDefault();
			$('#ldap_login_div').toggle();
			$('#ldap_submit').click(function(){
					var email = $('#ldap_email').val();
					var password = $('#ldap_password').val();
					//alert(email+' '+password);
					if(email){
					if(validateEmail(email)){		
					$.ajax({
						type:'POST',
						url:'<?php echo BASE_URI; ?>ldap_ajax.php',
						dataType:'html',
						data:{email:email,password:password}
					}).done(function(data){
						if(data="success"){
							     window.location = "<?php echo BASE_URI;?>sociallogin/ldap-login.php?email="+email;
						}else{
							
						}
			
					});
				}else{
			$('#message').text('Not a valid email');
		}}else{
			$('#message').text('The Email field is required');
		}
		});
					
		});
	});
</script>
  <?php
		default_footer_info( false );

		load_js_files();
	?>
</div>
</body></html><?php
	$dbh = null;
	ob_end_flush();
?>
<!-- Trigger the modal with a button 
<button type="button" class="btn btn-info btn-lg" data-toggle="modal" data-target="#cc-ldap">Open Modal</button>-->

<!-- Modal -->
<div id="cc-ldap" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Sign In with LDAP</h4>
      </div>
      <div class="modal-body">
        <!----------------------------------------------------------------------->
        <div id="ldap_login_div">
            <div class="well no-padding"> 
            <fieldset>
            	<section>
                    <div id="message"></div>
                    <label class="label">Email</label>
                    <label class="input"> <i class="icon-append fa fa-user"></i>
                    <input type="text" id="ldap_email" placeholder="email" value="riemann@ldap.forumsys.com" class="form-control">
                    <label class="label">Password</label>	
                    <label class="input"> <i class="icon-append fa fa-lock"></i>				
                    <input type="password" id="ldap_password" placeholder="password" value="password" class="form-control"><br/>
                    <a href="#" id="ldap_submit" class="btn  btn-primary">LOGIN</a>
            	</section>
            </fieldset>
            </div>
		</div>
        <!----------------------------------------------------------------------->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>

  </div>
  </div>
  

