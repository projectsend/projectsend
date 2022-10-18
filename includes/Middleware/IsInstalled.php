<?php declare(strict_types=1);

namespace ProjectSend\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\RedirectResponse;

class IsInstalled implements MiddlewareInterface
{
    public function __construct(\ProjectSend\Classes\Database $database, \League\Route\Router $router)
    {
        $this->router = $router;
        $this->install = new \ProjectSend\Classes\Install($database, $router);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $install_url = $this->router->getNamedRoute('install')->getPath();
        $current = $request->getUri()->getPath();
        if ($current != $install_url) {
            if (!$this->install->isInstalled()) {
                return new RedirectResponse($install_url);
            }
        }

        return $handler->handle($request);
    }
}
