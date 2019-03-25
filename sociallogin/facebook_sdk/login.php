<?php
require_once __DIR__ . '/Facebook/autoload.php';
require_once('../../sys.includes.php');
if (!session_id()) {
    session_start();
}
$fb = new Facebook\Facebook([
  'app_id' => FACEBOOK_CLIENT_ID,
  'app_secret' => FACEBOOK_CLIENT_SECRET,
  'default_graph_version' => 'v3.2',
  ]);

$helper = $fb->getRedirectLoginHelper();

$permissions = ['email']; // Optional permissions
$loginUrl = $helper->getLoginUrl(BASE_URI.'sociallogin/facebook_sdk/fb-callback.php', $permissions);
header("location:" .$loginUrl);
 ?>
