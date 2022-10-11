<?php
namespace ProjectSend\Controllers;

use ProjectSend\Controllers\BaseController;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PagesController extends BaseController
{
    public function error(ServerRequestInterface $request): ResponseInterface
    {
        require_once VIEWS_PUBLIC_DIR.DS.'error.php';
        return $response;
    }
}