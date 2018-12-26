<?php

namespace ProjectSend\Middleware;

use ProjectSend\Classes\Auth;
use Slim\Flash\Messages;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Router;

class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @var Messages
     */
    protected $flash;

    /**
     * @var string
     */
    protected $role;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var Auth
     */
    protected $auth;

    /**
     * Constructor.
     *
     * @param Router   $router
     * @param Messages $flash
     * @param Auth $auth
     * @param string   $role
     */
    public function __construct(Router $router, Messages $flash, Auth $auth, $role = null)
    {
        $this->router = $router;
        $this->flash = $flash;
        $this->auth = $auth;
        $this->role = $role;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        /*
        if (!$this->auth->check()) {
            $this->flash->addMessage('error', 'You must be logged in to access this page!');

            return $response->withRedirect($this->router->pathFor('login'));
        } elseif ($this->role && !$this->auth->can_see_content($this->role)) {
            throw new AccessDeniedException($request, $response);
        }
        */

        return $next($request, $response);
    }
}
