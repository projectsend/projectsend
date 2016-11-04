<?php
        session_start();
        include('config.php');
	include('hybridauth/Hybrid/Auth.php');
	require_once('../sys.includes.php');
	//print_r($config);exit();
        if(isset($_GET['provider']))
        {
        	$provider = $_GET['provider'];
        	
        	try{
        	
        	$hybridauth = new Hybrid_Auth( $config );
        	
        	$authProvider = $hybridauth->authenticate($provider);

	        $user_profile = $authProvider->getUserProfile();
	       // echo "<pre>";print_r($user_profile);echo "</pre>";//exit();
			if($user_profile && isset($user_profile->identifier)){
				echo "s";
				//for facebook
				if($provider=='Facebook'){
					$email = $user_profile->email;
					if (!isset($_SESSION['facebook_user'])){
						$userData = $user_profile;
					      	$email = $user_profile->email;
					}else {
						$email = $_SESSION['facebook_user']['email'];
						unset($_SESSION['facebook_user']);
					}
				}
				//for Twitter
				if($provider=='Twitter'){
					$email = $user_profile->email;
					if (!isset($_SESSION['twitter_user'])){
						$userData = $user_profile;
					      	$email = $user_profile->email;
					}else {
						$email = $_SESSION['twitter_user']['email'];
						unset($_SESSION['twitter_user']);
					}
				}
				//for LinkedIn
				if($provider=='LinkedIn'){
					$email = $user_profile->email;
					if (!isset($_SESSION['linkedin_user'])){
						$userData = $user_profile;
					      	$email = $user_profile->email;
					}else {
						$email = $_SESSION['linkedin_user']['email'];
						unset($_SESSION['linkedin_user']);
					}
				}
				//echo $email;exit();	

				    global $dbh;
				    $statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE email= :email");
				    $statement->execute(array(':email' => $email));

				    $count_user = $statement->rowCount();
				    if ($count_user > 0){
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
				    }else {
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
					$_SESSION['facebook_user'] = $userData;
					header("location:" . BASE_URI . "sociallogin/facebook/callback.php");
					return;
				      }
				    }
	        	echo "<b>Name</b> :".$user_profile->displayName."<br>";
	        	echo "<b>Profile URL</b> :".$user_profile->profileURL."<br>";
	        	echo "<b>Image</b> :".$user_profile->photoURL."<br> ";
	        	echo "<img src='".$user_profile->photoURL."'/><br>";
	        	echo "<b>Email</b> :".$user_profile->email."<br>";	        		        		        	
	        	echo "<br> <a href='logout.php'>Logout</a>";
	        	}	        

			}
			catch( Exception $e )
			{ 
			
				 switch( $e->getCode() )
				 {
                        case 0 : echo "Unspecified error."; break;
                        case 1 : echo "Hybridauth configuration error."; break;
                        case 2 : echo "Provider not properly configured."; break;
                        case 3 : echo "Unknown or disabled provider."; break;
                        case 4 : echo "Missing provider application credentials."; break;
                        case 5 : echo "Authentication failed. "
                                         . "The user has canceled the authentication or the provider refused the connection.";
                                 break;
                        case 6 : echo "User profile request failed. Most likely the user is not connected "
                                         . "to the provider and he should to authenticate again.";
                                 $twitter->logout();
                                 break;
                        case 7 : echo "User not connected to the provider.";
                                 $twitter->logout();
                                 break;
                        case 8 : echo "Provider does not support this feature."; break;
                }

                // well, basically your should not display this to the end user, just give him a hint and move on..
                echo "<br /><br /><b>Original error message:</b> " . $e->getMessage();

                echo "<hr /><h3>Trace</h3> <pre>" . $e->getTraceAsString() . "</pre>";

			}
        
        }
?>
