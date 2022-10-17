<?php
/**
 * ProjectSend is a free, clients-oriented, private file sharing web application.
 * Clients are created and assigned a username and a password. Then you can
 * upload as much files as you want under each account, and optionally add
 * a name and description to them. 
 *
 * ProjectSend is hosted on github.
 * Feel free to participate!
 *
 * @link		https://github.com/projectsend/projectsend
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GNU GPL version 2
 * @package		ProjectSend
 */

// Start session
if (!isset($_SESSION)) session_start();

define('ROOT_DIR', dirname(__FILE__));
define('DS', DIRECTORY_SEPARATOR);
define('CONFIG_FILE_NAME', 'includes'.DS.'sys.config.php');
define('CONFIG_FILE', ROOT_DIR.DS.CONFIG_FILE_NAME);

// Composer autoload
require_once ROOT_DIR . '/vendor/autoload.php';

global $app;
$app = new \ProjectSend\Application;

// Replace with middleware
if (!defined('IS_INSTALL') && !defined('FILE_UPLOADING') && $_POST && !\ProjectSend\Classes\Csrf::validateCsrfToken()) {
    exit_with_error_code(403);
}

$app->run();


// $dbh = get_dbh();
// $auth = get_container_item('auth');
// $flash = get_container_item('flash');
// $bfchecker = get_container_item('bfchecker');
// $hybridauth = get_container_item('hybridauth');