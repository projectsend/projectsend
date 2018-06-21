<?php
/**
 * Contains the form and the processes used to install ProjectSend.
 *
 * @package		ProjectSend
 * @subpackage	Install
 * 
 * @todo make an Install Class
 */
define( 'IS_INSTALL', true );

define( 'ABS_PARENT', dirname( dirname(__FILE__) ) );
require_once( ABS_PARENT . '/sys.includes.php' );

/** Version requirements check */
$version_php	= phpversion();
$version_mysql	= $dbh->query('SELECT version()')->fetchColumn();

/** php */
$version_not_met =  __('minimum version not met. Please upgrade to at least version','cftp_admin');
if ( version_compare( $version_php, REQUIRED_VERSION_PHP, "<" ) ) {
	$error_msg[] = 'php' . ' ' . $version_not_met . ' ' . REQUIRED_VERSION_PHP;
}
/** mysql */
if ( version_compare( $version_mysql, REQUIRED_VERSION_MYSQL, "<" ) ) {
	$error_msg[] = 'MySQL' . ' ' . $version_not_met . ' ' . REQUIRED_VERSION_MYSQL;
}

if ( !empty( $error_msg ) ) {
    include_once ADMIN_TEMPLATES_DIR . DS . 'header-unlogged.php';
?>
	<div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">
		<div class="white-box">
			<div class="white-box-interior">
				<?php
					foreach ( $error_msg as $msg ) {
						echo system_message( 'error', $msg );
					}
				?>
			</div>
		</div>
	</div>
<?php
	include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
	exit;
}

global $dbh;
/**
 * Function that takes an array of SQL queries and executes them in order.
 */
function try_query($queries)
{
	global $dbh;

	if ( empty( $error_str ) ) {
		global $error_str;
	}
	foreach ($queries as $i => $value) {
		try {
			$statement = $dbh->prepare( $queries[$i]['query'] );
			$params = $queries[$i]['params'];
			if ( !empty( $params ) ) {
				foreach ( $params as $name => $value ) {
					$statement->bindValue( $name, $value );
				}
			}
			$statement->execute( $params );
		} catch (Exception $e) {
			$error_str .= $e . '<br>';
		}
	}
	return $statement;
}

/** Collect data from form */
if ($_POST) {
	$this_install_title	= $_POST['this_install_title'];
	$base_uri			= $_POST['base_uri'];
	$got_admin_name		= $_POST['install_user_fullname'];
	$got_admin_username	= $_POST['install_user_username'];
	$got_admin_email	= $_POST['install_user_mail'];
	$got_admin_pass 	= password_hash($_POST['install_user_pass'], PASSWORD_DEFAULT, [ 'cost' => HASH_COST_LOG2 ]);
	//$got_admin_pass	= $hasher->HashPassword($_POST['install_user_pass']);
	//$got_admin_pass	= md5($_POST['install_user_pass']);
	//$got_admin_pass2	= md5($_POST['install_user_repeat']);
}

/** Define the installation text strings */
$page_title_install		= __('Install','cftp_admin');

include_once ADMIN_TEMPLATES_DIR . DS . 'header-unlogged.php';
?>

