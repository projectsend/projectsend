<?php
/**
 * Contains the form and the processes used to install ProjectSend.
 *
 * @package		ProjectSend
 * @subpackage	Install (config check)
 */

error_reporting(E_ALL);


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
$upload_files_dir = ROOT_DIR.'/upload/files';
$upload_files_dir_writable = is_writable($upload_files_dir);
$upload_temp_dir = ROOT_DIR.'/upload/temp';
$upload_temp_dir_writable = is_writable($upload_temp_dir);

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



?>
<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title>ProjectSend setup</title>
	<link rel="shortcut icon" href="favicon.ico" />
	<script type="text/javascript" src="includes/js/jquery-1.8.3.min.js"></script>

	<link rel="stylesheet" media="all" type="text/css" href="css/bootstrap.min.css" />
	<link rel="stylesheet" media="all" type="text/css" href="css/bootstrap-responsive.min.css" />
	<script type="text/javascript" src="includes/js/bootstrap/bootstrap.min.js"></script>
	<script type="text/javascript" src="includes/js/bootstrap/modernizr-2.6.2-respond-1.1.0.min.js"></script>

	<link rel="stylesheet" media="all" type="text/css" href="css/base.css" />
	<link rel="stylesheet" media="all" type="text/css" href="css/shared.css" />

	<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Open+Sans:400,700,300' rel='stylesheet' type='text/css'>
	<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Abel' rel='stylesheet' type='text/css'>

	<script src="includes/js/jquery.validations.js" type="text/javascript"></script>
	<style>
	.form_fields li label {
		display:inline-block;
		width:25%;
	}
	</style>
	<script type="text/javascript">
		$(document).ready(function() {
			$('.button').click(function() {
				$(this).blur();
			});
		});
	</script>
</head>

