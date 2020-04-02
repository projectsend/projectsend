<?php
//session_start();
include('config.php');
include('hybridauth/Hybrid/Auth.php');
require_once('../sys.includes.php');
//print_r($config);exit();
if(isset($_GET['provider'])){
	$provider = $_GET['provider'];
	try{
		if($_GET['provider'] == 'office365') {

					$office_u_name = $_GET['user'];
					$office_u_email = $_GET['useremail'];

				$email = $office_u_email;
				if (!isset($_SESSION['office365'])){
					$userData = $office_u_name;
					$name = $office_u_name;
					$email = $office_u_email;
					$_SESSION['office365']['email']= $email;
					$_SESSION['office365']['name']= $name;
				}else {
					$email = $_SESSION['office365']['email'];
					$name = $_SESSION['office365']['name'];
					unset($_SESSION['office365']);
				}
				$user_level=0;

			}elseif($_GET['provider'] == 'Facebook') {

				$name = $_SESSION['FULLNAME'] ;
				$email =$_SESSION['EMAIL'];
				if (!isset($_SESSION['facebook_user'])){
					//$userData = $Facebook_u_name;
					$_SESSION['facebook_user']['email']= $email;
					$_SESSION['facebook_user']['name']= $name;
				}else {
					$email = $_SESSION['facebook_user']['email'];
					$name = $_SESSION['facebook_user']['name'];
					unset($_SESSION['facebook_user']);
				}
				$user_level=0;
			}

		else {
		$hybridauth = new Hybrid_Auth( $config );
		$authProvider = $hybridauth->authenticate($provider);
		$user_profile = $authProvider->getUserProfile();
		$user_identifier = $user_profile->identifier;
		}

		//echo "<pre>";print_r($user_profile);echo "</pre>";exit();
		if((isset($user_profile) && isset($user_identifier)) || $_GET['provider'] == 'office365' || $_GET['provider'] == 'Facebook'){
			//for Twitter
			if($provider=='Twitter'){
				$email = $user_profile->email;
				if (!isset($_SESSION['twitter_user'])){
					$userData = $user_profile;
					$name = $user_profile->displayName;
					$email = $user_profile->email;
					$_SESSION['twitter_user']['email']= $email;
					$_SESSION['twitter_user']['name']= $name;
				}else {
					$email = $_SESSION['twitter_user']['email'];
					$name = $_SESSION['twitter_user']['name'];
					unset($_SESSION['twitter_user']);
				}
				$user_level=0;
			}
			//for LinkedIn
			if($provider=='LinkedIn'){

				$email = $user_profile->email;
				if (!isset($_SESSION['linkedin_user'])){
				    echo "eeiieie";exit;
					$userData = $user_profile;
					$_SESSION['linkedin_user']='';
					$name = $user_profile->displayName;
					$email = $user_profile->email;
					//$_SESSION['linkedin_user']['email']= $email;
					//$_SESSION['linkedin_user']['name']= $name;
				}else {
					$email = $_SESSION['linkedin_user']['email'];
					$name = $_SESSION['linkedin_user']['name'];
					unset($_SESSION['linkedin_user']['email']);
					unset($_SESSION['linkedin_user']['name']);
				}
				$user_level=0;
			}
			//for Yahoo
			if($provider=='yahoo'){
				$email = $user_profile->email;
				if (!isset($_SESSION['yahoo_user'])){
					//print_r($user_profile);exit;
					$userData = $user_profile;
					$firstName = $user_profile->firstName;
					$lastName = $user_profile->lastName;
					$name = $firstName." ".$lastName;
					$email = $user_profile->email;
					$_SESSION['yahoo_user']['email']= $email;
					$_SESSION['yahoo_user']['name']= $name;
				}else {
					$email = $_SESSION['yahoo_user']['email'];
					$name = $_SESSION['yahoo_user']['name'];
					unset($_SESSION['yahoo_user']);
				}
				$user_level=0;
			}



			global $dbh;
			if(!empty($email)){
			$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE email= :email");
			$statement->execute(array(':email' => $email));
			$count_user = $statement->rowCount();

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
					header("location:" . BASE_URI . "inbox.php/");
				}
				else {

					if(isset($_GET['auth'])) {
						$drop_off_auth =$_GET['auth'];
						 header("location:".BASE_URI."dropoff.php?auth=".$drop_off_auth);
					}
				 else{
					 header("location:" . BASE_URI . "home.php");
				 }
				}
				exit;
				}else{
					$_SESSION['errorstate'] = 'inactive_client';
					if($_GET['provider'] == 'office365') {
						echo "0";
						exit;
					}
					else {
						if(isset($_GET['auth'])) {
              $drop_off_auth =$_GET['auth'];
    						header("location:".BASE_URI."dropoff.php?auth=".$drop_off_auth);
            }
           else{
             header("location:" . BASE_URI . "home.php");
           }
						exit;
					}
					if($_GET['provider'] == 'Facebook') {
						echo "0";
						exit;
					}
					else {
						if(isset($_GET['auth'])) {
              $drop_off_auth =$_GET['auth'];
    						header("location:".BASE_URI."dropoff.php?auth=".$drop_off_auth);
            }
           else{
             header("location:" . BASE_URI . "home.php");
           }
						exit;
					}
				}

			}else {
			if(CLIENTS_CAN_REGISTER == '0') {
				$_SESSION['errorstate'] = 'no_self_registration';
				header("location:" . BASE_URI);
				return;
			}else {
				//echo 'can';
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

				if ($user_level != '0') {
					$access_string = 'admin';
					$_SESSION['access'] = $username;
				}else{
					$access_string = $username;
					$_SESSION['access'] = $username;
				}
				//echo "<br>CLIENTS_AUTO_APPROVE : ".CLIENTS_AUTO_APPROVE."<br>";
				//echo "<pre>";print_r($_SESSION);echo "</pre>";
				//exit;
				//exit;
				if (CLIENTS_AUTO_APPROVE == '0') {
					$_SESSION['errorstate'] = 'inactive_client';
					if($_GET['provider'] == 'office365') {
						echo "0";
						exit;
					}
					else {
						header("location:" . BASE_URI);
					return;
					}

				}
					//$_SESSION['facebook_user'] = $userData;
					//header("location:" . BASE_URI . "sociallogin/facebook/callback.php");
					//return;
				if ($user_level == '0') {
					header("location:" . BASE_URI . "my_files/");
				}else{
					//echo "214";exit();
					header("location:" . BASE_URI . "home.php");
				}
			}

			}
		}else{
			echo "email is empty";
			header("location:" . BASE_URI . "process.php?do=logout");
			exit();
		}
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
	exit;
}
?>
