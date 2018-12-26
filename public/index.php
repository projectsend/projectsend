<?php
/**
 * Requirements of basic system files.
 * It's included in every main page as the app starter.
 * 
 * @todo refactor this file after using a proper router!
 * 
 * @package ProjectSend
 * @subpackage Core
 */

define('ROOT_DIR', __DIR__ . '/..');

define('DS', DIRECTORY_SEPARATOR);

/* App config */
define('CORE_DIR', ROOT_DIR . DS . 'src');
define('APP_CONFIG_DIR', CORE_DIR . DS . "config");
define('SYS_FILE', APP_CONFIG_DIR . DS . "config.php");
require_once SYS_FILE;

/* Composer dependencies */
require_once ROOT_DIR . '/vendor/autoload.php';

$app = new ProjectSend\Application(IS_DEV == true ? 'dev' : 'prod');

/** @todo refactor this */
$requires = [
    "language.functions.php",
    "language.locales.names.php",
    "language.load.php",
    "functions.php",
    "functions.files.php",
    "functions.groups.php",
    "functions.options.php",
    "assets.functions.php",
    "actions.log.functions.php",
    "actions.log.references.php",
    "functions.categories.php",
    "functions.forms.php",
    "templates.functions.php",
    "text.strings.php",
];
foreach ( $requires as $file ) {
    $location = '../src/autoload/' . $file;
    if ( file_exists( $location ) ) {
       //require_once $location;
    }
}

$app->run();

exit;

/** @todo Add middleware */

/* Check if we are on a development version */
if ( IS_DEV == true ) {
    $message = __('You are using a development version. Some features may be unfinished or not working correctly.', 'cftp_admin');
    $messages->add(
        'warning', $message, array(
            'id' => 'dev_version',
            'add_notice' => true,
        )
    );
}

/** Custom functions files */
$requires = [
    'custom.php',
];
foreach ( $requires as $file ) {
    $location = INCLUDES_DIR . DS . $file;
    if ( file_exists( $location ) ) {
        require_once $location;
    }
}