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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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

/**
 * Check if the personal configuration file exists
 * Otherwise will start a configuration page
 *
 * @see sys.config.sample.php
 */
if ( !file_exists(CONFIG_FILE) ) {
    header("Cache-control: private");
    $_SESSION = [];
    session_regenerate_id(true);
    session_destroy();

    if ( !defined( 'IS_MAKE_CONFIG' ) ) {
        // the following script returns only after the creation of the configuration file
        if ( defined('IS_INSTALL') ) {
            header('Location:make-config.php');
            exit;
        }

        header('Location:install/make-config.php');
        exit;
    }
} else {
    // Load custom config file
    include_once CONFIG_FILE;
}

// Basic system constants
require_once ROOT_DIR . '/includes/system.constants.php';

if ( defined('DB_NAME') ) {
    $app->containerAdd('db', new \ProjectSend\Classes\Database([
        'driver' => DB_DRIVER,
        'host' => DB_HOST,
        'database' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASSWORD,
        'port' => DB_PORT,
        'charset' => DB_CHARSET,
    ]));
}

// Load the language class and translation file
require_once ROOT_DIR . '/includes/language.php';

// Contains the current session information
if (!defined('IS_INSTALL')) {
    require_once ROOT_DIR . '/includes/active.session.php';
}

// Recreate the function if it doesn't exist. By Alan Reiblein
require_once ROOT_DIR . '/includes/timezone_identifiers_list.php';

if (!defined('IS_ERROR_PAGE')) {
    check_server_requirements();
}

$app->setUpOptions();
$app->containerAdd('flash', new \Tamtamchik\SimpleFlash\Flash);
$app->containerAdd('bfchecker', new \ProjectSend\Classes\BruteForceBlock);
$app->containerAdd('actions_logger', new \ProjectSend\Classes\ActionsLog);
$app->containerAdd('global_text_strings', new \ProjectSend\Classes\GobalTextStrings);
$app->containerAdd('auth', new \ProjectSend\Classes\Auth);
$app->containerAdd('assets_loader', new \ProjectSend\Classes\AssetsLoader);
$app->containerAdd('permissions', new \ProjectSend\Classes\Permissions);
$app->containerAdd('csrf', new \ProjectSend\Classes\Csrf);
$app->containerAdd('hybridauth', new \ProjectSend\Classes\Hybridauth);

require_once ROOT_DIR . '/includes/install.constants.php';

// $dbh = get_dbh();
// $auth = get_container_item('auth');
// $flash = get_container_item('flash');
// $bfchecker = get_container_item('bfchecker');
// $hybridauth = get_container_item('hybridauth');

// Router
$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals(
    $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
);

$router = new League\Route\Router;
require_once ROOT_DIR . '/includes/routes.php';
$app->containerAdd('router', $router);

// Replace with middleware
if (!defined('IS_INSTALL') && !defined('FILE_UPLOADING') && $_POST && !\ProjectSend\Classes\Csrf::validateCsrfToken()) {
    exit_with_error_code(403);
}

$response = new \ProjectSend\Classes\RoutesDispatcher($router);
$response->dispatch($request);
