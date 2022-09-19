<?php
/**
 * File that keeps alive the session when uploading files.
 * Prevents the following case from happening:
 * If "remember me" is not selected, after finishing uploading
 * a big file, the user is returned to the log in form since the
 * session has expired.
 * Used on upload.php.
 *
 * @package ProjectSend
 */
session_start();

require_once 'functions.session.permissions.php';

extend_session();

$random = rand( 1,1000000 );
$timestamp = preg_replace( '/[^0-9]/', '', $_GET['timestamp'] );
echo $timestamp . '-' . $random;
