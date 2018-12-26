<?php

namespace ProjectSend\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

interface MiddlewareInterface
{
    /**
     * Method called when the class is used as a function.
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next
     *
     * @return mixed
     */
    public function __invoke(Request $request, Response $response, callable $next);
}
