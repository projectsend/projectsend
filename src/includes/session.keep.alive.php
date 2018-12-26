<?php
/**
 * File that keeps alive the session when uploading files.
 * Prevents the following case from happening:
 * Used on upload-from-computer.php.
 *
 * @package ProjectSend
 */
session_start();
$_SESSION['last_call'] = time();

$random = rand( 1,1000000 );
$timestamp = preg_replace( '/[^0-9]/', '', $_GET['timestamp'] );
echo $timestamp . '-' . $random;