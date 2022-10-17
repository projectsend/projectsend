<?php
namespace ProjectSend\Classes;

class SystemConstants
{
    public function __construct()
    {
        $this->setUp();
    }

    private function setUp()
    {
        /**
         * ProjectSend system constants
         *
         * This file includes the most basic system options that cannot be
         * changed through the web interface, such as the version number,
         * php directives and the user and password length values.
         */
        
        /**
         * Define the system name, and the information that will be used
         * on the footer blocks.
         *
         */
        define('SYSTEM_NAME','ProjectSend');
        define('SYSTEM_URI','https://www.projectsend.org/');
        define('SYSTEM_URI_LABEL','ProjectSend on github');
        define('DONATIONS_URL','https://www.projectsend.org/donations/');
        
        /**
         * Current version.
         * Updated only when releasing a new downloadable complete version.
         */
        define('CURRENT_VERSION', 'r1584');
        
        /**
         * Required software versions
         */
        define('REQUIRED_VERSION_PHP', '7.4');
        define('REQUIRED_VERSION_MYSQL', '5.0');
        
        /**
         * Fix for including external files when on HTTPS.
         * Contribution by Scott Wright on
         * http://code.google.com/p/clients-oriented-ftp/issues/detail?id=230
         */
        define('PROTOCOL', empty($_SERVER['HTTPS'])? 'http' : 'https');
        
        /**
         * DEBUG
         */
        if (!defined("DEBUG")) {
            define('DEBUG', true);
        }
        
        /**
         * IS_DEV is set to true during development to show a sitewide remainder
         * of the app unreleased status.
         */
        define('IS_DEV', false);
        
        /**
         * This constant holds the current default charset
         */
        define('CHARSET', 'UTF-8');
        
        /**
         * Turn off reporting of PHP errors, warnings and notices.
         * On a development environment, it should be set to E_ALL for
         * complete debugging.
         *
         * @link http://www.php.net/manual/en/function.error-reporting.php
         */
        if ( DEBUG === true ) {
            ini_set('display_errors', 'On');
            ini_set('error_reporting', 'E_ALL');
            ini_set('display_startup_errors', 'On');
            error_reporting(E_ALL);
        }
        else {
            error_reporting(0);
        }
        
        define('GLOBAL_TIME_LIMIT', 240*60);
        define('UPLOAD_TIME_LIMIT', 120*60);
        @set_time_limit(GLOBAL_TIME_LIMIT);
        
        /**
         * Define the RSS url to use on the home news list.
         */
        define('NEWS_FEED_URI','https://www.projectsend.org/serve/news');
        
        /**
         * Define the Feed from where to take the latest version
         * number.
         */
        define('UPDATES_FEED_URI','https://projectsend.org/serve/versions');
        
        /**
         * Database connection driver
         */
        if (!defined('DB_DRIVER')) { define('DB_DRIVER', 'mysql'); }
        if (!defined('DB_PORT')) { define('DB_PORT', '3306'); }
        if (!defined('DB_CHARSET')) { define('DB_CHARSET', 'utf8'); }
        
        /**
         * This values affect both validation methods (client and server side)
         * and also the maxlength value of the form fields.
         */
        define('MIN_USER_CHARS', 5);
        define('MAX_USER_CHARS', 60);
        define('MIN_PASS_CHARS', 5);
        define('MAX_PASS_CHARS', 60);
        
        define('MIN_GENERATE_PASS_CHARS', 10);
        define('MAX_GENERATE_PASS_CHARS', 20);
        /*
        * Cookie expiration time (in seconds).
        * Set by default to 30 days (60*60*24*30).
        */
        define('COOKIE_EXP_TIME', 60*60*24*30);
        
        /* Password recovery */
        define('PASSWORD_RECOVERY_TOKEN_EXPIRATION_TIME', 60*60*24);
        
        /**
         * Time (in seconds) after which the session becomes invalid.
         * Default is disabled and time is set to a huge value (1 month)
         * Case uses must be analyzed before enabling this function
         */
        define('SESSION_TIMEOUT_EXPIRE', true);
        $session_expire_time = 31*24*60*60; // 31 days * 24 hours * 60 minutes * 60 seconds
        define('SESSION_EXPIRE_TIME', $session_expire_time);
        
        /* Define the folder where uploaded files will reside */
        define('UPLOADED_FILES_ROOT', ROOT_DIR . DS . 'upload');
        define('UPLOADED_FILES_DIR', UPLOADED_FILES_ROOT . DS . 'files');
        define('UPLOADS_TEMP_DIR', UPLOADED_FILES_ROOT . DS . 'temp');
        define('THUMBNAILS_FILES_DIR', UPLOADED_FILES_ROOT . DS . 'thumbnails');
        define('UPLOADED_FILES_URL', 'upload/files/');
        define('XACCEL_FILES_URL', '/serve-file');
        
        /* Assets */
        define('ASSETS_DIR', ROOT_DIR . DS . 'assets');
        define('ASSETS_CSS_DIR', ASSETS_DIR . DS . 'css');
        define('ASSETS_IMG_DIR', ASSETS_DIR . DS . 'img');
        define('ASSETS_JS_DIR', ASSETS_DIR . DS . 'js');
        define('ASSETS_LIB_DIR', ASSETS_DIR . DS . 'lib');
        
        /** Directories */
        define('CORE_LANG_DIR', ROOT_DIR . DS . 'lang');
        define('INCLUDES_DIR', ROOT_DIR . DS . 'includes');
        define('FORMS_DIR', INCLUDES_DIR . DS . 'forms');
        define('LAYOUT_DIR', INCLUDES_DIR . DS . 'layout');
        define('UPGRADES_DIR', INCLUDES_DIR . DS . 'upgrades');
        define('EMAIL_TEMPLATES_DIR', ROOT_DIR . DS . 'emails');
        define('TEMPLATES_DIR', ROOT_DIR . DS . 'templates');
        define('JSON_CACHE_DIR', ROOT_DIR . DS . 'cache');
        define('VIEWS_DIR', ROOT_DIR . DS . 'views');
        define('VIEWS_PUBLIC_DIR', VIEWS_DIR . DS . 'public');
        define('VIEWS_ADMIN_DIR', VIEWS_DIR . DS . 'admin');
        define('VIEWS_PARTS_DIR', VIEWS_DIR . DS . 'parts');
        
        /* Branding */
        define('ADMIN_UPLOADS_DIR', UPLOADED_FILES_ROOT . DS . 'admin');
        define('LOGO_MAX_WIDTH', 300);
        define('LOGO_MAX_HEIGHT', 300);
        define('DEFAULT_LOGO_FILENAME', 'projectsend-logo.svg');
        
        /* Thumbnails */
        define('THUMBS_MAX_WIDTH', 300);
        define('THUMBS_MAX_HEIGHT', 300);
        define('THUMBS_QUALITY', 90);
        
        /* Widgets */
        define('WIDGETS_FOLDER',ROOT_DIR.'/includes/widgets/');
        
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
        define('EMAIL_TEMPLATE_2FA_CODE', '2fa-code.html');
        
        /** Passwords */
        define('HASH_COST_LOG2', 8);
        define('HASH_PORTABLE', false);
        
        /** ZIP files */
        define('ZIP_TMP_EXPIRATION_TIME', 172800); // Delete zip files from the temp folder older than this value (in seconds)
        
        /** cURL timeout */
        define('CURL_TIMEOUT_SECONDS', 5);
        
        /**
         * Footable
         * Define the amount of items to show
         * @todo Get this value off a cookie if it exists.
         */
        define('FOOTABLE_PAGING_NUMBER', '10');
        define('FOOTABLE_PAGING_NUMBER_LOG', '15');
        
        /**
         * External links
         */
        define('LINK_DOC_RECAPTCHA', 'https://developers.google.com/recaptcha/intro');
        define('LINK_DOC_GOOGLE_SIGN_IN', 'https://developers.google.com/identity/protocols/OpenIDConnect');
        define('LINK_DOC_FACEBOOK_LOGIN', 'https://developers.facebook.com/docs/facebook-login/');
        define('LINK_DOC_LINKEDIN_LOGIN', 'https://www.linkedin.com/developers/');
    }
}
