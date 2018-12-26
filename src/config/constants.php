<?php
/**
 * Define system constants after getting the options from the database
 *
 * @package	ProjectSend
 * @subpackage autoload
 */

/* Selected template path */
define('SELECTED_TEMPLATE_MAIN_FILE', TEMPLATES_DIR . DS . SELECTED_CLIENTS_TEMPLATE . DS . 'template.php');

/* Recaptcha */
if (
    RECAPTCHA_ENABLED == 1 &&
    !empty(RECAPTCHA_SITE_KEY) &&
    !empty(RECAPTCHA_SECRET_KEY)
)
{
    define('RECAPTCHA_AVAILABLE', true);
}

/* Absolute URLs are defined only if ProjectSend is installed */
if (defined('BASE_URI')) {
    /** Directories */
    define('ADMIN_TEMPLATES_URI', BASE_URI . 'src/view/');
    define('EMAIL_TEMPLATES_URI', ADMIN_TEMPLATES_URI . 'emails/');
    define('TEMPLATES_URI', BASE_URI . '/templates');

    /* Widgets files */
    define('WIDGETS_URL', ADMIN_TEMPLATES_URI . 'widgets/');

    /* Thumbnails */
    define('THUMBNAILS_FILES_URL', BASE_URI.'upload/thumbnails');

    /** Landing page for public groups and files */
    define('PUBLIC_DOWNLOAD_URI', BASE_URI.'download.php');
    define('PUBLIC_LANDING_URI', BASE_URI.'public.php');
    define('PUBLIC_GROUP_URI', BASE_URI.'public.php');
    define('ASSETS_CSS_URI', BASE_URI . ASSETS_COMPILED_DIR . 'css/');
    define('ASSETS_JS_URI', BASE_URI . ASSETS_COMPILED_DIR . 'js/');
    define('ASSETS_IMG_URI', BASE_URI . ASSETS_DIR . DS . 'img/');
    define('INCLUDES_URI', BASE_URI . 'src/includes');
    define('BOWER_DEPENDENCIES_URI', BASE_URI . BOWER_DEPENDENCIES_DIR);
    define('COMPOSER_DEPENDENCIES_URI', BASE_URI . COMPOSER_DEPENDENCIES_DIR);
    define('NPM_DEPENDENCIES_URI', BASE_URI . NPM_DEPENDENCIES_DIR);
    define('ADMIN_UPLOADS_URI', BASE_URI . 'upload/admin/');

    /** Client's landing URI */
    //define('CLIENT_VIEW_FILE_LIST_URI_PATH', 'my_files/');
    define('CLIENT_VIEW_FILE_LIST_URI_PATH', 'private.php');
    define('CLIENT_VIEW_FILE_LIST_URI', BASE_URI . CLIENT_VIEW_FILE_LIST_URI_PATH);

    /** Oauth login callback */
    define('OAUTH_LOGIN_CALLBACK_URI', BASE_URI . 'oauth2.php');
    define('LOGIN_CALLBACK_URI_GOOGLE', OAUTH_LOGIN_CALLBACK_URI . '?service=google');
}