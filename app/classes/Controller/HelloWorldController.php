<?php


namespace ProjectSend\Controller;


use Slim\Http\Request;
use Slim\Http\Response;

class HelloWorldController implements HelloWorldControllerInterface
{
    public function hello(Request $req, Response $res)
    {
        $res->getBody()->write('Hello World!');
        return $res;
    }
}