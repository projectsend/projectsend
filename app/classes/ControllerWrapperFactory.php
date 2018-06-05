<?php


namespace ProjectSend;


use Slim\Container;

class ControllerWrapperFactory implements ControllerWrapperFactoryInterface
{
    protected $container;

    /**
     * ControllerWrapperFactory constructor.
     * @param $conctainer
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function resolve(string $containerTag, string $function): callable
    {
        return function() use ($containerTag, $function) {
            $obj = $this->container->get($containerTag);
            call_user_func_array([$obj, $function], func_get_args());
        };
    }
}