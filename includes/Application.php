<?php
namespace ProjectSend;

use \Laminas\Diactoros\ServerRequestFactory;
use \League\Route\Router;
use \Tamtamchik\SimpleFlash\Flash;
use \ProjectSend\Classes\Locale;
use \ProjectSend\Classes\BruteForceBlock;
use \ProjectSend\Classes\ActionsLog;
use \ProjectSend\Classes\Install;
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

        $requirements = new \ProjectSend\Classes\ServerRequirements($this->container->get('db'));
        $requirements->checkServerRequirementsOrExit();

        $this->setUpOptions();
        $this->addDependencies();

        if (!$this->container->get('install')->isInstalled())
        {
            $route = $this->container->get('router')->getNamedRoute('install');
            ps_redirect($route);
        }
    }

    private function setUpContainer()
    {
        $this->container = new \DI\Container();
    }

    private function addRouter()
    {
        // Router
        $this->request = ServerRequestFactory::fromGlobals(
            $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
        );

        $router = new Router;
        require_once ROOT_DIR . '/includes/routes.php';
        $this->container->set('router', $router);
    }

    private function loadPersonalConfigFile()
    {
        $this->container->set('install', new Install($this->container->get('router')));
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

        $this->container->get('install')->AddDatabase($this->container->get('db'));
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
        $this->container->set('hybridauth', new Hybridauth($this->container->get('options')));
        $this->container->set('dispatcher', new \ProjectSend\Classes\RoutesDispatcher($this->container->get('router')));
    }

    public function run()
    {
        $this->container->get('dispatcher')->dispatch($this->request);
    }

    public function getContainer()
    {
        return $this->container;
    }
}