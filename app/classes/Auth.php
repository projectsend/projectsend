<?php
/**
 * Class that handles log in, log out and account status checks.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */
namespace ProjectSend;
use \PDO;

class Auth {

    function __construct()
    {
		global $dbh;
        $this->dbh = $dbh;
        
        global $logger;
	}

    /**
     * Try to log in with a username and password
     *
     * @param $username
     * @param $password
     * @param $language
     */
    public function login($username, $password, $language)
    {
        global $logger;
        global $hasher;
        
        if ( !$username || !$password )
            return false;

		$this->selected_form_lang	= (!empty( $language ) ) ? $language : SITE_LANG;

		/** Look up the system users table to see if the entered username exists */
		$this->statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE user= :username OR email= :email");
		$this->statement->execute(
						array(
							':username'	=> $username,
							':email'	=> $username,
						)
					);
		$this->count_user = $this->statement->rowCount();
		if ($this->count_user > 0) {
			/** If the username was found on the users table */
			$this->statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $this->row = $this->statement->fetch() ) {
				$this->db_username	    = $this->row['user'];
				$this->db_pass			= $this->row['password'];
				$this->user_level		= $this->row["level"];
				$this->active_status	= $this->row['active'];
				$this->logged_id		= $this->row['id'];
				$this->name	        	= $this->row['name'];
			}

			if (password_verify($password, $this->db_pass)) {
				if ($this->active_status != '0') {
					/** Set SESSION values */
					$_SESSION['loggedin']	= $this->db_username;
					$_SESSION['userlevel']	= $this->user_level;
					$_SESSION['lang']		= $this->selected_form_lang;

					/**
					 * Language cookie
                     * Must decide how to refresh language in the form when the user
                     * changes the language <select> field.
                     * By using a cookie and not refreshing here, the user is
                     * stuck in a language and must use it to recover password or
                     * create account, since the lang cookie is only at login now.
                     * 
					 * @todo Implement.
					 */
					//setcookie('projectsend_language', $selected_form_lang, time() + (86400 * 30), '/');

					if ($this->user_level != '0') {
						$this->access_string	= 'admin';
						$_SESSION['access']		= $this->access_string;
					}
					else {
						$this->access_string	= $this->db_username;
						$_SESSION['access']		= $this->db_username;
					}

					/** If "remember me" checkbox is on, set the cookie */
					if (!empty($_POST['login_form_remember'])) {
						/*
						setcookie("loggedin",$db_username,time()+COOKIE_EXP_TIME);
						setcookie("password",$sysuser_password,time()+COOKIE_EXP_TIME);
						setcookie("access",$access_string,time()+COOKIE_EXP_TIME);
						setcookie("userlevel",$user_level,time()+COOKIE_EXP_TIME);
						*/
						setcookie("rememberwho",$db_username,time()+COOKIE_EXP_TIME);
					}
					/** Record the action log */
					$this->log_action_args = array(
											'action' => 1,
											'owner_id' => $this->logged_id,
											'owner_user' => $this->name,
											'affected_account_name' => $this->name
										);
					$this->new_record_action = $logger->log_action_save($this->log_action_args);


					$results = array(
									'status'	=> 'success',
									'message'	=> system_message('success','Login success. Redirecting...','login_response'),
								);
					if ($this->user_level == '0') {
						$results['location']	= BASE_URI."my_files/";
					}
					else {
						$results['location']	= BASE_URI."dashboard.php";
					}

					/** Using an external form */
					if ( !empty( $_GET['external'] ) && $_GET['external'] == '1' && empty( $_GET['ajax'] ) ) {
						/** Success */
						if ( $results['status'] == 'success' ) {
							header('Location: ' . $results['location']);
							exit;
						}
					}

					echo json_encode($results);
					exit;
				}
				else {
					$this->errorstate = 'inactive_client';
				}
			}
			else {
				//$errorstate = 'wrong_password';
				$this->errorstate = 'invalid_credentials';
			}
		}
		else {
			//$errorstate = 'wrong_username';
			$this->errorstate = 'invalid_credentials';
        }
        
        $this->error_message = $this->get_login_error($this->errorstate);
		$results = array(
						'status'	=> 'error',
						'message'	=> system_message('danger',$this->error_message,'login_error'),
					);

		/** Using an external form */
		if ( !empty( $_GET['external'] ) && $_GET['external'] == '1' && empty( $_GET['ajax'] ) ) {
			/** Error */
			if ( $results['status'] == 'error' ) {
				header('Location: ' . BASE_URI . '?error=invalid_credentials');
                exit;
			}
		}

        echo json_encode($results);
    }

    /**
     * Login error strings
     * 
     * @param string errorstate
     * @return string
     */
    public function get_login_error($errorstate)
    {
        $this->error = __("Error during log in.",'cftp_admin');;

		if (isset($errorstate)) {
			switch ($errorstate) {
				case 'invalid_credentials':
					$this->error = __("The supplied credentials are not valid.",'cftp_admin');
					break;
				case 'wrong_username':
					$this->error = __("The supplied username doesn't exist.",'cftp_admin');
					break;
				case 'wrong_password':
					$this->error = __("The supplied password is incorrect.",'cftp_admin');
					break;
				case 'inactive_client':
					$this->error = __("This account is not active.",'cftp_admin');
					if (CLIENTS_AUTO_APPROVE == 0) {
						$this->error .= ' '.__("If you just registered, please wait until a system administrator approves your account.",'cftp_admin');
					}
					break;
				case 'no_self_registration':
					$this->error = __('Client self registration is not allowed. If you need an account, please contact a system administrator.','cftp_admin');
					break;
				case 'no_account':
					$this->error = __('Sign-in with Google cannot be used to create new accounts at this time.','cftp_admin');
					break;
				case 'access_denied':
					$this->error = __('You must approve the requested permissions to sign in with Google.','cftp_admin');
					break;
			}
        }
        
        return $this->error;
    }

    /**
     * Login using oauth
     */
    public function oauth_login($service, $oauth)
    {
    }

    public function logout()
    {
        global $logger;

        header("Cache-control: private");
		unset($_SESSION['loggedin']);
		unset($_SESSION['access']);
		unset($_SESSION['userlevel']);
		unset($_SESSION['lang']);
		unset($_SESSION['last_call']);
		session_destroy();

		/** If there is a cookie, unset it */
		setcookie("loggedin","",time()-COOKIE_EXP_TIME);
		setcookie("password","",time()-COOKIE_EXP_TIME);
		setcookie("access","",time()-COOKIE_EXP_TIME);
		setcookie("userlevel","",time()-COOKIE_EXP_TIME);

		/*
		$language_cookie = 'projectsend_language';
		setcookie ($language_cookie, "", 1);
		setcookie ($language_cookie, false);
		unset($_COOKIE[$language_cookie]);
		*/

		/** Record the action log */
		$log_action_args = array(
								'action'	=> 31,
								'owner_id'	=> CURRENT_USER_ID,
								'affected_account_name' => CURRENT_USER_NAME
							);
		$new_record_action = $logger->log_action_save($log_action_args);

		$redirect_to = 'index.php';
		if ( isset( $_GET['timeout'] ) ) {
			$redirect_to .= '?error=timeout';
		}

		header("Location: " . $redirect_to);
		exit;
    }
}