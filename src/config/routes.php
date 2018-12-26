<?php
/** Frontend routes */
$app->group('', function () {
    $this->map(['GET', 'POST'], '/', 'auth.controller:login')->setName('login');
    $this->map(['GET', 'POST'], '/register', 'auth.controller:register')->setName('register');
    $this->map(['GET', 'POST'], '/recover-password', 'auth.controller:register')->setName('recover_password');
})->add($container['guest.middleware']);

$app->get('/logout', 'auth.controller:logout')
    ->add($container['auth.middleware']())
    ->setName('logout');

// Installation
$app->map(['GET', 'POST'], '/make-config', 'installation.controller:makeConfig')->setName('make_config');
$app->map(['GET', 'POST'], '/install', 'installation.controller:install')->setName('install');

// Generic Errors
$app->get('/error', 'error.controller:show')->setName('show_error');

// Unauthorized
$app->get('/unauthorized', 'auth.controller:unauthorized')->setName('unauthorized');