<div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">
	<div class="white-box">
		<div class="white-box-interior">

			<?php
				if ( isset( $_GET['status'] ) && !empty( $_GET['status'] ) ) {
					switch ( $_GET['status'] ) {
						case 'success';
							$msg = __('Congratulations! Everything is up and running.','cftp_admin');
							echo system_message('ok',$msg);
							?>
								<p><?php _e('You may proceed to','cftp_admin'); ?> <a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('log in','cftp_admin'); ?></a> <?php _e('with your newely created username and password.','cftp_admin'); ?></p>
							<?php
						break;
					}
				}
				else {
					if (is_projectsend_installed()) {
			?>
						<h3><?php _e('Already installed','cftp_admin'); ?></h3>
						<p><?php _e('It seems that ProjectSend is already installed here.','cftp_admin'); ?></p>
						<p><?php _e('If you want to reinstall, please delete the system tables from the database and come back to the installation form.','cftp_admin'); ?></p>
			<?php
					}
					else {
						if ($_POST) {

							/**
							 * The URI must end with a /, so add it if it wasn't posted.
							 */
							if ($base_uri{(strlen($base_uri) - 1)} != '/') { $base_uri .= '/'; }
							/** Begin form validation */
							$validation->validate('completed',$this_install_title,$json_strings['validation']['install_no_sitename']);
							$validation->validate('completed',$base_uri,$json_strings['validation']['install_no_baseuri']);
							$validation->validate('completed',$got_admin_name,$json_strings['validation']['no_name']);
							$validation->validate('completed',$got_admin_email,$json_strings['validation']['no_email']);
							/** Username validation */
							$validation->validate('completed',$got_admin_username,$json_strings['validation']['no_user']);
							$validation->validate('length',$got_admin_username,$json_strings['validation']['length_user'],MIN_USER_CHARS,MAX_USER_CHARS);
							$validation->validate('alpha_dot',$got_admin_username,$json_strings['validation']['alpha_user']);
							/** Password fields validation */
							$validation->validate('completed',$_POST['install_user_pass'],$json_strings['validation']['no_pass']);
							//$validation->validate('completed',$_POST['install_user_repeat'],$json_strings['validation']['no_pass2']);
							$validation->validate('email',$got_admin_email,$json_strings['validation']['invalid_email']);
							$validation->validate('length',$_POST['install_user_pass'],$json_strings['validation']['length_pass'],MIN_USER_CHARS,MAX_USER_CHARS);
							$validation->validate('password',$_POST['install_user_pass'],$json_strings['validation']['alpha_pass']);
							//$validation->validate('pass_match','',$json_strings['validation']['match_pass'],'','',$_POST['install_user_pass'],$_POST['install_user_repeat']);

							if ($validation->return_val) {
								/**
								 * Call the file that creates the tables and fill it with the data we got previously
								 */
								define('TRY_INSTALL',true);
								include_once(ROOT_DIR.'/install/database.php');
								/**
								 * Try to execute each query individually
								 */
								try_query($install_queries);
								/**
								 * Continue based on the value returned from the above function
								 */
								if (!empty($error_str)) {
									$query_state = 'err';
								}
								else {
									$query_state = 'ok';
								}
							}

						}
					?>

					<?php
						if(isset($validation)) {
							/** If the form was submited with errors, show them here */
							$validation->list_errors();
						}

						if (isset($query_state)) {
							switch ($query_state) {
								case 'ok':
									/**
									 * Create/Chmod the upload directories to 755 to avoid
									 * errors later.
									 */
									$up_folders = array(
															'main'	=> ROOT_DIR.'/upload',
															'temp'	=> ROOT_DIR.'/upload/temp',
															'files'	=> ROOT_DIR.'/upload/files'
														);
									foreach ($up_folders as $work_folder) {
										if (!file_exists($work_folder)) {
											mkdir($work_folder, 0755);
										}
										else {
											chmod($work_folder, 0755);
										}
									}

									update_chmod_emails();
									chmod_main_files();

									/** Record the action log */
									$new_log_action = new ProjectSend\LogActions();
									$log_action_args = array(
															'action' => 0,
															'owner_id' => 1,
															'owner_user' => $got_admin_name
														);
									$new_record_action = $new_log_action->log_action_save($log_action_args);

									$location = 'index.php?status=success';
									header("Location: $location");
									die();
								break;
								case 'err':
									$msg = __('There seems to be an error. Please try again.','cftp_admin');
									$msg .= '<p>';
									$msg .= $error_str;
									$msg .= '</p>';
									echo system_message('error',$msg);
								break;
							}
						}

						else {
						?>

							<script type="text/javascript">
								$(document).ready(function() {
									$("form").submit(function() {
										clean_form(this);

										is_complete(this.this_install_title,'<?php echo $json_strings['validation']['install_no_sitename']; ?>');
										is_complete(this.base_uri,'<?php echo $json_strings['validation']['install_no_baseuri']; ?>');
										is_complete(this.install_user_fullname,'<?php echo $json_strings['validation']['no_name']; ?>');
										is_complete(this.install_user_mail,'<?php echo $json_strings['validation']['no_email']; ?>');
										// username
										is_complete(this.install_user_username,'<?php echo $json_strings['validation']['no_user']; ?>');
										is_length(this.install_user_username,<?php echo MIN_USER_CHARS; ?>,<?php echo MAX_USER_CHARS; ?>,'<?php echo $json_strings['validation']['length_user']; ?>');
										is_alpha_or_dot(this.install_user_username,'<?php echo $json_strings['validation']['alpha_user']; ?>');
										// password fields
										is_complete(this.install_user_pass,'<?php echo $json_strings['validation']['no_pass']; ?>');
										//is_complete(this.install_user_repeat,'<?php echo $json_strings['validation']['no_pass2']; ?>');
										is_email(this.install_user_mail,'<?php echo $json_strings['validation']['invalid_email']; ?>');
										is_length(this.install_user_pass,<?php echo MIN_USER_CHARS; ?>,<?php echo MAX_USER_CHARS; ?>,'<?php echo $json_strings['validation']['length_pass']; ?>');
										is_password(this.install_user_pass,'<?php echo $json_strings['validation']['valid_pass'] . " " . addslashes($json_strings['validation']['valid_chars']); ?>');
										//is_match(this.install_user_pass,this.install_user_repeat,'<?php echo $json_strings['validation']['match_pass']; ?>');

										// show the errors or continue if everything is ok
										if (show_form_errors() == false) { return false; }
									});
								});
							</script>

							<form action="index.php" name="installform" method="post" class="form-horizontal">

								<h3><?php _e('Basic system options','cftp_admin'); ?></h3>
								<p><?php _e("You need to provide this data for a correct system installation. The site name will be visible in the system panel, and the client's lists.",'cftp_admin'); ?><br />
									<?php _e("Remember to edit the file",'cftp_admin'); ?> <em>/includes/sys.config.php</em> <?php _e("with your database settings before installing. If the file doesn't exist, you can create it by renaming the dummy file sys.config.sample.php.",'cftp_admin'); ?>
								</p>

								<div class="form-group">
									<label for="this_install_title" class="col-sm-4 control-label"><?php _e('Site name','cftp_admin'); ?></label>
									<div class="col-sm-8">
										<input type="text" name="this_install_title" id="this_install_title" class="form-control required" value="<?php echo (isset($this_install_title) ? $this_install_title : ''); ?>" />
									</div>
								</div>

								<div class="form-group">
									<label for="base_uri" class="col-sm-4 control-label"><?php _e('ProjectSend URI (address)','cftp_admin'); ?></label>
									<div class="col-sm-8">
										<input type="text" name="base_uri" id="base_uri" class="form-control required" value="<?php echo (isset($base_uri) ? $base_uri : get_current_url()); ?>" />
									</div>
								</div>

								<div class="options_divide"></div>

								<h3><?php _e('Default system administrator options','cftp_admin'); ?></h3>
								<p><?php _e("This info will be used to create a default system user, which can't be deleted afterwards. Password should be between",'cftp_admin'); ?> <strong><?php echo MIN_PASS_CHARS; ?> <?php _e("and",'cftp_admin'); ?> <?php echo MAX_PASS_CHARS; ?> <?php _e("characters long.",'cftp_admin'); ?></strong></p>

								<div class="form-group">
									<label for="install_user_fullname" class="col-sm-4 control-label"><?php _e('Full name','cftp_admin'); ?></label>
									<div class="col-sm-8">
										<input type="text" name="install_user_fullname" id="install_user_fullname" class="form-control required" value="<?php echo (isset($got_admin_name) ? $got_admin_name : ''); ?>" />
									</div>
								</div>

								<div class="form-group">
									<label for="install_user_mail" class="col-sm-4 control-label"><?php _e('E-mail address','cftp_admin'); ?></label>
									<div class="col-sm-8">
										<input type="text" name="install_user_mail" id="install_user_mail" class="form-control required" value="<?php echo (isset($got_admin_email) ? $got_admin_email : ''); ?>" />
									</div>
								</div>

								<div class="form-group">
									<label for="install_user_username" class="col-sm-4 control-label"><?php _e('Log in username','cftp_admin'); ?></label>
									<div class="col-sm-8">
										<input type="text" name="install_user_username" id="install_user_username" class="form-control required" maxlength="<?php echo MAX_USER_CHARS; ?>" value="<?php echo (isset($got_admin_username) ? $got_admin_username : ''); ?>" />
									</div>
								</div>


								<div class="form-group">
									<label for="install_user_pass" class="col-sm-4 control-label"><?php _e('Password','cftp_admin'); ?></label>
									<div class="col-sm-8">
										<div class="input-group">
											<input type="password" name="install_user_pass" id="install_user_pass" class="form-control password_toggle required" maxlength="<?php echo MAX_PASS_CHARS; ?>" />
											<div class="input-group-btn password_toggler">
												<button type="button" class="btn pass_toggler_show"><i class="glyphicon glyphicon-eye-open"></i></button>
											</div>
										</div>
										<button type="button" name="generate_password" id="generate_password" class="btn btn-default btn-sm btn_generate_password" data-ref="install_user_pass" data-min="<?php echo MAX_GENERATE_PASS_CHARS; ?>" data-max="<?php echo MAX_GENERATE_PASS_CHARS; ?>"><?php _e('Generate','cftp_admin'); ?></button>
									</div>
								</div>

								<div class="form-group">
									<div class="inside_form_buttons">
										<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Install','cftp_admin'); ?></button>
									</div>
								</div>

								<div id="install_extra">
									<p><?php _e('After installing the system, you can go to the options page to set your timezone, preferred date display format and thumbnails parameters, besides being able to change the site options provided here.','cftp_admin'); ?></p>
								</div>

							</form>
				<?php
						}
					}
				}
			?>
		</div>
	</div>
</div>


<?php
	include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
