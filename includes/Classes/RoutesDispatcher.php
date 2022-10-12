<?php
namespace ProjectSend\Classes;

use League\Route\Router;
use \Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

class RoutesDispatcher
{
    protected $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function dispatch($request)
    {
        try {
            $response = $this->router->dispatch($request);
            (new SapiEmitter)->emit($response);
        } catch (\League\Route\Http\Exception\NotFoundException $e) {
            $route = get_route_for('error');
            ps_redirect(BASE_URI.$route);
        }
    }
}