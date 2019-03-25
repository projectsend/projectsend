<?php
require_once __DIR__ . '/Facebook/autoload.php';
require_once('../../sys.includes.php');
if (!session_id()) {
    session_start();
}
$fb = new Facebook\Facebook([
  'app_id' => FACEBOOK_CLIENT_ID, // Replace 887385511592928 with your app id
  'app_secret' => FACEBOOK_CLIENT_SECRET,
  'default_graph_version' => 'v3.2',
  ]);

  $helper = $fb->getRedirectLoginHelper();

  try {
    $accessToken = $helper->getAccessToken();
  } catch(Facebook\Exceptions\FacebookResponseException $e) {
    // When Graph returns an error
    echo 'Graph returned an error: ' . $e->getMessage();
    exit;
  } catch(Facebook\Exceptions\FacebookSDKException $e) {
    // When validation fails or other local issues
    echo 'Facebook SDK returned an error: ' . $e->getMessage();
    exit;
  }

  if (! isset($accessToken)) {
    if ($helper->getError()) {
      header('HTTP/1.0 401 Unauthorized');
      echo "Error: " . $helper->getError() . "\n";
      echo "Error Code: " . $helper->getErrorCode() . "\n";
      echo "Error Reason: " . $helper->getErrorReason() . "\n";
      echo "Error Description: " . $helper->getErrorDescription() . "\n";
    } else {
      header('HTTP/1.0 400 Bad Request');
      echo 'Bad request';
    }
    exit;
  }

  // Logged in
  echo '<h3>Access Token</h3>';
  var_dump($accessToken->getValue());

  // The OAuth 2.0 client handler helps us manage access tokens
  $oAuth2Client = $fb->getOAuth2Client();

  // Get the access token metadata from /debug_token
  $tokenMetadata = $oAuth2Client->debugToken($accessToken);
  echo '<h3>Metadata</h3>';
  var_dump($tokenMetadata);

  // Validation (these will throw FacebookSDKException's when they fail)
  $tokenMetadata->validateAppId(FACEBOOK_CLIENT_ID); // Replace 887385511592928 with your app id
  // If you know the user ID this access token belongs to, you can validate it here
  //$tokenMetadata->validateUserId('123');
  $tokenMetadata->validateExpiration();

  if (! $accessToken->isLongLived()) {
    // Exchanges a short-lived access token for a long-lived one
    try {
      $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
    } catch (Facebook\Exceptions\FacebookSDKException $e) {
      echo "<p>Error getting long-lived access token: " . $e->getMessage() . "</p>\n\n";
      exit;
    }

    echo '<h3>Long-lived</h3>';
    var_dump($accessToken->getValue());
  }

  $_SESSION['fb_access_token'] = (string) $accessToken;

  // User is logged in with a long-lived access token.
  // You can redirect them to a members-only page.
  //header('Location: https://example.com/members.php');
  try {
    $response = $fb->get('/me?fields=email,name', $accessToken);
  } catch(Facebook\Exceptions\FacebookResponseException $e) {
    echo 'Graph returned an error: ' . $e->getMessage();
    exit;
  } catch(Facebook\Exceptions\FacebookSDKException $e) {
    echo 'Facebook SDK returned an error: ' . $e->getMessage();
    exit;
  }
echo "<br>";
  $user = $response->getGraphUser();

  echo('<br>');
  print_r($user);
  echo('<br>');
  echo "Email :".$user->getField('email');
  echo('<br>');

  if($user->getField('email') != '') {
        $name = $user->getField('name');
        $email =$user->getField('email');
        if (!isset($_SESSION['facebook_user'])){
          $_SESSION['facebook_user']['email']= $email;
          $_SESSION['facebook_user']['name']= $name;
        }else {
          $email = $_SESSION['facebook_user']['email'];
          $name = $_SESSION['facebook_user']['name'];
          unset($_SESSION['facebook_user']);
        }
        $user_level=0;
      }
      global $dbh;
      if(!empty($email)){
                        $statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE email= :email");
                        $statement->execute(array(':email' => $email));
                        $count_user = $statement->rowCount();
                        // echo("Row Count".$count_user);
                        if ($count_user > 0){
                          //echo "present";
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
                          $_SESSION['loggedin_id'] = $logged_id;
                          $_SESSION['userlevel'] = $user_level;

                          if ($user_level != 0) {
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

                              if ($user_level == 0) {
                                header("location:" . BASE_URI . "inbox.php/");
                              }
                              else {
                                // echo("HERE WE ARE");
                                header("location:" . BASE_URI . "home.php");
                              }
                          exit;
                          }
                          else{

                              header("location:" . BASE_URI . "home.php");
                              exit;
                          }

                        }
            else  if(CLIENTS_CAN_REGISTER == '0') {
              $_SESSION['errorstate'] = 'no_self_registration';
              header("location:" . BASE_URI);
              return;
            }
            else {
      				//echo
      				$_SESSION['errorstate'] = 'no_account'; //TODO: create new account
      				$new_client = new ClientActions();
      				$username = $new_client->generateUsername($name);
      				$password = generate_password();
      				//echo "<br>".$username." ".$password."<br>".CLIENTS_AUTO_APPROVE;
      				$clientData = array(
      					'username' => $email,
      					'password' => $password,
      					'name' => $name,
      					'email' => $email,
      					'address' => '',
      					'phone' => '',
      					'contact' => '',
      					'notify' => 0,
      					'type' => 'new_client',
      					'active' => 0,
      					);
      				$new_response = $new_client->create_client($clientData);

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
      				/** Set SESSION values */
      				$_SESSION['loggedin'] = $username;
      				$_SESSION['userlevel'] = 0;

      				if ($user_level != 0) {
      					$access_string = 'admin';
      					$_SESSION['access'] = $username;
      				}else{
      					$access_string = $username;
      					$_SESSION['access'] = $username;
      				}

      				if ($user_level == 0) {
      					header("location:" . BASE_URI . "inbox.php");
      				}else{
      					header("location:" . BASE_URI . "home.php");
      				}
      			}
          }

  ?>
