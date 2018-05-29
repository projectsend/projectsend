<?php
/**
 * Requirements of basic system files.
 *
 * @package ProjectSend
 * @subpackage Core
 */
session_start();

define('ROOT_DIR', __DIR__);

/** Basic system constants */
require_once ROOT_DIR . '/sys.vars.php';
require_once ROOT_DIR . '/vendor/autoload.php';