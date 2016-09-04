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

/**
 * Google Sign-in
 */
$googleClient = new Google_Client();
$googleClient->setApplicationName(THIS_INSTALL_SET_TITLE);
$googleClient->setClientSecret(GOOGLE_CLIENT_SECRET);
$googleClient->setClientId(GOOGLE_CLIENT_ID);
$googleClient->setRedirectUri(BASE_URI . 'sociallogin/google/callback.php');
$googleClient->setScopes(array('profile','email'));
$auth_url = $googleClient->createAuthUrl();
	
	/** The form was submitted */
	if ($_POST) {
		global $dbh;
		$sysuser_password = $_POST['login_form_pass'];
	
		/** Look up the system users table to see if the entered username exists */
		$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE BINARY user= :username OR BINARY email= :email");
		$statement->execute(
						array(
							':username'	=> $_POST['login_form_user'],
							':email'	=> $_POST['login_form_user'],
						)
					);
		$count_user = $statement->rowCount();
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
			//if ($db_pass == $sysuser_password) {
				if ($active_status != '0') {
					/** Set SESSION values */
					$_SESSION['loggedin'] = $sysuser_username;
					$_SESSION['userlevel'] = $user_level;

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

if(isset($_SESSION['errorstate'])) {
	$errorstate = $_SESSION['errorstate'];
	unset($_SESSION['errorstate']);
}

?>

		<h2><?php echo $page_title; ?></h2>
		
		<div class="container">
			<div class="row">
				<div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-4 col-md-offset-4 white-box">
					<div class="white-box-interior">
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
								}
				
								echo system_message('error',$login_err_message,'login_error');
							}
						?>
					
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
					
						<form action="index.php" method="post" name="login_admin" role="form">
							<fieldset>
								<div class="form-group">
									<label for="login_form_user"><?php _e('Username','cftp_admin'); ?> / <?php _e('E-mail','cftp_admin'); ?></label>
									<input type="text" name="login_form_user" id="login_form_user" value="<?php if (isset($sysuser_username)) { echo htmlspecialchars($sysuser_username); } ?>" class="form-control" />
								</div>

								<div class="form-group">
									<label for="login_form_pass"><?php _e('Password','cftp_admin'); ?></label>
									<input type="password" name="login_form_pass" id="login_form_pass" class="form-control" />
								</div>
<?php
/*
								<label for="login_form_remember">
									<input type="checkbox" name="login_form_remember" id="login_form_remember" value="on" />
									<?php _e('Remember me','cftp_admin'); ?>
								</label>
*/?>
								<div class="inside_form_buttons">
									<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Continue','cftp_admin'); ?></button>
									<?php if(GOOGLE_SIGNIN_ENABLED == '1'): ?>
										<a href="<?php echo $auth_url; ?>" name="Sign in with Google" class="google-login"><img src="<?php echo BASE_URI; ?>img/google/btn_google_signin_light_normal_web.png" alt="Google Signin" /></a>
									<?php endif; ?>
								</div>
							</fieldset>
						</form>
			
						<div class="login_form_links">
							<p id="reset_pass_link"><?php _e("Forgot your password?",'cftp_admin'); ?> <a href="<?php echo BASE_URI; ?>reset-password.php"><?php _e('Set up a new one.','cftp_admin'); ?></a></p>
							<?php
								if (CLIENTS_CAN_REGISTER == '1') {
							?>
									<p id="register_link"><?php _e("Don't have an account yet?",'cftp_admin'); ?> <a href="<?php echo BASE_URI; ?>register.php"><?php _e('Register as a new client.','cftp_admin'); ?></a></p>
							<?php
								} else {
							?>
									<p><?php _e("This server does not allow self registrations.",'cftp_admin'); ?></p>
									<p><?php _e("If you need an account, please contact a server administrator.",'cftp_admin'); ?></p>
							<?php
								}
							?>
						</div>
					</div>
				</div>
			</div>	
		</div> <!-- container -->
	</div> <!-- main (from header) -->

	<?php default_footer_info(false); ?>

</body>
</html>
<?php
	$dbh = null;
	ob_end_flush();
?>