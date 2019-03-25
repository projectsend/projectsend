<?php
/**
 * A simple example that shows how to use multiple providers.
 */
 ini_set('display_errors', 1);
 ini_set('display_startup_errors', 1);
 error_reporting(E_ALL);

include 'src/autoload.php';
include 'config.php';
require_once('../../sys.includes.php');

use Hybridauth\Exception\Exception;
use Hybridauth\Hybridauth;
use Hybridauth\HttpClient;
use Hybridauth\Storage\Session;

try {
    /**
     * Feed configuration array to Hybridauth.
     */
    $hybridauth = new Hybridauth($config);

    /**
     * Initialize session storage.
     */
    $storage = new Session();

    /**
     * Hold information about provider when user clicks on Sign In.
     */
    if (isset($_GET['provider'])) {
        $storage->set('provider', $_GET['provider']);
        echo("Inside callback");
    }

    /**
     * When provider exists in the storage, try to authenticate user and clear storage.
     *
     * When invoked, `authenticate()` will redirect users to provider login page where they
     * will be asked to grant access to your application. If they do, provider will redirect
     * the users back to Authorization callback URL (i.e., this script).
     */
    if ($provider = $storage->get('provider')) {
        // $hybridauth->authenticate($provider);
        $adapter = $hybridauth->authenticate($provider);
        //Returns a boolean of whether the user is connected with

        $isConnected = $adapter->isConnected();
        //Retrieve the user's profile
        $userProfile = $adapter->getUserProfile();


        //Inspect profile's public attributes
        if($provider=='LinkedIn'){
          // echo("Inside loop");
          $email = $userProfile->email;
          if (!isset($_SESSION['linkedin_user'])){
            // echo("<br> Inside If");
            $userData = $userProfile;
            $_SESSION['linkedin_user']='';
            $name = $userProfile->displayName;
            $email = $userProfile->email;
          }else {
            // echo("<br> Inside else");
            // print_r($_SESSION);
            $email = $_SESSION['linkedin_user']['email'];
            $name = $_SESSION['linkedin_user']['name'];
            unset($_SESSION['linkedin_user']['email']);
            unset($_SESSION['linkedin_user']['name']);
          }
          $user_level=0;
        }
        else if($provider=='Twitter'){
          // echo("Inside Twitter");
          $email = $userProfile->email;
          if (!isset($_SESSION['twitter_user'])){
            // echo("Inside If");
            $userData = $userProfile;
            $name = $userProfile->displayName;
            $email = $userProfile->email;
            $_SESSION['twitter_user']['email']= $email;
            $_SESSION['twitter_user']['name']= $name;
          }else {
            echo("Inside Else");
            $email = $_SESSION['twitter_user']['email'];
            $name = $_SESSION['twitter_user']['name'];
            unset($_SESSION['twitter_user']);
          }
          $user_level=0;
        }
        else if($provider=='yahoo'){
                $gotData= $userProfile;
                // print_r($gotData);
                // die();
        				$email = $userProfile->email;
        				if (!isset($_SESSION['yahoo_user'])){
        					// echo "<pre>";print_r($userProfile);exit;
        					$userData = $userProfile;
        					$firstName = $userProfile->firstName;
        					$lastName = $userProfile->lastName;
        					$name = $firstName." ".$lastName;
        					$email = $userProfile->email;
        					$_SESSION['yahoo_user']['email']= $email;
        					$_SESSION['yahoo_user']['name']= $name;
        				}else {
        					$email = $_SESSION['yahoo_user']['email'];
        					$name = $_SESSION['yahoo_user']['name'];
        					unset($_SESSION['yahoo_user']);
        				}
        				$user_level=0;
        			}


        //Disconnect the adapter
        $adapter->disconnect();

        $storage->set('provider', null);
        echo("<br> Before Session");

        global $dbh;
        if(!empty($email)){
                    			$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE email= :email");
                    			$statement->execute(array(':email' => $email));
                          $count_user = $statement->rowCount();
                          echo("Row Count".$count_user);
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
                              $_SESSION['errorstate'] = 'inactive_client';
                              if($_GET['provider'] == 'office365') {
                                echo "0";
                                exit;
                              }
                              else {
                                header("location:" . BASE_URI . "home.php");
                                exit;
                              }
                            }

                          }
        }else
            if(CLIENTS_CAN_REGISTER == '0') {
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


    /**
     * This will erase the current user authentication data from session, and any further
     * attempt to communicate with provider.
     */
    if (isset($_GET['logout'])) {
        $adapter = $hybridauth->getAdapter($_GET['logout']);
        $adapter->disconnect();
    }

    /**
     * Redirects user to home page (i.e., index.php in our case)
     */
    // HttpClient\Util::redirect('http://demo4.rndshosting.com/msend/');
    echo("Success");
} catch (Exception $e) {
    echo $e->getMessage();
}
