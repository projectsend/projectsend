<?php
use ProjectSend\Classes\Session as Session;

require_once 'bootstrap.php';

global $hybridauth;
$provider = Session::get('SOCIAL_LOGIN_NETWORK');
$adapter = $hybridauth->authenticate($provider);
if ($adapter->isConnected($provider)) {
    $process = new \ProjectSend\Classes\DoProcess($dbh);
    $process->socialLogin($provider);
}
