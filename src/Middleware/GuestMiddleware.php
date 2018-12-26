<?php

namespace ProjectSend\Middleware;

use ProjectSend\Classes\Auth;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Interfaces\RouterInterface;

class GuestMiddleware implements MiddlewareInterface
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Auth
     */
    protected $auth;

    /**
     * Constructor.
     *
     * @param RouterInterface $router
     * @param Auth            $auth
     */
    public function __construct(RouterInterface $router, Auth $auth)
    {
        $this->router = $router;
        $this->auth = $auth;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        /*
        if ($this->auth->check()) {
            return $response->withRedirect($this->router->pathFor('home'));
        }
        */

        return $next($request, $response);
    }
}
