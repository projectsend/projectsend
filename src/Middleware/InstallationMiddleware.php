<?php

namespace ProjectSend\Middleware;

use ProjectSend\Classes\Auth;
use ProjectSend\Classes\Database;
use Slim\Flash\Messages;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Router;
use \PDO;
use Slim\Container;

class InstallationMiddleware implements MiddlewareInterface
{
    private $container;
    protected $pdo;
    protected $router;

    /**
     * Constructor.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->pdo = $container['pdo'];
        $this->router = $container['router'];
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        
        //$installation = new \ProjectSend\Controller\InstallationController($container);
        /* Check php and database engine versions requirements, then extensions */
        //$installation->check_versions_requirements();
        //$installation->check_extensions_requirements();

        /**
         * Check if the configuration file exists
         */
        if ( !file_exists( CONFIG_FILE ) ) {
            if ( !defined( 'IS_MAKE_CONFIG' ) ) {
                return $response->withRedirect($this->router->pathFor('make_config'));
            }
        }

        /**
         * Check if ProjectSend is installed by trying to find the main users table.
         * If it is missing, the installation is invalid.
         */
        $tables_need = array(
            TABLE_USERS
        );

        $tables_missing = 0;
        /**
        * This table list is defined on sys.vars.php
        */
        foreach ($tables_need as $table) {
            $database = new Database($this->pdo);
            if ( !$database->table_exists( $table ) ) {
                $tables_missing++;
            }
        }
        if ($tables_missing > 0) {
            return $response->withRedirect($this->router->pathFor('install'));
        }

        return $next($request, $response);
    }
}
