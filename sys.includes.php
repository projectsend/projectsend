<?php
/**
 * Requirements of basic system files.
 * It's included in every main page as the app starter.
 * 
 * @todo remove this file after using a proper router!
 * 
 * @package ProjectSend
 * @subpackage Core
 */

define('ROOT_DIR', __DIR__);

/** Composer dependencies */
require_once ROOT_DIR . '/vendor/autoload.php';