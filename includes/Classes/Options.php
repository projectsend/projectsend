<?php

/**
 * Get custom system options from the database
 */

namespace ProjectSend\Classes;

class Options
{
    private $dbh;

    function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;
    }

    /**
     * Gets the values from the options table, which has 2 columns.
     * The first one is the option name, and the second is the assigned value.
     */
    public function getOption($option = null)
    {
        if (empty($option)) {
            return null;
        }

        if (empty($this->dbh)) {
            return null;
        }

        try {
            $statement = $this->dbh->prepare("SELECT value FROM " . TABLE_OPTIONS . " WHERE name = :option");
            $statement->bindParam(':option', $option);
            $statement->execute();
            $results = $statement->fetch();

            $value = $results['value'];

            if ((!empty($value))) {
                return $value;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Makes the options available to the app
     */
    public function setSystemConstants()
    {
        $base_uri = (empty($this->getOption('base_uri'))) ? '/' : $this->getOption('base_uri');
        define('BASE_URI', $base_uri);

        // Set the default timezone based on the value of the Timezone select box of the options page
        $timezone = $this->getOption('timezone');
        if (!empty($timezone)) {
            date_default_timezone_set($this->getOption('timezone'));
        }

        // Landing page for public groups and files
        define('PUBLIC_DOWNLOAD_URL', BASE_URI . 'download.php');
        define('PUBLIC_LANDING_URI', BASE_URI . 'public.php');
        define('PUBLIC_GROUP_URL', BASE_URI . 'public.php');

        // Cron
        define('CRON_COMMAND_EXAMPLE', '* /5 * * * /usr/bin/php ' . ROOT_DIR . '/cron.php key=' . $this->getOption('cron_key') . '  >/dev/null');
        define('CRON_URL', BASE_URI . 'cron.php?key=' . $this->getOption('cron_key'));

        // URLs
        define('THUMBNAILS_FILES_URL', BASE_URI . 'upload/thumbnails');
        define('EMAIL_TEMPLATES_URL', BASE_URI . 'emails/');
        define('TEMPLATES_URL', BASE_URI . 'templates/');

        // Widgets
        define('WIDGETS_URL', BASE_URI . 'includes/widgets/');

        // Logo Uploads
        define('ADMIN_UPLOADS_URI', BASE_URI . 'upload/admin/');

        // Assets
        define('ASSETS_URL', BASE_URI . 'assets');
        define('ASSETS_CSS_URL', ASSETS_URL . '/css');
        define('ASSETS_IMG_URL', ASSETS_URL . '/img');
        define('ASSETS_JS_URL', ASSETS_URL . '/js');
        define('ASSETS_LIB_URL', ASSETS_URL . '/lib');

        // Client's landing URI
        define('CLIENT_VIEW_FILE_LIST_URL_PATH', 'my_files/index.php');
        define('CLIENT_VIEW_FILE_LIST_URL', BASE_URI . CLIENT_VIEW_FILE_LIST_URL_PATH);

        // Set a page for each status code
        define('STATUS_PAGES_DIR', ADMIN_VIEWS_DIR . DS . 'http_status_pages');
        define('PAGE_STATUS_CODE_URL', BASE_URI . 'error.php');
        define('PAGE_STATUS_CODE_403', PAGE_STATUS_CODE_URL . '?e=403');
        define('PAGE_STATUS_CODE_404', PAGE_STATUS_CODE_URL . '?e=404');
    }
}
