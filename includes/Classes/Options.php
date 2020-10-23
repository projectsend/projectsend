<?php
/**
 * Get custom system options from the database
 */

namespace ProjectSend\Classes;

class Options
{
    public $options;
    private $dbh;

    function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;
    }

    /**
	 * Gets the values from the options table, which has 2 columns.
	 * The first one is the option name, and the second is the assigned value.
	 *
	 * @return array
	 */
	public function getOption($option)
	{
        if (empty($option)) {
            return false;
        }

		try {
			$this->statement = $this->dbh->prepare("SELECT value FROM " . TABLE_OPTIONS . " WHERE name = :option");
            $this->statement->bindParam(':option', $option);
            $this->statement->execute();
            $this->results = $this->statement->fetch();

            $value = $this->results['value'];

			if ((!empty($value)) ) {
				return $value;
			}
		}
		catch ( Exception $e ) {
			return false;
        }
	}

    /**
	 * Gets the values from the options table, which has 2 columns.
	 * The first one is the option name, and the second is the assigned value.
	 *
	 * @return array
	 */
	private function getOptions()
	{
		$this->options = array();
		try {
			$this->query = $this->dbh->query("SELECT * FROM " . TABLE_OPTIONS);
			$this->query->setFetchMode(\PDO::FETCH_ASSOC);

			if ( $this->query->rowCount() > 0) {
				while ( $this->row = $this->query->fetch() ) {
					$this->options[$this->row['name']] = $this->row['value'];
				}
			}

			return $this->options;
		}
		catch ( Exception $e ) {
			return false;
        }
	}

	/**
	 * Makes the options available to the app
	 */
	public function getAll()
	{
		$this->options = $this->getOptions();

		/** In case an option should not be set as a const */
		$this->exceptions = [
		];

		if ( !empty( $this->options ) ) {
			/**
			 * Set a const for each value on the options table
			 */
			foreach ( $this->options as $this->name => $this->value ) {
				if ( in_array( $this->name, $this->exceptions ) ) {
					continue;
                }
                
				$const = strtoupper( $this->name );
				define( $const, $this->value );
			}

			/**
			 * Set the default timezone based on the value of the Timezone select box
			 * of the options page.
			 */
            date_default_timezone_set(TIMEZONE);
            
            /** Options that do not come from the db */
            define('TEMPLATE_PATH',ROOT_DIR.DS.'templates'.DS.SELECTED_CLIENTS_TEMPLATE.DS.'template.php');

            /* Recaptcha */
            if (
				RECAPTCHA_ENABLED == 1 &&
				!empty(RECAPTCHA_SITE_KEY) &&
				!empty(RECAPTCHA_SECRET_KEY)
			) {
			    define('RECAPTCHA_AVAILABLE', true);
            }

            /* Landing page for public groups and files */
            define('PUBLIC_DOWNLOAD_URL',BASE_URI.'download.php');
            define('PUBLIC_LANDING_URI',BASE_URI.'public.php');
            define('PUBLIC_GROUP_URL',BASE_URI.'public.php');

            /* URLs */
            define('THUMBNAILS_FILES_URL', BASE_URI.'upload/thumbnails');
            define('EMAIL_TEMPLATES_URL', BASE_URI.'emails/');
            define('TEMPLATES_URL', BASE_URI.'templates/');
        
            /* Widgets */
            define('WIDGETS_URL',BASE_URI.'includes/widgets/');
            
            /* Logo Uploads */
            define('ADMIN_UPLOADS_URI', BASE_URI . 'upload/admin/');
        
            /* Assets */
            define('ASSETS_URL',BASE_URI . 'assets');
            define('ASSETS_CSS_URL', ASSETS_URL . '/css');
            define('ASSETS_IMG_URL', ASSETS_URL . '/img');
            define('ASSETS_JS_URL', ASSETS_URL . '/js');
            define('ASSETS_LIB_URL', ASSETS_URL . '/lib');

            /** Client's landing URI */
            define('CLIENT_VIEW_FILE_LIST_URL_PATH', 'my_files/index.php');
            define('CLIENT_VIEW_FILE_LIST_URL', BASE_URI . CLIENT_VIEW_FILE_LIST_URL_PATH);

            /* Set a page for each status code */
            define('STATUS_PAGES_DIR', ADMIN_VIEWS_DIR . DS . 'http_status_pages');
            define('PAGE_STATUS_CODE_URL', BASE_URI . 'error.php');
            define('PAGE_STATUS_CODE_403', PAGE_STATUS_CODE_URL . '?e=403');
            define('PAGE_STATUS_CODE_404', PAGE_STATUS_CODE_URL . '?e=404');
		} else {
			define('BASE_URI', '/');
        }
	}

	/**
	 * Save to the database
	 */
	public function save($options)
	{
	}
}