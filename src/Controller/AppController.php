<?php

namespace ProjectSend\Controller;

use Awurth\Slim\Helper\Controller\Controller;
use Slim\Http\Request;
use Slim\Http\Response;

class AppController extends Controller
{
    public function home(Request $request, Response $response)
    {
        return $this->render($response, 'app/home.twig');
    }
}
