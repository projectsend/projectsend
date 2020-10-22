<?php
/**
 * Class that handles log in, log out and account status checks.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */
namespace ProjectSend\Classes;
use \PDO;

class Auth
{
    private $dbh;
    private $logger;

    private $user;

    public function __construct(PDO $dbh = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;
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
		$this->statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE user=:username OR email=:email");
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

					/** Record the action log */
					$this->new_record_action = $this->logger->addEntry([
                        'action' => 1,
                        'owner_id' => $this->logged_id,
                        'owner_user' => $this->name,
                        'affected_account_name' => $this->name
                    ]);


					$results = array(
									'status'	=> 'success',
								);
					if ($this->user_level == '0') {
						$results['location']	= CLIENT_VIEW_FILE_LIST_URL;
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
        
        $this->error_message = $this->getLoginError($this->errorstate);
		$results = array(
						'status'	=> 'error',
						'message'	=> $this->error_message,
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

    /** Social Login via hybridauth */
    public function socialLogin($provider) {
        if (empty($provider)) {
            header("location:".PAGE_STATUS_CODE_404);
            exit;
        }

        //Attempt to authenticate users with a provider by name
        switch ($provider) {
            case 'google':
            case 'facebook':
            case 'linkedIn':
            case 'twitter':
            case 'windowslive':
            case 'yahoo':
            case 'openid':
                break;
            default:
                header("location:".PAGE_STATUS_CODE_404);
                exit;
        }
            
        global $hybridauth;
        $adapter = $hybridauth->authenticate($provider);
        if ($adapter->isConnected($provider)) {
            $userProfile = $adapter->getUserProfile();
            Session::remove('SOCIAL_LOGIN_NETWORK');
        }    

		/** Look up the system users table to see if the entered username exists */
		$statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE user=:username OR email=:email");
		$statement->execute(
						array(
							':username'	=> $userProfile->email,
							':email'	=> $userProfile->email,
						)
					);
		$count_user = $statement->rowCount();
		if ($count_user > 0) {
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $row = $statement->fetch() ) {
                $user = new \ProjectSend\Classes\Users;
                $user->get($row['id']);
                $user_data = $user->getProperties();

				if ($user->isActive()) {
					$_SESSION['loggedin'] = $user_data['username'];
					$_SESSION['userlevel'] = $user_data['role'];

					if ($user_data['role'] != '0') {
						$_SESSION['access'] = 'admin';
					}
					else {
						$_SESSION['access'] = $user_data['username'];
					}

					/** Record the action log */
					$this->new_record_action = $this->logger->addEntry([
                        'action' => 1,
                        'owner_id' => $user_data['id'],
                        'owner_user' => $user_data['username'],
                        'affected_account_name' => $user_data['name']
                    ]);

					if ($user_data['role'] == '0') {
                        header('Location: ' . CLIENT_VIEW_FILE_LIST_URL);
                        exit;
					}
					else {
                        header('Location: ' . BASE_URI."dashboard.php");
                        exit;
					}
				}
				else {
					$this->errorstate = 'inactive_client';
				}

            }
        }

        pax($userProfile);
        /*
            @todo
            Check if user exists on database
                Create if not
                Login if exists
                Log action
                Redirect
        */
    }

    public function login_ldap($email, $password, $language)
    {
        global $logger;
        
        if ( !$email || !$password ) {
            $return = [
                'status' => 'error',
                'message' => __("Email and password cannot be empty.",'cftp_admin')
            ];
    
            return json_encode($return);    
        }

		$selected_form_lang = (!empty( $language ) ) ? $language : SITE_LANG;

        // Bind to server
        $ldap_server = get_option('ldap_server');
        $ldap_bind_dn = get_option('ldap_bind_dn');
        $ldap_admin_user = get_option('ldap_admin_user');
        $ldap_admin_password = get_option('ldap_admin_password');

        try {
            $ldap = ldap_connect($ldap_server);
        } catch (\Exception $e) {
            $return = [
                'status' => 'error',
                'message' => sprintf(__("LDAP connection error: %s", 'cftp_admin'), $e->getMessage())
            ];

            return json_encode($return);
        }

        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, get_option('ldap_protocol_version'));
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        try {
            $bind = ldap_bind($ldap, $ldap_admin_user, $ldap_admin_password);
            if ($bind) {
                $ldap_search_base = get_option('ldap_search_base');
                
                $arr = array('dn', 1);
                $result = ldap_search($ldap, $ldap_bind_dn, "(mail=$email)", $arr);
                $entries = ldap_get_entries($ldap, $result);

                if ($entries['count'] > 0) {
                    // Bind with user
                    if (ldap_bind($ldap, $entries[0]['dn'], $password)) {
                        /*
                            @todo
                            Check if user exists on database
                                Create if not
                                Login if exists
                                Log action
                                Redirect
                        */
                        $return = [
                            'status' => 'success',
                        ];
            
                        return json_encode($return);
                    }
                    else {
                        $return = [
                            'status' => 'error',
                            'message' => __("The supplied email or password does not match an existing record.", 'cftp_admin')
                        ];
            
                        return json_encode($return);        
                    }
                }
                else {
                    // Email not found
                    $return = [
                        'status' => 'error',
                        'message' => __("The supplied email or password does not match an existing record.", 'cftp_admin')
                    ];
        
                    return json_encode($return);        
                }
            }
            else {
                $return = [
                    'status' => 'error',
                    'message' => __("Error binding to LDAP server.",'cftp_admin')
                ];
    
                return json_encode($return);    
            }
        } catch (\Exception $e) {
            $return = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];

            return json_encode($return);
        }
    }

    /**
     * Login error strings
     * 
     * @param string errorstate
     * @return string
     */
    public function getLoginError($errorstate)
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

    public function logout()
    {
        header("Cache-control: private");
		unset($_SESSION['loggedin']);
		unset($_SESSION['access']);
		unset($_SESSION['userlevel']);
		unset($_SESSION['lang']);
		unset($_SESSION['last_call']);
        session_destroy();
        
        global $hybridauth;
        try {
            $hybridauth->disconnectAllAdapters();
        } catch (\Exception $e) {

        }

		/*
		$language_cookie = 'projectsend_language';
		setcookie ($language_cookie, "", 1);
		setcookie ($language_cookie, false);
		unset($_COOKIE[$language_cookie]);
		*/

		/** Record the action log */
		$new_record_action = $this->logger->addEntry([
            'action'	=> 31,
            'owner_id'	=> CURRENT_USER_ID,
            'affected_account_name' => CURRENT_USER_NAME
        ]);

		$redirect_to = 'index.php';
		if ( isset( $_GET['timeout'] ) ) {
			$redirect_to .= '?error=timeout';
		}

		header("Location: " . $redirect_to);
		exit;
    }
}