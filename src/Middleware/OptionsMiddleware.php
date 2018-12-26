<?php

namespace ProjectSend\Middleware;

use ProjectSend\Classes\Options;
use Slim\Flash\Messages;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Router;
use \PDO;

class OptionsMiddleware implements MiddlewareInterface
{
    protected $router;
    protected $pdo;
    protected $options;

    /**
     * Constructor.
     */
    public function __construct(Router $router, PDO $pdo, Options $options)
    {
        $this->router = $router;
        $this->pdo = $pdo;
        $this->options = $options;
    }

    public function __invoke(Request $request, Response $response, callable $next)
    {
        $this->options = $this->options->retrieve();

        require APP_CONFIG_DIR.'/constants.php';

        return $next($request, $response);
    }
}
