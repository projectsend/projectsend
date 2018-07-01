<?php
/**
 * Define system constants after getting the options from the database
 *
 * @package	ProjectSend
 * @subpackage autoload
 */

 /*Directories and URLs constants */
define('ASSETS_DIR','assets');
define('ASSETS_COMPILED_DIR', ASSETS_DIR . DS . 'dist/');
define('BOWER_DEPENDENCIES_DIR', 'bower_components/');
define('COMPOSER_DEPENDENCIES_DIR', 'vendor/');
define('NPM_DEPENDENCIES_DIR', 'node_modules/');
define('EMAIL_TEMPLATES_DIR', ADMIN_TEMPLATES_DIR . '/emails');
define('TEMPLATES_DIR', ROOT_DIR . '/templates');
define('WIDGETS_DIR', ADMIN_TEMPLATES_DIR . DS . 'widgets');

/* Define the folder where uploaded files will reside */
define('UPLOADED_FILES_ROOT', ROOT_DIR . DS . 'upload');
define('UPLOADED_FILES_DIR', UPLOADED_FILES_ROOT . DS . 'files');
define('THUMBNAILS_FILES_DIR', UPLOADED_FILES_ROOT . DS . 'thumbnails');
define('UPLOADED_FILES_URL', 'upload/files/');

/* Branding */
define('ADMIN_UPLOADS_DIR', UPLOADED_FILES_ROOT . DS . 'admin');
define('LOGO_MAX_WIDTH', 300);
define('LOGO_MAX_HEIGHT', 300);
define('DEFAULT_LOGO_FILENAME', 'projectsend-logo.png');

/* Thumbnails */
define('THUMBS_MAX_WIDTH', 300);
define('THUMBS_MAX_HEIGHT', 300);
define('THUMBS_QUALITY', 90);

/* Selected template path */
define('TEMPLATE_PATH', TEMPLATES_DIR . DS . SELECTED_CLIENTS_TEMPLATE . DS . 'template.php');

/* Assets */
define('ASSETS_IMG_DIR', ROOT_DIR . DS . ASSETS_DIR . DS . 'img/');

/* Default e-mail templates files */
define('EMAIL_TEMPLATE_HEADER', 'header.html');
define('EMAIL_TEMPLATE_FOOTER', 'footer.html');
define('EMAIL_TEMPLATE_NEW_CLIENT', 'new-client.html');
define('EMAIL_TEMPLATE_NEW_CLIENT_SELF', 'new-client-self.html');
define('EMAIL_TEMPLATE_CLIENT_EDITED', 'client-edited.html');
define('EMAIL_TEMPLATE_NEW_USER', 'new-user.html');
define('EMAIL_TEMPLATE_ACCOUNT_APPROVE', 'account-approve.html');
define('EMAIL_TEMPLATE_ACCOUNT_DENY', 'account-deny.html');
define('EMAIL_TEMPLATE_NEW_FILE_BY_USER', 'new-file-by-user.html');
define('EMAIL_TEMPLATE_NEW_FILE_BY_CLIENT', 'new-file-by-client.html');
define('EMAIL_TEMPLATE_PASSWORD_RESET', 'password-reset.html');

/* Recaptcha */
if (
    RECAPTCHA_ENABLED == 1 &&
    !empty(RECAPTCHA_SITE_KEY) &&
    !empty(RECAPTCHA_SECRET_KEY)
)
{
    define('RECAPTCHA_AVAILABLE', true);
}

/**
 * Footable
 * Define the amount of items to show
 * @todo Get this value from a cookie if it exists.
 */
if (!defined('FOOTABLE_PAGING_NUMBER')) {
    define('FOOTABLE_PAGING_NUMBER', '10');
    define('FOOTABLE_PAGING_NUMBER_LOG', '15');
}
if (!defined('RESULTS_PER_PAGE')) {
    define('RESULTS_PER_PAGE', '10');
    define('RESULTS_PER_PAGE_LOG', '15');
}

/** External links */
define('LINK_DOC_RECAPTCHA', 'https://developers.google.com/recaptcha/docs/start');
define('LINK_DOC_GOOGLE_SIGN_IN', 'https://developers.google.com/identity/protocols/OpenIDConnect');

/* Absolute URLs are defined only if ProjectSend is installed */
if (defined('BASE_URI')) {
    /** Directories */
    define('ADMIN_TEMPLATES_URI', BASE_URI . 'app/templates/');
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
    define('INCLUDES_URI', BASE_URI . 'app/includes');
    define('BOWER_DEPENDENCIES_URI', BASE_URI . BOWER_DEPENDENCIES_DIR);
    define('COMPOSER_DEPENDENCIES_URI', BASE_URI . COMPOSER_DEPENDENCIES_DIR);
    define('NPM_DEPENDENCIES_URI', BASE_URI . NPM_DEPENDENCIES_DIR);
    define('ADMIN_UPLOADS_URI', BASE_URI . 'upload/admin/');

    /** Client's landing URI */
    define('CLIENT_VIEW_FILE_LIST_URI_PATH', 'my_files/');
    define('CLIENT_VIEW_FILE_LIST_URI', BASE_URI . CLIENT_VIEW_FILE_LIST_URI_PATH);

    /** Oauth login callback */
    define('OAUTH_LOGIN_CALLBACK_URI', BASE_URI . 'oauth2.php');
    define('LOGIN_CALLBACK_URI_GOOGLE', OAUTH_LOGIN_CALLBACK_URI . '?service=google');
}