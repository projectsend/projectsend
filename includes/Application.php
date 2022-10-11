<?php
namespace ProjectSend;

class Application {
    public $container;

    public function __construct()
    {
        $this->setUpContainer();
    }

    private function setUpContainer()
    {
        $this->container = new \DI\Container();
    }

    public function setUpOptions()
    {
        if (!defined('IS_MAKE_CONFIG')) {
            try {
                $options = new \ProjectSend\Classes\Options;
                $options->setSystemConstants();
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    public function containerAdd($name, $value)
    {
        $this->container->set($name, $value);
    }

    public function getContainer()
    {
        return $this->container;
    }
}