<?php
/**
 * System health and status functions
 *
 * @package		ProjectSend
 * @subpackage	Functions
 */

/**
 * Check if ProjectSend is installed by trying to find the main users table.
 * If it is missing, the installation is invalid.
 */
function is_projectsend_installed()
{
	$tables_need = array(
						TABLE_USERS
					);

	$tables_missing = 0;
	/**
	 * This table list is defined on sys.vars.php
	 */
	foreach ($tables_need as $table) {
		if ( !table_exists( $table ) ) {
			$tables_missing++;
		}
	}
	if ($tables_missing > 0) {
		return false;
	}
	else {
		return true;
	}
}

/** Version requirements check */
function check_versions_requirements()
{
    global $dbh;
    global $messages;

    $version_php	= phpversion();
    $version_mysql	= $dbh->query('SELECT version()')->fetchColumn();
    $version_errors = 0;

    /** php */
    if ( version_compare( $version_php, REQUIRED_VERSION_PHP, "<" ) ) {
        $message = sprintf( __('%s minimum version not met. Please upgrade to at least version %s','cftp_admin'), 'php', REQUIRED_VERSION_PHP );
        $messages->add('danger', $message);
        $version_errors++;
    }

    /** mysql */
    if ( DB_DRIVER == 'mysql' ) {
        if ( version_compare( $version_mysql, REQUIRED_VERSION_MYSQL, "<" ) ) {
            $message = sprintf( __('%s minimum version not met. Please upgrade to at least version %s','cftp_admin'), 'MySQL', REQUIRED_VERSION_MYSQL );
            $messages->add('danger', $message);
            $version_errors++;
        }
    }

    if ( $version_errors > 0 ) {
        error_page('versions_requirements_not_met');
    }
}

/** Check for extensions requirements */
function check_extensions_requirements()
{
    global $dbh;
    global $messages;

    $missing_extensions = [];

    // PDO
    $pdo_available_drivers = PDO::getAvailableDrivers();
    if ( (DB_DRIVER == 'mysql') && !defined('PDO::MYSQL_ATTR_INIT_COMMAND') ) {
        $missing_extensions[] = 'pdo_mysql';
    }
    if ( (DB_DRIVER == 'mssql') && !in_array('dblib', $pdo_available_drivers) ) {
        $missing_extensions[] = 'PDO_SQLSRV';
    }

    if ( !empty( $missing_extensions ) )
    {
        $message = '<p><strong>' . __('Missing required extensions') . '</strong></p>';
        $message .= '<p>' . __('Please install and enable the following extensions to continue using the app:', 'cftp_admin') . '</p>';
        $message .= '<ul>';
        foreach ( $missing_extensions as $ext ) {
            $message .= '<li>' . $ext . '</li>';
        }
        $message .= '</ul>';
        $messages->add('danger', $message);

        error_page('extensions_requirements_not_met', $missing_extensions);
    }
}

/** Shows errors and stops execution */
function error_page($type, $missing_extensions = array())
{
    global $dbh;
    global $messages;

    $dont_redirect_if_logged = true;
    $page_title = __('System configuration error', 'cftp_admin');
    include_once ADMIN_TEMPLATES_DIR . DS . 'header-unlogged.php';
    ?>
        <div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">
            <div class="white-box">
                <div class="white-box-interior">
                    <?php
                        // Renders all system messages
                        require_once ADMIN_TEMPLATES_DIR . DS . 'system.messages.php';
                    ?>
                </div>
            </div>
        </div>
    <?php

    include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
    exit;
}