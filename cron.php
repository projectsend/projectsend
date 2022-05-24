<?php
require_once 'bootstrap.php';

$cron = new \ProjectSend\Classes\Cron;
$cron->runTasks();

exit;