<?php

$cron = new \ProjectSend\Classes\Cron;
$cron->runTasks();
$cron->outputResults();

exit;
