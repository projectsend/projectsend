<?php
/* 
 * Configuration for the Cron module.
 */

$config = array (

	'key' => 'secret',
	'allowed_tags' => array('daily', 'hourly', 'frequent'),
	'debug_message' => TRUE,
	'sendemail' => TRUE,

);
