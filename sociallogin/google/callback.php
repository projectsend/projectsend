<?php
require_once('../../sys.includes.php');

$googleClient = new Google_Client();
$googleClient->setApplicationName(THIS_INSTALL_SET_TITLE);
$googleClient->setClientSecret(GOOGLE_CLIENT_SECRET);
$googleClient->setClientId(GOOGLE_CLIENT_ID);
$googleClient->setRedirectUri(BASE_URI . 'sociallogin/google/callback.php');
$googleClient->setScopes(array('profile', 'email'));

if (isset($_GET['code'])) {
  $token = $googleClient->fetchAccessTokenWithAuthCode($_GET['code']);
  $googleClient->setAccessToken($token);
  $_SESSION['id_token_token'] = $token;
}

if (isset($_SESSION['id_token_token']) && isset($_SESSION['id_token_token']['id_token'])) {
  $ticket = $googleClient->verifyIdToken($_SESSION['id_token_token']['id_token']);
  if ($ticket) {
    $email = $ticket['email'];

    global $dbh;
    $statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE BINARY email= :email");
    $statement->execute(array(':email' => $email));

    $count_user = $statement->rowCount();
    if ($count_user > 0) {
      /** If the username was found on the users table */
      $statement->setFetchMode(PDO::FETCH_ASSOC);
      while ($row = $statement->fetch()) {
        $sysuser_username = $row['user'];
        $user_level = $row["level"];
        $active_status = $row['active'];
        $logged_id = $row['id'];
        $global_name = $row['name'];
      }
      if ($active_status != '0') {
        /** Set SESSION values */
        $_SESSION['loggedin'] = $sysuser_username;
        $_SESSION['userlevel'] = $user_level;

        if ($user_level != '0') {
          $access_string = 'admin';
          $_SESSION['access'] = $access_string;
        }
        else {
          $access_string = $sysuser_username;
          $_SESSION['access'] = $sysuser_username;
        }

        /** If "remember me" checkbox is on, set the cookie */
        if (!empty($_POST['login_form_remember'])) {
          setcookie("rememberwho", $sysuser_username, time() + COOKIE_EXP_TIME);
        }

        /** Record the action log */
        $new_log_action = new LogActions();
        $log_action_args = array(
          'action' => 1,
          'owner_id' => $logged_id,
          'affected_account_name' => $global_name
        );
        $new_record_action = $new_log_action->log_action_save($log_action_args);

        if ($user_level == '0') {
          header("location:" . BASE_URI . "my_files/");
        }
        else {
          header("location:" . BASE_URI . "home.php");
        }
        exit;
      }
      else {
        $_SESSION['errorstate'] = 'invalid_credentials';
      }
    }
    else {
      $_SESSION['errorstate'] = 'no_account'; //TODO: create new account
      if(CLIENTS_CAN_REGISTER == '0') {
        $_SESSION['errorstate'] = 'no_self_registration';
      }
      header("location:" . BASE_URI);
      return;
    }
  }
  $_SESSION['errorstate'] = 'invalid_credentials';
  header("location:" . BASE_URI);
}