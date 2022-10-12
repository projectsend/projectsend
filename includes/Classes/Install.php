<?php
namespace ProjectSend\Classes;

class Install {

    private $installed;
    private $dbh;
    private $router;
    private $file_exists;
    
    public function __construct(\League\Route\Router $router)
    {
        $this->router = $router;
        $this->file_exists = false;

        $this->loadPersonalConfigFile();
    }

    public function addDatabase(Database $database)
    {
        $this->dbh = $database->getPdo();
    }

    public function loadPersonalConfigFile()
    {
        if ( file_exists(CONFIG_FILE) ) {
            require_once CONFIG_FILE;
            $this->file_exists = true;
            return;
        }

        header("Cache-control: private");
        $_SESSION = [];
        session_regenerate_id(true);
        session_destroy();

        if ( !defined( 'IS_MAKE_CONFIG' ) ) {
            $route = $this->router->getNamedRoute('install_make_config_file');
            pax($route->getPath());
        
            if ( defined('IS_INSTALL') ) {
                header('Location:make-config.php');
                exit;
            }

            header('Location:install/make-config.php');
            exit;
        }
    }

    private function isInstalled()
    {
        $tables_need = array(
            TABLE_USERS
        );
    
        $tables_missing = 0;
        foreach ($tables_need as $table) {
            if (!table_exists($this->container->get('db')->getPdo(), $table)) {
                $tables_missing++;
            }
        }
        if ($tables_missing > 0) {
            $route = $this->container->get('router')->getNamedRoute('install');
        }    
    }
}
