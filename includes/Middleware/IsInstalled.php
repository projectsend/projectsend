<?php declare(strict_types=1);

namespace ProjectSend\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\RedirectResponse;

class IsInstalled implements MiddlewareInterface
{
    public function __construct(\ProjectSend\Classes\Database $database, \League\Route\Router $router, \ProjectSend\Classes\Options $options)
    {
        $this->database = $database;
        $this->router = $router;
        $this->options = $options;
        $this->install = new \ProjectSend\Classes\Install($router);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $install_url = $this->router->getNamedRoute('install')->getPath();
        $current = $request->getUri()->getPath();
        // Check for base_uri in options table, or check for a table called {prefix}users?
        // $base_uri = $this->options->getOption('base_uri');
        if ($current != $install_url) {
            // if (empty($base_uri)) {
            if (!$this->database->tableExists(get_table('users'))) {
                return new RedirectResponse($install_url);
            }
        }

        return $handler->handle($request);
    }
}
