<?php
/**
 * Contains the form and the processes used to install ProjectSend.
 *
 * @package		ProjectSend
 * @subpackage	Install
 */
define( 'IS_INSTALL', true );

define( 'ABS_PARENT', dirname( dirname(__FILE__) ) );
require_once ABS_PARENT . '/bootstrap.php';

global $dbh;

$page_id = 'install';

include_once '../header-unlogged.php';

$form = true;
?>
<div class="row">
    <div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                    if ( isset( $_GET['status'] ) && !empty( $_GET['status'] ) ) {
                        $form = false;

                        if (option_exists('post_install_complete')) {
                            ps_redirect('../index.php');
                        }

                        save_option('post_install_complete', '1');

                        // Run required database upgrades
                        $upgrade = new \ProjectSend\Classes\DatabaseUpgrade;
                        $upgrade->upgradeDatabase(false);

                        switch ( $_GET['status'] ) {
                            case 'success';
                                // Create/Chmod the upload directories to 755 to avoid errors later.
                                $create_errors = [];
                                $chmod_errors = [];
                                $other_errors = [];

                                $directories = array(
                                    'main' => ROOT_DIR.'/upload',
                                    'temp' => ROOT_DIR.'/upload/temp',
                                    'files'	=> ROOT_DIR.'/upload/files',
                                    'thumbnails' => ROOT_DIR.'/upload/thumbnails',
                                );
                                foreach ($directories as $directory) {
                                    if (!file_exists($directory)) {
                                        if (!@mkdir($directory, 0755)) {
                                            $create_errors[] = $directory;
                                        }
                                    }
                                    else {
                                        if (!@chmod($directory, 0755)) {
                                            $chmod_errors[] = $directory;
                                        }
                                    }
                                }
                
                                $other_errors[] = update_chmod_emails();
                                $other_errors[] = chmod_main_files();
                                $other_errors = array_filter($other_errors); // Fix by michaelkrieger

                                if (!empty($create_errors) || !empty($chmod_errors) || $other_errors) {
                                    $msg = '<strong>' . __('Database installation was successful, but errors were encountered.','cftp_admin') . '</strong>';

                                    if (!empty($other_errors)) {
                                        foreach ($other_errors as $error) {
                                            if (is_array($error)) {
                                                foreach ($error as $e) {
                                                    $msg .= '<p>' . $e . '</p>';
                                                }
                                            }
                                            else {
                                                $msg .= '<p>' . $error . '</p>';
                                            }
                                        }
                                    }

                                    if (!empty($create_errors)) {
                                        $msg .= '<p>' . __('The following directories could not be created:','cftp_admin') . '</p>';
                                        foreach ($create_errors as $dir) {
                                            $msg .= '<p>' . $dir . '</p>';
                                        }
                                    }

                                    if (!empty($chmod_errors)) {
                                        $msg .= '<p>' . __('The following directories could not be chmodded to 755:','cftp_admin') . '</p>';
                                        foreach ($chmod_errors as $dir) {
                                            $msg .= '<p>' . $dir . '</p>';
                                        }
                                    }

                                    echo system_message('warning',$msg);
                                }

                                $msg = '<p><strong>' . __('Congratulations! Everything is up and running.','cftp_admin') . '</strong></p>';
                                $msg .= '<p>' . __('You may proceed to log in with your newely created username and password.','cftp_admin') . '</p>';
                                echo system_message('success',$msg);
                ?>
                                <div class="text-center">
                                    <a href="<?php echo BASE_URI; ?>" class="btn btn-primary btn-wide" target="_self">
                                        <?php _e('Log in','cftp_admin'); ?>
                                    </a>
                                </div>
                <?php
                            break;
                        }
                    }
                    else {
                        if (is_projectsend_installed()) {
                            $form = false;
                ?>
                            <h3><?php _e('Already installed','cftp_admin'); ?></h3>
                            <p><?php _e('It seems that ProjectSend is already installed here.','cftp_admin'); ?></p>
                            <p><?php _e('If you want to reinstall, please delete the system tables from the database and come back to the installation form.','cftp_admin'); ?></p>
                            <div class="text-center">
                                <a href="<?php echo BASE_URI; ?>" class="btn btn-primary btn-wide" target="_self">
                                    <?php _e('Go back','cftp_admin'); ?>
                                </a>
                            </div>
                <?php
                        }
                        else {
                            if ($_POST) {
                                $install_title = $_POST['install_title'];
                                $base_uri = $_POST['base_uri'];
                                $admin_name = $_POST['admin_name'];
                                $admin_username = $_POST['admin_username'];
                                $admin_email = $_POST['admin_email'];
                                $admin_pass = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT, [ 'cost' => HASH_COST_LOG2 ]);
                            
                                /**
                                 * The URI must end with a /, so add it if it wasn't posted.
                                 */
                                if (substr($base_uri, -1) != '/') { $base_uri .= '/'; }

                                global $json_strings;

                                /** Begin form validation */
                                $validation = new \ProjectSend\Classes\Validation;
                                $validation->validate_items([
                                    $install_title => [
                                        'required' => ['error' => $json_strings['validation']['install_no_sitename']],
                                    ],
                                    $base_uri => [
                                        'required' => ['error' => $json_strings['validation']['install_no_baseuri']],
                                    ],
                                    $admin_name => [
                                        'required' => ['error' => $json_strings['validation']['no_name']],
                                    ],
                                    $admin_email => [
                                        'required' => ['error' => $json_strings['validation']['no_email']],
                                        'email' => ['error' => $json_strings['validation']['invalid_email']],
                                    ],
                                    $admin_username => [
                                        'required' => ['error' => $json_strings['validation']['no_user']],
                                        'alpha_or_dot' => ['error' => $json_strings['validation']['alpha_user']],
                                        'length' => ['error' => $json_strings['validation']['length_user'], 'min' => MIN_USER_CHARS, 'max' => MAX_USER_CHARS],
                        
                                    ],
                                    $_POST['admin_pass'] => [
                                        'required' => ['error' => $json_strings['validation']['no_pass']],
                                        'password' => ['error' => $json_strings['validation']['alpha_pass']],
                                        'length' => ['error' => $json_strings['validation']['length_pass'], 'min' => MIN_PASS_CHARS, 'max' => MAX_PASS_CHARS],
                                    ],
                                ]);

                                if ($validation->passed()) {
                                    // Call the file that creates the tables and fill it with the data we got previously
                                    define('TRY_INSTALL',true);
                                    include_once ROOT_DIR.'/install/database.php';

                                    // Try to execute each query individually
                                    try_query($install_queries);

                                    // Continue based on the value returned from the above function
                                    if (empty($error_str)) {
                                        /** Record the action log */
                                        $logger = new \ProjectSend\Classes\ActionsLog;
                                        $new_record_action = $logger->addEntry([
                                            'action' => 0,
                                            'owner_id' => 1,
                                            'owner_user' => $admin_username
                                        ]);

                                        ps_redirect('index.php?status=success');
                                    } else {
                                        $msg = __('There was an error writing to the database.','cftp_admin');
                                        $msg .= '<p>' . $error_str . '</p>';
                                        echo system_message('danger',$msg);
                                    }
                                }
                            }

                            // If the form was submitted with errors, show them here
                            if (isset($validation)) {
                                $validation->list_errors();
                            }

                            if ($form == true) {
                        ?>
                                <form action="index.php" name="install_form" id="install_form" method="post" class="form-horizontal">

                                    <h3><?php _e('Basic system options','cftp_admin'); ?></h3>
                                    <p><?php _e("You need to provide this data for a correct system installation. The site name will be visible in the system panel, and the client's lists.",'cftp_admin'); ?><br />
                                        <?php _e("Remember to edit the file",'cftp_admin'); ?> <em>/includes/sys.config.php</em> <?php _e("with your database settings before installing. If the file doesn't exist, you can create it by renaming the dummy file sys.config.sample.php.",'cftp_admin'); ?>
                                    </p>

                                    <div class="form-group">
                                        <label for="install_title" class="col-sm-4 control-label"><?php _e('Site name','cftp_admin'); ?></label>
                                        <div class="col-sm-8">
                                            <input type="text" name="install_title" id="install_title" class="form-control required" value="<?php echo (isset($install_title) ? $install_title : ''); ?>" />
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
                                        <label for="admin_name" class="col-sm-4 control-label"><?php _e('Full name','cftp_admin'); ?></label>
                                        <div class="col-sm-8">
                                            <input type="text" name="admin_name" id="admin_name" class="form-control required" value="<?php echo (isset($admin_name) ? $admin_name : ''); ?>" />
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="admin_email" class="col-sm-4 control-label"><?php _e('E-mail address','cftp_admin'); ?></label>
                                        <div class="col-sm-8">
                                            <input type="text" name="admin_email" id="admin_email" class="form-control required" value="<?php echo (isset($admin_email) ? $admin_email : ''); ?>" />
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="admin_username" class="col-sm-4 control-label"><?php _e('Log in username','cftp_admin'); ?></label>
                                        <div class="col-sm-8">
                                            <input type="text" name="admin_username" id="admin_username" class="form-control required" maxlength="<?php echo MAX_USER_CHARS; ?>" value="<?php echo (isset($admin_username) ? $admin_username : ''); ?>" />
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="admin_pass" class="col-sm-4 control-label"><?php _e('Password','cftp_admin'); ?></label>
                                        <div class="col-sm-8">
                                            <div class="input-group">
                                                <input type="password" name="admin_pass" id="admin_pass" class="form-control password_toggle required" maxlength="<?php echo MAX_PASS_CHARS; ?>" />
                                                <div class="input-group-btn password_toggler">
                                                    <button type="button" class="btn pass_toggler_show"><i class="glyphicon glyphicon-eye-open"></i></button>
                                                </div>
                                            </div>
                                            <button type="button" name="generate_password" id="generate_password" class="btn btn-default btn-sm btn_generate_password" data-ref="admin_pass" data-min="<?php echo MAX_GENERATE_PASS_CHARS; ?>" data-max="<?php echo MAX_GENERATE_PASS_CHARS; ?>"><?php _e('Generate','cftp_admin'); ?></button>
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
</div>

<?php
    include_once '../footer.php';
