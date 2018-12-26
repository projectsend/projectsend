<?php

use ProjectSend\Middleware\AuthMiddleware;
use ProjectSend\Middleware\GuestMiddleware;
use ProjectSend\Middleware\InstallationMiddleware;
use ProjectSend\Middleware\OptionsMiddleware;
use ProjectSend\Middleware\CurrentSessionMiddleware;

$container['guest.middleware'] = function ($container) {
    return new GuestMiddleware($container['router'], $container['auth']);
};

$container['auth.middleware'] = function ($container) {
    return function ($role = null) use ($container) {
        return new AuthMiddleware($container['router'], $container['flash'], $container['auth'], $role);
    };
};

$container['installation.middleware'] = function ($container) {
    return new InstallationMiddleware($container);
};

$container['options.middleware'] = function ($container) {
    return new OptionsMiddleware($container['router'], $container['pdo'], $container['options']);
};

$container['currentsession.middleware'] = function ($container) {
    return new CurrentSessionMiddleware($container['router'], $container['pdo']);
};

$app->add($container['csrf']);
