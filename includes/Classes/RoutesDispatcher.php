<?php
namespace ProjectSend\Classes;

use League\Route\Router;
use \Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Laminas\Diactoros\Response\RedirectResponse;

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
            $redirect_url = $this->router->getNamedRoute('error');
            return new RedirectResponse($redirect_url);
        }
    }
}