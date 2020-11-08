<?php
use ProjectSend\Classes\Session as Session;

require_once 'bootstrap.php';

global $hybridauth;
global $auth;
$provider = Session::get('SOCIAL_LOGIN_NETWORK');
$adapter = $hybridauth->authenticate($provider);
if ($adapter->isConnected($provider)) {
    $auth->socialLogin($provider);
}
