<?php
/**
 * Requirements of basic system files.
 *
 * @package ProjectSend
 * @subpackage Core
 */
define('ROOT_DIR', dirname(__FILE__));
define('DS', DIRECTORY_SEPARATOR);
/** Composer autoload */
require_once ROOT_DIR . '/vendor/autoload.php';

/** Basic system constants */
require_once ROOT_DIR.'/includes/app.php';

/** Load the database class */
require_once ROOT_DIR.'/includes/database.php';

/** Flash messages */
require_once ROOT_DIR . '/includes/flash.php';

/** Load the site options */
if ( !defined( 'IS_MAKE_CONFIG' ) ) {
    require_once ROOT_DIR.'/includes/site.options.php';
}

//if (defined('IS_MAKE_CONFIG') || defined('IS_INSTALL')) {
    require_once ROOT_DIR.'/includes/install.constants.php';
//}

/** Load the language class and translation file */
require_once ROOT_DIR.'/includes/language.php';

require_once ROOT_DIR.'/includes/functions.i18n.php';

/** Text strings used on various files */
require_once ROOT_DIR.'/includes/text.strings.php';

/** Basic functions to be accessed from anywhere */
require_once ROOT_DIR.'/includes/functions.php';

/** Assets */
require_once ROOT_DIR.'/includes/functions.assets.php';

/** Options functions */
require_once ROOT_DIR.'/includes/functions.options.php';

/** Require the updates functions */
require_once ROOT_DIR.'/includes/updates.functions.php';

/** Contains the session and cookies validation functions */
require_once ROOT_DIR.'/includes/functions.session.permissions.php';

/** Template list functions */
require_once ROOT_DIR.'/includes/functions.templates.php';

/** User Meta functions */
require_once ROOT_DIR.'/includes/functions.usermeta.php';

/** Contains the current session information */
if ( !defined( 'IS_INSTALL' ) ) {
    require_once ROOT_DIR.'/includes/active.session.php';
}

/** Recreate the function if it doesn't exist. By Alan Reiblein */
require_once ROOT_DIR.'/includes/timezone_identifiers_list.php';

/** Action log functions */
require_once ROOT_DIR.'/includes/functions.actionslog.php';

/** Categories functions */
require_once ROOT_DIR.'/includes/functions.categories.php';

/** Search, filters and actions forms */
require_once ROOT_DIR.'/includes/functions.forms.php';

/** Search, filters and actions forms */
require_once ROOT_DIR.'/includes/functions.groups.php';

/** Public files display functins */
require_once ROOT_DIR.'/includes/functions.public.php';

/** Social login */
if (!defined('IS_INSTALL')) {
    require_once ROOT_DIR.'/includes/hybridauth.php';
}

/** Security */
require_once ROOT_DIR . '/includes/security/csrf.php';

check_server_requirements();

global $bfchecker;
$bfchecker = new \ProjectSend\Classes\BruteForceBlock($dbh);

global $auth;
$auth = new \ProjectSend\Classes\Auth();

global $assets_loader;
$assets_loader = new \ProjectSend\Classes\AssetsLoader();
