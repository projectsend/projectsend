<?php
$router->get('/', '\ProjectSend\Controllers\AuthController::login')->setName('login');
$router->get('/reset-password', '\ProjectSend\Controllers\AuthController::resetPassword')->setName('resetPassword');
$router->post('/reset-password', '\ProjectSend\Controllers\AuthController::resetPassword')->setName('resetPassword__post');
$router->get('/error/{code}', '\ProjectSend\Controllers\PagesController::error')->setName('error');
$router->get('/requirements-error', '\ProjectSend\Controllers\PagesController::errorRequirements')->setName('error_requirements');

$router->group('/install', function (\League\Route\RouteGroup $route) {
    $route->get('/', '\ProjectSend\Controllers\InstallerController::install')->setName('install');
    $route->post('/', '\ProjectSend\Controllers\InstallerController::install')->setName('install__post');
    $route->get('/make-config-file', '\ProjectSend\Controllers\InstallerController::makeConfigFile')->setName('install_make_config_file');
    $route->post('/make-config-file', '\ProjectSend\Controllers\InstallerController::makeConfigFile')->setName('install_make_config_file__post');
    $route->get('/config-file-exists', '\ProjectSend\Controllers\InstallerController::configFileExists')->setName('install_config_file_exists');
    $route->get('/success', '\ProjectSend\Controllers\InstallerController::configFileExists')->setName('install_success');
    $route->get('/already-installed', '\ProjectSend\Controllers\InstallerController::alreadyInstalled')->setName('install_already_installed');
});
