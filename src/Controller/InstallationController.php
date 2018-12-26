<?php

namespace ProjectSend\Controller;

use Awurth\Slim\Helper\Controller\Controller;
use Slim\Http\Request;
use Slim\Http\Response;
use ProjectSend\Classes\Database;

class InstallationController extends Controller
{

    public function makeConfig(Request $request, Response $response)
    {
        define('IS_INSTALL', true);
    }


    public function install(Request $request, Response $response)
    {
        define('IS_INSTALL', true);
        if ($request->isPost()) {
        }

        return $this->render($response, 'install/install.twig');
    }


    /** Version requirements check */
    protected function check_versions_requirements()
    {
        global $messages;

        $version_php	= phpversion();
        $version_mysql	= $this->dbh->query('SELECT version()')->fetchColumn();
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
    protected function check_extensions_requirements()
    {
        global $dbh;
        global $messages;

        $missing_extensions = [];

        // PDO
        $pdo_available_drivers = \PDO::getAvailableDrivers();
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
    protected function error_page($type, $missing_extensions = array())
    {
        return $this->render($response, 'install/requirements-error.twig', compact('missing_extensions'));
    }
}
