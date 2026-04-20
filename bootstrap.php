<?php
/**
 * Requirements of basic system files.
 */
define('ROOT_DIR', dirname(__FILE__));
define('DS', DIRECTORY_SEPARATOR);
define('CONFIG_FILE_NAME', '/includes/sys.config.php');
define('CONFIG_FILE', ROOT_DIR.CONFIG_FILE_NAME);

// Composer autoload
require_once ROOT_DIR . '/vendor/autoload.php';

// Basic system constants
require_once ROOT_DIR . '/includes/app.php';

// Load the database class
require_once ROOT_DIR . '/includes/database.php';

// Flash messages
require_once ROOT_DIR . '/includes/flash.php';

// Load the site options
if (!defined('IS_MAKE_CONFIG')) {
    require_once ROOT_DIR . '/includes/site.options.php';
}

//if (defined('IS_MAKE_CONFIG') || defined('IS_INSTALL')) {
require_once ROOT_DIR . '/includes/install.constants.php';
//}

// Load the language class and translation file
require_once ROOT_DIR . '/includes/language.php';

require_once ROOT_DIR . '/includes/functions.i18n.php';

// Text strings used on various files
require_once ROOT_DIR . '/includes/text.strings.php';

// Basic functions to be accessed from anywhere
require_once ROOT_DIR . '/includes/functions.php';

// Assets
require_once ROOT_DIR . '/includes/functions.assets.php';

// Options functions
require_once ROOT_DIR . '/includes/functions.options.php';

// Require the updates functions (needed by migrations)
require_once ROOT_DIR . '/includes/updates.functions.php';

// CRITICAL: Run database upgrades BEFORE loading sessions/permissions
// This must happen AFTER options functions are loaded (need option_exists())
// but BEFORE active.session.php and Permissions class instantiation
if (!defined('IS_INSTALL') && !defined('IS_ERROR_PAGE')) {
    $db_upgrade = new \ProjectSend\Classes\DatabaseUpgrade;
    $db_upgrade->upgradeDatabase(false);
}

// Contains the session and cookies validation functions
require_once ROOT_DIR . '/includes/functions.session.permissions.php';

// Template list functions
require_once ROOT_DIR . '/includes/functions.templates.php';

// User Meta functions
require_once ROOT_DIR . '/includes/functions.usermeta.php';

// Custom fields functions
require_once ROOT_DIR . '/includes/functions.custom-fields.php';

// Contains the current session information
if (!defined('IS_INSTALL')) {
    require_once ROOT_DIR . '/includes/active.session.php';
}

// Recreate the function if it doesn't exist. By Alan Reiblein
require_once ROOT_DIR . '/includes/timezone_identifiers_list.php';

// Action log functions
require_once ROOT_DIR . '/includes/functions.actionslog.php';

// Categories functions
require_once ROOT_DIR . '/includes/functions.categories.php';

// Search, filters and actions forms
require_once ROOT_DIR . '/includes/functions.forms.php';

// Options forms helper functions
require_once ROOT_DIR . '/includes/functions.forms.options.php';

// Search, filters and actions forms
require_once ROOT_DIR . '/includes/functions.groups.php';

// Public files display functins
require_once ROOT_DIR . '/includes/functions.public.php';

// Theme settings functions
require_once ROOT_DIR . '/includes/functions.theme-settings.php';

// Social login
if (!defined('IS_INSTALL')) {
    require_once ROOT_DIR . '/includes/hybridauth.php';
}

// Security
require_once ROOT_DIR . '/includes/security/csrf.php';

if (!defined('IS_ERROR_PAGE')) {
    check_server_requirements();
}

global $bfchecker;
$bfchecker = new \ProjectSend\Classes\BruteForceBlock();

global $auth;
$auth = new \ProjectSend\Classes\Auth();

global $assets_loader;
$assets_loader = new \ProjectSend\Classes\AssetsLoader();

global $permissions;
$user_id = (user_is_logged_in() && defined('CURRENT_USER_ID')) ? CURRENT_USER_ID : null;
$permissions = new \ProjectSend\Classes\Permissions($user_id);

// Ensure all core permissions exist in database (auto-creation)
\ProjectSend\Classes\Permissions::ensureCorePermissionsExist();
