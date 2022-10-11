<?php
namespace ProjectSend\Controllers;

use ProjectSend\Controllers\BaseController;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthController extends BaseController
{
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        require_once VIEWS_PUBLIC_DIR.DS.'login.php';
        return $response;
    }

    public function resetPassword(ServerRequestInterface $request): ResponseInterface
    {
        require_once VIEWS_PUBLIC_DIR.DS.'reset-password.php';
        return $response;
    }
}