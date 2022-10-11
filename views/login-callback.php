<?php
use ProjectSend\Classes\Session as Session;


global $hybridauth;
global $auth;
$provider = Session::get('SOCIAL_LOGIN_NETWORK');
$adapter = $hybridauth->authenticate($provider);
if ($adapter->isConnected($provider)) {
    $auth->socialLogin($provider);
}