<body>

	<header>
		<div id="header">
			<div id="lonely_logo">
				<h1>ProjectSend setup</h1>
			</div>
		</div>
		<div id="login_header_low">
		</div>

	</header>

	<div id="main">

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
									<h3>Database configuration</h3>
									<p>You need to provide this data for a correct database communication.
									</p>
								</li>
								<li class="row-fluid">
									<div class="span3">
										<label for="dbdriver">Driver</label>
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
									<label for="dbhost">Host</label>
									<input type="text" name="dbhost" id="dbhost" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required" value="<?php echo $post_vars['dbhost']; ?>" />
									<?php if ($pdo_connected) : ?>
										<span class="label label-success">OK</span>
									<?php else : ?>
										<span class="label label-important">!</span>
									<?php endif; ?>
								</li>
								<li>
									<label for="dbuser">Username</label>
									<input type="text" name="dbuser" id="dbuser" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required" value="<?php echo $post_vars['dbuser']; ?>" />
									<?php if ($pdo_connected) : ?>
										<span class="label label-success">OK</span>
									<?php else : ?>
										<span class="label label-important">!</span>
									<?php endif; ?>
								</li>
								<li>
									<label for="dbpassword">Password</label>
									<input type="text" name="dbpassword" id="dbpassword" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required" value="<?php echo $post_vars['dbpassword']; ?>" />
									<?php if ($pdo_connected) : ?>
										<span class="label label-success">OK</span>
									<?php else : ?>
										<span class="label label-important">!</span>
									<?php endif; ?>
								</li>
								<li>
									<label for="dbname">DB name</label>
									<input type="text" name="dbname" id="dbname" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required" value="<?php echo $post_vars['dbname']; ?>" />
									<?php if ($pdo_connected) : ?>
										<span class="label label-success">OK</span>
									<?php else : ?>
										<span class="label label-important">!</span>
									<?php endif; ?>
								</li>
								<li>
									<label for="dbprefix">Table prefix</label>
									<input type="text" name="dbprefix" id="dbprefix" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required" value="<?php echo $post_vars['dbprefix']; ?>" />
									<?php if ($pdo_connected) : ?>
										<?php if (!$table_exists || $reuse_tables) : ?>
											<span class="label label-success">OK</span>
										<?php else : ?>
											<span class="label label-warning">!</span>
										<?php endif; ?>
										<?php if ($table_exists) : ?>
											<p class="label label-warning">The database is already populated!</p>
										<?php endif; ?>
									<?php endif; ?>
								</li>

								<?php if ($pdo_connected) : ?>
									<?php if ($table_exists) : ?>
										<li>
											<label for="dbreuse">Reuse existing tables</label>
											<input type="checkbox" name="dbreuse" value="reuse" <?php echo $post_vars['dbreuse'] == 'reuse' ? 'checked' : ''; ?> />
											<?php if ($reuse_tables) : ?>
												<span class="label label-success">OK</span>
											<?php endif; ?>
										</li>
									<?php endif; ?>
								<?php endif; ?>


								<li class="options_divide"></li>


								<li>
									<h3>Language selection</h3>
								</li>
								<li>
									<label for="lang">Language</label>
									<select>
										<?php foreach ($langs as $l) : ?>
										<option value="<?php echo $l;?>" <?php echo $post_vars['lang']==$l ? 'selected' : ''; ?>><?php echo $l;?></option>
										<?php endforeach?>
									</select>
								</li>


								<li class="options_divide"></li>


								<li>
									<h3>Folders</h3>
								</li>
								<li>
								  <label for="phpversion">Config file</label>
								  <?php echo $config_file; ?>
								  <?php if ($config_file_writable) : ?>
									  <span class="label label-success">writable</span>
								  <?php else : ?>
									  <span class="label label-important">not writable</span>
								  <?php endif; ?>
								</li>
								<li>
								  <label for="phpversion">Upload directory</label>
								  <?php echo $upload_files_dir; ?>
								  <?php if ($upload_files_dir_writable) : ?>
									  <span class="label label-success">writable</span>
								  <?php else : ?>
									  <span class="label label-important">not writable</span>
								  <?php endif; ?>
								</li>
								<li>
								  <label for="phpversion">Temporary directory</label>
								  <?php echo $upload_temp_dir; ?>
								  <?php if ($upload_temp_dir_writable) : ?>
									  <span class="label label-success">writable</span>
								  <?php else : ?>
									  <span class="label label-important">not writable</span>
								  <?php endif; ?>
								</li>


								<li class="options_divide"></li>


								<li>
									<h3>System</h3>
								</li>
								<li>
								  <label for="server_os">Server OS</label>
								  <input type="text" name="server_os" id="server_os" disabled value="<?php echo php_uname(); ?>" />
								</li>
								<li>
								  <label for="server_user">Server User</label>
								  <input type="text" name="server_user" id="server_user" disabled value="<?php echo $server_user; ?>" />
								</li>
								<li>
								  <label for="phpversion">PHP Version</label>
								  <input type="text" name="phpversion" id="phpversion" disabled value="<?php echo phpversion(); ?>" />
								</li>
								<li>
								  <label for="sapi_name">PHP SAPI</label>
								  <input type="text" name="sapi_name" id="sapi_name" disabled value="<?php echo php_sapi_name(); ?>" />
								</li>
								<li>
								  <label for="memory_limit">Memory limit</label>
								  <input type="text" name="memory_limit" id="memory_limit" disabled value="<?php echo ini_get('memory_limit'); ?>" />
								</li>
								<li>
								  <label for="post_max_size">POST max size</label>
								  <input type="text" name="post_max_size" id="post_max_size" disabled value="<?php echo ini_get('post_max_size'); ?>" />
								</li>
								<li>
								  <label for="upload_max_filesize">Upload max filesize</label>
								  <input type="text" name="upload_max_filesize" id="upload_max_filesize" disabled value="<?php echo ini_get('upload_max_filesize'); ?>" />
								</li>

							</ul>

							<div class="inside_form_buttons">
								<?php if ($ready_to_go) : ?>
									<button type="submit" name="submit" class="btn btn-wide btn-secondary">Check again</button>
									<button type="submit" name="submit-start" class="btn btn-wide btn-primary">Write config file</button>
								<?php else : ?>
									<button type="submit" name="submit" class="btn btn-wide btn-primary">Check</button>
								<?php endif; ?>
							</div>

						</form>



		</div>

	</div> <!--main-->

	<footer>
		<div id="footer">
			Provided by <a href="http://www.projectsend.org" target="_blank">ProjectSend</a> - Free software
		</div>
	</footer>

</body>
</html>
<?php exit; ?>
