<?php
/**
 * Contains the form and the processes used to install ProjectSend.
 *
 * @package		ProjectSend
 * @subpackage	Install (config check)
 */
error_reporting(E_ALL);

define( 'IS_INSTALL', true );
require_once('../sys.includes.php');

$page_title_install		= __('Install','cftp_admin');

// array of POST variables to check, with associated default value
$post_vars = array(
	'dbdriver'		=> 'mysql',
	'dbname'		=> 'projectsend',
	'dbuser'		=> 'root',
	'dbpassword'	=> 'root',
	'dbhost'		=> 'localhost',
	'dbprefix'		=> 'tbl_',
	'dbreuse'		=> 'no',
	'lang'			=> 'en'
);

// parse all variables in the above array and fill in whatever is sent via POST
foreach ($post_vars as $var=>$value) {
	if (isset($_POST[$var])) {
		$post_vars[$var] = trim(stripslashes((string) $_POST[$var]));
	}
}

//check PDO status
$pdo_available_drivers = PDO::getAvailableDrivers();
$pdo_mysql_available = in_array('mysql', $pdo_available_drivers);
$pdo_mssql_available = in_array('dblib', $pdo_available_drivers);
$pdo_driver_available = $pdo_mysql_available || $pdo_mssql_available;

// only mysql driver is available, let's force it
if ($pdo_mysql_available && !$pdo_mssql_available) {
	$post_vars['dbdriver'] = 'mysql';
}

// only mysql driver is available, let's force it
if (!$pdo_mysql_available && $pdo_mssql_available) {
	$post_vars['dbdriver'] = 'mssql';
}

