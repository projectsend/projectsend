<?php
require_once('../../sys.includes.php');

$googleClient = new Google_Client();
$oauth2 = new Google_Oauth2Service($googleClient);
$googleClient->setApplicationName(THIS_INSTALL_SET_TITLE);
$googleClient->setClientSecret(GOOGLE_CLIENT_SECRET);
$googleClient->setClientId(GOOGLE_CLIENT_ID);
$googleClient->setRedirectUri(CALLBACK_GOOGLE_AUTH);
$googleClient->setScopes(array('profile', 'email'));

if (isset($_GET['error'])) {
  if ($_GET['error'] == 'access_denied') {
    $_SESSION['errorstate'] = 'access_denied';
    header("location:" . BASE_URI);
    return;
  }
  $_SESSION['errorstate'] = 'invalid_credentials';
  header("location:" . BASE_URI);
  return;
}

if (isset($_GET['code'])) {
  $token = $googleClient->authenticate($_GET['code']);
  $googleClient->setAccessToken($token);
  $_SESSION['id_token_token'] = json_decode($token);
}

if (isset($_SESSION['id_token_token']) && isset($_SESSION['id_token_token']->id_token)) {
  $ticket = $googleClient->verifyIdToken($_SESSION['id_token_token']->id_token);
  if ($ticket) {
    if (!isset($_SESSION['google_user'])) {
      $userData = $oauth2->userinfo->get();
      $email = $userData['email'];
    }else {
      $email = $_SESSION['google_user']['email'];
      unset($_SESSION['google_user']);
    }

    global $dbh;
    $statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE email= :email");
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
          header("location:" . BASE_URI . "dashboard.php");
        }
        exit;
      }
      else {
        $_SESSION['errorstate'] = 'invalid_credentials';
      }
    }
    else {

      if(CLIENTS_CAN_REGISTER == '0') {
        $_SESSION['errorstate'] = 'no_self_registration';
        header("location:" . BASE_URI);
        return;
      }else {
        $_SESSION['errorstate'] = 'no_account'; //TODO: create new account
        $new_client = new ClientActions();
        $username = $new_client->generateUsername($userData['name']);
        $password = generate_password();

        $clientData = array(
          'id' => '',
          'username' => $username,
          'password' => $password,
          'name' => $userData['name'],
          'email' => $userData['email'],
          'address' => '',
          'phone' => '',
          'contact' => '',
          'notify' => 0,
          'type' => 'new_client',
          'active' => CLIENTS_AUTO_APPROVE,
        );

        $new_client->create_client($clientData);

        if (CLIENTS_AUTO_GROUP != '0') {
          $admin_name = 'SELFREGISTERED';
          $client_id = $new_response['new_id'];
          $group_id = CLIENTS_AUTO_GROUP;

          $add_to_group = $dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
            ." VALUES (:admin, :id, :group)");
          $add_to_group->bindParam(':admin', $admin_name);
          $add_to_group->bindParam(':id', $client_id, PDO::PARAM_INT);
          $add_to_group->bindParam(':group', $group_id);
          $add_to_group->execute();
        }

        $notify_admin = new PSend_Email();
        $email_arguments = array(
          'type' => 'new_client_self',
          'address' => ADMIN_EMAIL_ADDRESS,
          'username' => $add_client_data_user,
          'name' => $add_client_data_name
        );
        $notify_admin_status = $notify_admin->psend_send_email($email_arguments);

        if (CLIENTS_AUTO_APPROVE == '0') {
          $_SESSION['errorstate'] = 'inactive_client';
          header("location:" . BASE_URI);
          return;
        }
        $_SESSION['google_user'] = $userData;
        header("location:" . CALLBACK_GOOGLE_AUTH);
        return;
      }
    }
  }
  $_SESSION['errorstate'] = 'invalid_credentials';
  header("location:" . BASE_URI);
}
