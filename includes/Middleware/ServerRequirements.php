<?php declare(strict_types=1);

namespace ProjectSend\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\RedirectResponse;

class ServerRequirements implements MiddlewareInterface
{
    public function __construct(\ProjectSend\Classes\Database $database, \League\Route\Router $router)
    {
        $this->dbh = $database->getPdo();
        $this->router = $router;
        $this->requirements = new \ProjectSend\Classes\ServerRequirements($database);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $error_url = $this->router->getNamedRoute('error_requirements')->getPath();
        $current = $request->getUri()->getPath();
        if (!$this->requirements->requirementsMet() && $current != $error_url) {
            return new RedirectResponse($error_url);
        }

        return $handler->handle($request);
    }
}
