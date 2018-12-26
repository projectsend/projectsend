<?php
namespace ProjectSend\Classes;

use Slim\Http\Request;
use Slim\Http\Response;
use Interop\Container\ContainerInterface;
use \PDO;

class Base {
    public $container;
    public $dbh;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->dbh = $this->container->pdo;
    }
}