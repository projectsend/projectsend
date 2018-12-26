<?php

use Awurth\Slim\Helper\Twig\AssetExtension;
use Awurth\Slim\Helper\Twig\CsrfExtension;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Slim\Csrf\Guard;
use Slim\Flash\Messages;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Twig\Extension\DebugExtension;
use ProjectSend\Classes\Auth;
use ProjectSend\Classes\LogActions;
use ProjectSend\Classes\Options;
use ProjectSend\Classes\Validation;

$container['pdo'] = function ($container) {
    $host = DB_HOST;
    $dbname = DB_NAME;
    $username = DB_USER;
    $password = DB_PASSWORD;
    $charset = 'utf8';
    $collate = 'utf8_unicode_ci';
    $dsn = DB_DRIVER.":host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE $collate"
    ];

    return new PDO($dsn, $username, $password, $options);
};

$container['auth'] = function ($container) {
    $auth = new ProjectSend\Classes\Auth($container);

    return $auth;
};

$container['flash'] = function () {
    return new \Slim\Flash\Messages();
};

$container['csrf'] = function ($container) {
    $guard = new Guard();
    $guard->setFailureCallable($container['csrfFailureHandler']);

    return $guard;
};

$container['validator'] = function () {
    return new ProjectSend\Classes\Validation;
};

$container['twig'] = function ($container) {
    $config = $container['settings']['twig'];

    $twig = new Twig($config['path'], $config['options']);

    $twig->addExtension(new TwigExtension($container['router'], $container['request']->getUri()));
    $twig->addExtension(new DebugExtension());
    $twig->addExtension(new Twig_Extensions_Extension_I18n());
    $twig->addExtension(new AssetExtension($container['request']));
    $twig->addExtension(new CsrfExtension($container['csrf']));

    $twig->getEnvironment()->addGlobal('flash', $container['flash']);
    $twig->getEnvironment()->addGlobal('auth', $container['auth']);

    return $twig;
};

$container['options'] = function ($container) {
    $options = new ProjectSend\Classes\Options($container);

    return $options;
};

$container['messages'] = function ($container) {
    /* System messages shown before the main content (flash) */
    $messages = new ProjectSend\Classes\Messages();

    return $messages;
};

$container['log'] = function ($container) {
    $logger = new ProjectSend\Classes\LogActions($container);

    return $logger;    
};

$container['monolog'] = function ($container) {
    $config = $container['settings']['monolog'];

    $monologger = new Logger($config['name']);
    $monologger->pushProcessor(new UidProcessor());
    $monologger->pushHandler(new StreamHandler($config['path'], $config['level']));

    return $monologger;
};

$container['updater'] = function ($container) {
    $core_updates = new \ProjectSend\UpdatesCore();
    $core_updates->apply_legacy_updates();

    return $core_updates;    
};