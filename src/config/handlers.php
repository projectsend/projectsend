<?php

use Awurth\Slim\Helper\Exception\AccessDeniedException;
use Slim\Handlers\NotAllowed;
use Slim\Handlers\NotFound;
use Slim\Handlers\PhpError;
use Slim\Handlers\Strategies\RequestResponseArgs;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Controller functions signature must be like:
 *
 * public function controllerAction($request, $response, $arg1, $arg2, $arg3, ...)
 *
 * https://www.slimframework.com/docs/objects/router.html#route-strategies
 */
$container['foundHandler'] = function ($container) {
    /** @var Request $request */
    $request = $container['request'];
    $container['monolog']->info(sprintf('Matched route "%s /%s"', $request->getMethod(), ltrim($request->getUri()->getPath(), '/')));

    return new RequestResponseArgs();
};

$container['csrfFailureHandler'] = function ($container) {
    return function (Request $request, Response $response) use ($container) {
        $container['monolog']->error(sprintf('Failed CSRF check on "%s /%s"', $request->getMethod(), ltrim($request->getUri()->getPath(), '/')));

        $container['flash']->addMessage('error', 'Failed CSRF check');

        if ('prod' === $this->getEnvironment()) {
            return $response->withRedirect($request->getUri()->getPath());
        } else {
            return $response->write('Failed CSRF check!');
        }
    };
};

$container['notFoundHandler'] = function ($container) {
    return function (Request $request, Response $response) use ($container) {
        $container['monolog']->error(sprintf('No route found for "%s /%s"', $request->getMethod(), ltrim($request->getUri()->getPath(), '/')));

        if ('prod' === $this->getEnvironment()) {
            return $response->withStatus(404)->write($container['twig']->fetch('error/404.twig'));
        } else {
            return (new NotFound())($request, $response);
        }
    };
};

$container['notAllowedHandler'] = function ($container) {
    return function (Request $request, Response $response, array $methods) use ($container) {
        $container['monolog']->error(sprintf(
            'No route found for "%s /%s": Method not allowed (Allow: %s)',
            $request->getMethod(),
            ltrim($request->getUri()->getPath(), '/'),
            implode(', ', $methods)
        ));

        if ('prod' === $this->getEnvironment()) {
            return $response->withStatus(405)->write($container['twig']->fetch('error/4xx.twig'));
        } else {
            return (new NotAllowed())($request, $response, $methods);
        }
    };
};

$container['accessDeniedHandler'] = function ($container) {
    return function (Request $request, Response $response, AccessDeniedException $exception) use ($container) {
        $container['monolog']->debug('Access denied, the user does not have access to this section', [
            'exception' => $exception
        ]);

        return $response->withStatus(403)->write($container['twig']->fetch('error/403.twig'));
    };
};

$container['errorHandler'] = function ($container) {
    return function (Request $request, Response $response, Exception $exception) use ($container) {
        if ($exception instanceof AccessDeniedException) {
            return $container['accessDeniedHandler']($request, $response, $exception);
        }

        $container['monolog']->error('Uncaught PHP Exception '.get_class($exception), [
            'exception' => $exception
        ]);

        if ('prod' === $this->getEnvironment()) {
            return $response->withStatus(500)->write($container['twig']->fetch('error/500.twig'));
        } else {
            return (new Slim\Handlers\Error(true))($request, $response, $exception);
        }
    };
};

$container['phpErrorHandler'] = function ($container) {
    return function (Request $request, Response $response, Throwable $error) use ($container) {
        $container['monolog']->critical('Uncaught PHP Exception '.get_class($error), [
            'exception' => $error
        ]);

        if ('prod' === $this->getEnvironment()) {
            return $response->withStatus(500)->write($container['twig']->fetch('error/500.twig'));
        } else {
            return (new PhpError(true))($request, $response, $error);
        }
    };
};
