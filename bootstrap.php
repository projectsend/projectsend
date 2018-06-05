<?php

use Psr\Container\ContainerInterface;
use Slim\Container;

require __DIR__ . '/includes/sys.config.php';
require __DIR__ . '/vendor/autoload.php';

function container()
{
    static $container;
    if (!$container) {
        $container = createContainer();
    }
    return $container;
}

function createContainer(): ContainerInterface
{
    $container = new \Slim\Container();
    defineBasis($container);
    defineApp($container);
    defineController($container);
    return $container;
}

function defineBasis(Container $container)
{
    if (true || DEBUG) {
        $container['settings'] = [
            'displayErrorDetails' => true,
        ];
    }

    $container['db'] = function() {
        $class = DEBUG ? \ProjectSend\PDOEx::class : PDO::class;
        /** @var PDO $conn */
        $conn = $conn = new $class(
            DB_DRIVER . ":host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD,
            [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]
        );

        $conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        $conn->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
        return $conn;
    };

    // TODO logger
}

function defineApp(Container $container)
{
    $container['app'] = function(Container $c) {
        return new \Slim\App($c);
    };

    $container[\ProjectSend\ControllerWrapperFactoryInterface::class] = function($c) {
        return new \ProjectSend\ControllerWrapperFactory($c);
    };
}

function defineController(Container $container)
{

}