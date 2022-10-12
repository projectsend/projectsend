<?php
namespace ProjectSend;

use \Laminas\Diactoros\ServerRequestFactory;
use \Tamtamchik\SimpleFlash\Flash;
use \ProjectSend\Classes\Locale;
use \ProjectSend\Classes\BruteForceBlock;
use \ProjectSend\Classes\ActionsLog;
use \ProjectSend\Classes\GobalTextStrings;
use \ProjectSend\Classes\Auth;
use \ProjectSend\Classes\AssetsLoader;
use \ProjectSend\Classes\Permissions;
use \ProjectSend\Classes\Csrf;
use \ProjectSend\Classes\Hybridauth;

class Application {
    public $container;

    public function __construct()
    {
        $this->setUpContainer();
        $this->addRouter();
        $this->loadPersonalConfigFile();
        $this->loadSystemConstants();
        $this->addDatabase();

        $check_requirements = new \ProjectSend\Classes\ServerRequirements($this->container->get('db'));

        $this->setUpOptions();
        $this->addDependencies();
    }

    private function setUpContainer()
    {
        $this->container = new \DI\Container();
    }

    private function addRouter()
    {
        // Router
        $request = ServerRequestFactory::fromGlobals(
            $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
        );

        $router = new \League\Route\Router;
        require_once ROOT_DIR . '/includes/routes.php';
        $this->container->set('router', $router);
    }

    private function loadPersonalConfigFile()
    {
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
    }

    private function loadSystemConstants()
    {
        $constants = new \ProjectSend\Classes\SystemConstants;
    }

    private function addDatabase()
    {
        if ( defined('DB_NAME') ) {
            $this->container->set('db', new \ProjectSend\Classes\Database([
                'driver' => DB_DRIVER,
                'host' => DB_HOST,
                'database' => DB_NAME,
                'username' => DB_USER,
                'password' => DB_PASSWORD,
                'port' => DB_PORT,
                'charset' => DB_CHARSET,
            ]));
        }
    }

    public function setUpOptions()
    {
        if (!defined('IS_MAKE_CONFIG')) {
            try {
                $options = new \ProjectSend\Classes\Options($this->container->get('db'));
                $options->setSystemConstants();
                $this->container->set('options', $options);
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    public function addDependencies()
    {
        $this->container->set('flash', new Flash);
        $this->container->set('bfchecker', new BruteForceBlock($this->container->get('db'), $this->container->get('options')));
        $this->container->set('locale', new Locale($this->container->get('options')));
        $this->container->set('actions_logger', new ActionsLog($this->container->get('db')));
        $this->container->set('global_text_strings', new GobalTextStrings);
        $this->container->set('auth', new Auth($this->container->get('db'), $this->container->get('global_text_strings')));
        $this->container->set('assets_loader', new AssetsLoader);
        $this->container->set('permissions', new Permissions);
        $this->container->set('csrf', new Csrf);
        $this->container->set('hybridauth', new Hybridauth);
    }

    public function getContainer()
    {
        return $this->container;
    }
}