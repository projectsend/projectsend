<?php
$router->get('/', '\ProjectSend\Controllers\AuthController::login')->setName('login');
$router->get('/reset-password', '\ProjectSend\Controllers\AuthController::resetPassword')->setName('resetPassword');
$router->post('/reset-password', '\ProjectSend\Controllers\AuthController::resetPassword')->setName('resetPassword__Post');
$router->get('/error/{code}', '\ProjectSend\Controllers\PagesController::error')->setName('error');


$router->group('/admin', function (\League\Route\RouteGroup $route) {
    $route->map('GET', '/acme/route1', 'AcmeController::actionOne');
    $route->map('GET', '/acme/route2', 'AcmeController::actionTwo');
    $route->map('GET', '/acme/route3', 'AcmeController::actionThree');
});