<?php
namespace ProjectSend\Classes;

class Base {
    private $dbh;
    private $logger;
    private $bfchecker;

    public function __construct()
    {
        $this->dbh = get_dbh();
        $this->logger = get_container_item('actions_logger');
        $this->bfchecker = get_container_item('bfchecker');
    }
}