if ($pdo_driver_available) {
	// check host connection
	$dsn = $post_vars['dbdriver'] . ':host=' . $post_vars['dbhost'] . ';dbname=' . $post_vars['dbname'];
	try{
		$db = new PDO($dsn, $post_vars['dbuser'], $post_vars['dbpassword'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		$pdo_connected = true;
	}
	catch(PDOException $ex){
    	$pdo_connected = false;
	}
}

// names of reserved tables
$table_names = array(
	'actions_log',
	'downloads',
	'files',
	'files_relations',
	'folders',
	'groups',
	'members',
	'notifications',
	'options',
	'password_reset',
	'users',
);

// check if tables exists
$table_exists = false;
if ($pdo_connected) {
	foreach ($table_names as $name) {
		$table_exists = $table_exists || table_exists($db, $post_vars['dbprefix'].$name);
	}
}
$reuse_tables =  $post_vars['dbreuse'] == 'reuse';

// scan for language files
$po_files = scandir(ROOT_DIR.'/lang/');
$langs = array();
foreach ($po_files as $file) {
  if (preg_match("/\.po$/", $file)) {
	  $langs[] = substr($file,0,-3); // removes last 3 characters
  }
}
sort($langs, SORT_STRING);

// ok if selected language has a .po file associated
$lang_ok = in_array($post_vars['lang'], $langs);

// check file & folders are writable
$config_file = ROOT_DIR.'/includes/sys.config.php';
$config_file_writable = is_writable($config_file) || is_writable(dirname($config_file));
$upload_dir = ROOT_DIR.'/upload';
$upload_files_dir = ROOT_DIR.'/upload/files';
$upload_files_dir_writable = is_writable($upload_files_dir) || is_writable($upload_dir);
$upload_temp_dir = ROOT_DIR.'/upload/temp';
$upload_temp_dir_writable = is_writable($upload_temp_dir) || is_writable($upload_dir);

// retrieve user data for web server
if (function_exists('posix_getpwuid')) {
	$server_user_data = posix_getpwuid(posix_getuid());
	$server_user = $server_user_data['name'] . ' (uid=' . $server_user_data['uid'] . ' gid=' . $server_user_data['gid'] . ')';
} else {
	$server_user = getenv('USERNAME');
}

// if everything is ok, we can proceed
$ready_to_go = $pdo_connected && (!$table_exists || $reuse_tables) && $lang_ok && $config_file_writable && $upload_files_dir_writable && $upload_temp_dir_writable;

// if the user requested to write the config file AND we can proceed, we try to write the new configuration
if (isset($_POST['submit-start']) && $ready_to_go) {
	$out = '<?php
define(\'DB_DRIVER\', \''.$post_vars['dbdriver'].'\');
define(\'DB_NAME\', \''.$post_vars['dbname'].'\');
define(\'DB_HOST\', \''.$post_vars['dbhost'].'\');
define(\'DB_USER\', \''.$post_vars['dbuser'].'\');
define(\'DB_PASSWORD\', \''.$post_vars['dbpassword'].'\');
define(\'TABLES_PREFIX\', \''.$post_vars['dbprefix'].'\');
define(\'SITE_LANG\',\''.$post_vars['lang'].'\');
define(\'MAX_FILESIZE\',2048);
define(\'EMAIL_ENCODING\', \'utf-8\');
';
	$result = file_put_contents($config_file, $out, LOCK_EX);
	if ($result > 0) {
		// config file written successfully
		error_reporting(0);
		return;
	}
}


/**
 * Check if a table exists in the current database.
 * (taken from stackoverflow)
 *
 * @param PDO $pdo PDO instance connected to a database.
 * @param string $table Table to search for.
 * @return bool TRUE if table exists, FALSE if no table found.
 */
function table_exists($pdo, $table) {

    // Try a select statement against the table
    // Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
    try {
        $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
    } catch (Exception $e) {
        // We got an exception == table not found
        return FALSE;
    }

    // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
    return $result !== FALSE;
}

include_once('../header-unlogged.php');
?>

		<div class="whitebox whiteform" id="install_form">

			<script type="text/javascript">
				$(document).ready(function() {
				/*	$("form").submit(function() {
						clean_form(this);

						is_complete(this.this_install_title,'<?php echo $install_no_sitename; ?>');
						is_complete(this.base_uri,'<?php echo $install_no_baseuri; ?>');
						is_complete(this.install_user_fullname,'<?php echo $validation_no_name; ?>');
						is_complete(this.install_user_mail,'<?php echo $validation_no_email; ?>');
						// username
						is_complete(this.install_user_username,'<?php echo $validation_no_user; ?>');
						is_length(this.install_user_username,<?php echo MIN_USER_CHARS; ?>,<?php echo MAX_USER_CHARS; ?>,'<?php echo $validation_length_user; ?>');
						is_alpha_or_dot(this.install_user_username,'<?php echo $validation_alpha_user; ?>');
						// password fields
						is_complete(this.install_user_pass,'<?php echo $validation_no_pass; ?>');
						//is_complete(this.install_user_repeat,'<?php echo $validation_no_pass2; ?>');
						is_email(this.install_user_mail,'<?php echo $validation_invalid_mail; ?>');
						is_length(this.install_user_pass,<?php echo MIN_USER_CHARS; ?>,<?php echo MAX_USER_CHARS; ?>,'<?php echo $validation_length_pass; ?>');
						is_password(this.install_user_pass,'<?php $chars = addslashes($validation_valid_chars); echo $validation_valid_pass." ".$chars; ?>');
						//is_match(this.install_user_pass,this.install_user_repeat,'<?php echo $validation_match_pass; ?>');

						// show the errors or continue if everything is ok
						if (show_form_errors() == false) { return false; }
					}); */
				});
			</script>

			<form action="index.php" name="installform" method="post">

				<ul class="form_fields">
					<li>
						<h3><?php _e('Database configuration','cftp_admin'); ?></h3>
						<p><?php _e('You need to provide this data for a correct database communication.','cftp_admin'); ?></p>
					</li>
					<li class="row-fluid">
						<div class="span3">
							<label for="dbdriver"><?php _e('Driver','cftp_admin'); ?></label>
						</div>
						<div class="span8">
							<div class="radio">
								<input type="radio" name="dbdriver" value="mysql" <?php echo !$pdo_mysql_available ? 'disabled' : ''; ?> <?php echo $post_vars['dbdriver'] == 'mysql' ? 'checked' : ''; ?> />
								MySQL <br />
								<?php echo $pdo_mysql_available ? '' : '(not supported)'; ?>
							</div>
							<br>
							<div class="radio">
								<input type="radio" name="dbdriver" value="mssql" <?php echo !$pdo_mssql_available ? 'disabled' : ''; ?> <?php echo $post_vars['dbdriver'] == 'mssql' ? 'checked' : ''; ?> />
								MS SQL Server  <br />
								<?php echo $pdo_mssql_available ? '' : '(not supported)'; ?>
							</div>
						</div>
						<div class="span1">
							<?php if ($pdo_driver_available) : ?>
								<span class="label label-success">OK</span>
							<?php else : ?>
								<span class="label label-important">!</span>
							<?php endif; ?>
						</div>
					</li>
					<li>
						<label for="dbhost"><?php _e('Host','cftp_admin'); ?></label>
						<input type="text" name="dbhost" id="dbhost" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required" value="<?php echo $post_vars['dbhost']; ?>" />
						<?php if ($pdo_connected) : ?>
							<span class="label label-success">OK</span>
						<?php else : ?>
							<span class="label label-important">!</span>
						<?php endif; ?>
					</li>
					<li>
						<label for="dbuser"><?php _e('Username','cftp_admin'); ?></label>
						<input type="text" name="dbuser" id="dbuser" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required" value="<?php echo $post_vars['dbuser']; ?>" />
						<?php if ($pdo_connected) : ?>
							<span class="label label-success">OK</span>
						<?php else : ?>
							<span class="label label-important">!</span>
						<?php endif; ?>
					</li>
					<li>
						<label for="dbpassword"><?php _e('Password','cftp_admin'); ?></label>
						<input type="text" name="dbpassword" id="dbpassword" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required" value="<?php echo $post_vars['dbpassword']; ?>" />
						<?php if ($pdo_connected) : ?>
							<span class="label label-success">OK</span>
						<?php else : ?>
							<span class="label label-important">!</span>
						<?php endif; ?>
					</li>
					<li>
						<label for="dbname"><?php _e('Database name','cftp_admin'); ?></label>
						<input type="text" name="dbname" id="dbname" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required" value="<?php echo $post_vars['dbname']; ?>" />
						<?php if ($pdo_connected) : ?>
							<span class="label label-success">OK</span>
						<?php else : ?>
							<span class="label label-important">!</span>
						<?php endif; ?>
					</li>
					<li>
						<label for="dbprefix"><?php _e('Table prefix','cftp_admin'); ?></label>
						<input type="text" name="dbprefix" id="dbprefix" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required" value="<?php echo $post_vars['dbprefix']; ?>" />
						<?php if ($pdo_connected) : ?>
							<?php if (!$table_exists || $reuse_tables) : ?>
								<span class="label label-success">OK</span>
							<?php else : ?>
								<span class="label label-warning">!</span>
							<?php endif; ?>
							<?php if ($table_exists) : ?>
								<p class="label label-warning"><?php _e('The database is already populated','cftp_admin'); ?></p>
							<?php endif; ?>
						<?php endif; ?>
					</li>

					<?php if ($pdo_connected) : ?>
						<?php if ($table_exists) : ?>
							<li>
								<label for="dbreuse"><?php _e('Reuse existing tables','cftp_admin'); ?></label>
								<input type="checkbox" name="dbreuse" value="reuse" <?php echo $post_vars['dbreuse'] == 'reuse' ? 'checked' : ''; ?> />
								<?php if ($reuse_tables) : ?>
									<span class="label label-success">OK</span>
								<?php endif; ?>
							</li>
						<?php endif; ?>
					<?php endif; ?>


					<li class="options_divide"></li>


					<li>
						<h3><?php _e('Language selection','cftp_admin'); ?></h3>
					</li>
					<li>
						<label for="lang"><?php _e('Language','cftp_admin'); ?></label>
						<select name="lang">
							<?php foreach ($langs as $l) : ?>
								<option value="<?php echo $l;?>" <?php echo $post_vars['lang']==$l ? 'selected' : ''; ?>><?php echo $l;?></option>
							<?php endforeach?>
						</select>
					</li>


					<li class="options_divide"></li>


					<li>
						<h3><?php _e('Folders','cftp_admin'); ?></h3>
					</li>
					<li>
					  <label for="phpversion"><?php _e('Config file','cftp_admin'); ?></label>
					  <?php echo $config_file; ?>
					  <?php if ($config_file_writable) : ?>
						  <span class="label label-success"><?php _e('Writable','cftp_admin'); ?></span>
					  <?php else : ?>
						  <span class="label label-important"><?php _e('Not writable','cftp_admin'); ?></span>
					  <?php endif; ?>
					</li>
					<li>
					  <label for="phpversion"><?php _e('Upload directory','cftp_admin'); ?></label>
					  <?php echo $upload_files_dir; ?>
					  <?php if ($upload_files_dir_writable) : ?>
						  <span class="label label-success"><?php _e('Writable','cftp_admin'); ?></span>
					  <?php else : ?>
						  <span class="label label-important"><?php _e('Not writable','cftp_admin'); ?></span>
					  <?php endif; ?>
					</li>
					<li>
					  <label for="phpversion"><?php _e('Temp directory','cftp_admin'); ?></label>
					  <?php echo $upload_temp_dir; ?>
					  <?php if ($upload_temp_dir_writable) : ?>
						  <span class="label label-success"><?php _e('Writable','cftp_admin'); ?></span>
					  <?php else : ?>
						  <span class="label label-important"><?php _e('Not writable','cftp_admin'); ?></span>
					  <?php endif; ?>
					</li>


					<li class="options_divide"></li>


					<li>
						<h3><?php _e('System','cftp_admin'); ?></h3>
					</li>
					<li>
					  <label for="server_os"><?php _e('Server OS','cftp_admin'); ?></label>
					  <input type="text" name="server_os" id="server_os" disabled value="<?php echo php_uname(); ?>" />
					</li>
					<li>
					  <label for="server_user"><?php _e('Server user','cftp_admin'); ?></label>
					  <input type="text" name="server_user" id="server_user" disabled value="<?php echo $server_user; ?>" />
					</li>
					<li>
					  <label for="phpversion"><?php _e('PHP version','cftp_admin'); ?></label>
					  <input type="text" name="phpversion" id="phpversion" disabled value="<?php echo phpversion(); ?>" />
					</li>
					<li>
					  <label for="sapi_name"><?php _e('PHP SAPI','cftp_admin'); ?></label>
					  <input type="text" name="sapi_name" id="sapi_name" disabled value="<?php echo php_sapi_name(); ?>" />
					</li>
					<li>
					  <label for="memory_limit"><?php _e('Memory limit','cftp_admin'); ?></label>
					  <input type="text" name="memory_limit" id="memory_limit" disabled value="<?php echo ini_get('memory_limit'); ?>" />
					</li>
					<li>
					  <label for="post_max_size"><?php _e('POST max size','cftp_admin'); ?></label>
					  <input type="text" name="post_max_size" id="post_max_size" disabled value="<?php echo ini_get('post_max_size'); ?>" />
					</li>
					<li>
					  <label for="upload_max_filesize"><?php _e('Upload max filesize','cftp_admin'); ?></label>
					  <input type="text" name="upload_max_filesize" id="upload_max_filesize" disabled value="<?php echo ini_get('upload_max_filesize'); ?>" />
					</li>

				</ul>

				<div class="inside_form_buttons">
					<?php if ($ready_to_go) : ?>
						<button type="submit" name="submit" class="btn btn-wide btn-secondary"><?php _e('Check again','cftp_admin'); ?></button>
						<button type="submit" name="submit-start" class="btn btn-wide btn-primary"><?php _e('Write config file','cftp_admin'); ?></button>
					<?php else : ?>
						<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Check','cftp_admin'); ?></button>
					<?php endif; ?>
				</div>

			</form>

		</div>

	</div> <!--main-->

	<?php default_footer_info(); ?>

</body>
</html>
<?php exit; ?>