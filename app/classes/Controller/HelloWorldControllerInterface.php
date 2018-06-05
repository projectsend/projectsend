<?php


namespace ProjectSend\Controller;

use Slim\Http\Request;
use Slim\Http\Response;


interface HelloWorldControllerInterface
{
    public function hello(Request $req, Response $res);
}