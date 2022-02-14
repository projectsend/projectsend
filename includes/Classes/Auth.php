<?php
/**
 * Class that handles log in, log out and account status checks.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */
namespace ProjectSend\Classes;
use \PDO;
use ProjectSend\Classes\Session as Session;

class Auth
{
    private $dbh;
    private $logger;
    private $errorstate;

    public $user;

    public function __construct(PDO $dbh = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;
    }

    public function setLanguage($language = null)
    {
        $selected_form_lang	= (!empty( $language ) ) ? $language : SITE_LANG;
        $_SESSION['lang'] = $selected_form_lang;
    }

    // Save user to session
    private function login($user)
    {
        $this->user = $user;

        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['role'] = $user->role;
        $_SESSION['account_type'] = $user->account_type;

        if ($user->isClient()) {
            $_SESSION['access'] = $user->username;
        }
        else {
            $_SESSION['access'] = 'admin';
        }

        session_regenerate_id(true);
    }

    public function authenticate($username, $password)
    {
        if ( !$username || !$password )
            return false;

		/** Look up the system users table to see if the entered username exists */
                if ( ENCRYPT_PI ) {
		  $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE user=:username");
		  $statement->execute([
            ':username' => $username
        ]);
                } else {
		  $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE user=:username OR email=:email");
		  $statement->execute([
            ':username' => $username,
            ':email' => $username,
        ]);
                }
		$count_user = $statement->rowCount();
		if ($count_user > 0) {
			/** If the username was found on the users table */
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $row = $statement->fetch() ) {
                $user = new \ProjectSend\Classes\Users;
                $user->get($row['id']);
                $this->user = $user;
            }

			if (password_verify($password, $user->getRawPassword())) {
				if ($user->isActive()) {
                    $this->login($user);

					$results = [
                        'status' => 'success',
                        'user_id' => $user->id,
                        'location' => $user->isClient() ? CLIENT_VIEW_FILE_LIST_URL : BASE_URI."dashboard.php",
					];
                    
                    return json_encode($results);
				}
				else {
					$this->errorstate = 'inactive_client';
				}
			}
			else {
				//$this->errorstate = 'wrong_password';
				$this->errorstate = 'invalid_credentials';
			}
		}
		else {
			//$this->errorstate = 'wrong_username';
			$this->errorstate = 'invalid_credentials';
        }

		$results = [
            'status' => 'error',
            'type' => $this->errorstate,
            'message' => $this->getLoginError(),
        ];

        return json_encode($results);
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
            case 'microsoftgraph':
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
		$statement->execute([
            ':username'	=> $userProfile->email,
            ':email'	=> $userProfile->email,
        ]);
		$count_user = $statement->rowCount();
		if ($count_user > 0) {
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $row = $statement->fetch() ) {
                $user = new \ProjectSend\Classes\Users;
                $user->get($row['id']);
                $this->user = $user;

				if ($user->isActive()) {
                    $this->login($user);

                    /** Record the action log */
					$this->new_record_action = $this->logger->addEntry([
                        'action' => 43,
                        'owner_id' => $user->id,
                        'owner_user' => $user->username,
                        'affected_account_name' => $user->name
                    ]);

					if ($user->isClient()) {
                        header('Location: ' . CLIENT_VIEW_FILE_LIST_URL);
                        exit;
					}
					else {
                        header('Location: ' . BASE_URI."dashboard.php");
                        exit;
					}
				}
				else {
					header('Location: ' . BASE_URI."?error=account_inactive");
				}
            }
        } else {
            // User does not exist, create if self-registrations are allowed
            //pax($userProfile);

            if (get_option('clients_can_register') == '0') {
                header('Location: ' . BASE_URI."index.php?error=no_self_registration");
                exit;
            }

            $email_parts = explode('@', $userProfile->email);
            $username = (!username_exists($email_parts[0])) ? $email_parts[0] : generate_username($email_parts[0]);
            $password = generate_random_password();

            /** Validate the information from the posted form. */
            /** Create the user if validation is correct. */
            $new_client = new \ProjectSend\Classes\Users();
            $new_client->setType('new_client');
            $new_client->set([
                'username' => $username,
                'password' => $password,
                'name' => $userProfile->firstName . ' ' . $userProfile->lastName,
                'email' => $userProfile->email,
                'address' => null,
                'phone' => null,
                'contact' => null,
                'max_file_size' => 0,
                'notify_upload' => 1,
                'notify_account' => 1,
                'active' => (get_option('clients_auto_approve') == 0) ? 0 : 1,
                'account_requested'	=> (get_option('clients_auto_approve') == 0) ? 1 : 0,
                'type' => 'new_client',
                'recaptcha' => null,
            ]);

            if ($new_client->validate()) {
                $new_client->create();
                $new_client->triggerAfterSelfRegister();

                // Save as metadata
                $meta_name = 'social_network';
                $meta_value = json_encode($userProfile);
                $statement = $this->dbh->prepare("INSERT INTO " . TABLE_USER_META . " (user_id, name, value)"
                                ."VALUES (:id, :name, :value)");
                $statement->bindParam(':id', $this->user->id, PDO::PARAM_INT);
                $statement->bindParam(':name', $meta_name);
                $statement->bindParam(':value', $meta_value);
                $statement->execute();

                /** Record the action log */
                $new_record_action = $this->logger->addEntry([
                    'action' => 42,
                    'owner_id' => $new_client->id,
                    'owner_user' => $new_client->username,
                    'affected_account_name' => $new_client->username
                ]);

                if (get_option('clients_auto_approve') == 1) {
                    $this->authenticate($username, $password);
                    $redirect_url = 'my_files/index.php';
                } else {
                    $redirect_url = BASE_URI.'register.php?success=1';
                }

                // Redirect
                header("Location:".$redirect_url);
                exit;
            }

        }
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
    public function getLoginError($state = null)
    {
        $error = __("Error during log in.",'cftp_admin');;

        if (!empty($state)) {
            $this->errorstate = $state;
        }

		if (isset($this->errorstate)) {
			switch ($this->errorstate) {
                default:
				case 'invalid_credentials':
					$error = __("The supplied credentials are not valid.",'cftp_admin');
					break;
				case 'wrong_username':
					$error = __("The supplied username doesn't exist.",'cftp_admin');
					break;
				case 'wrong_password':
					$error = __("The supplied password is incorrect.",'cftp_admin');
                    break;
                case 'account_inactive':
				case 'inactive_client':
					$error = __("This account is not active.",'cftp_admin');
					if (get_option('clients_auto_approve') == 0) {
						$error .= ' '.__("If you just registered, please wait until a system administrator approves your account.",'cftp_admin');
					}
					break;
				case 'no_self_registration':
					$error = __('Client self registration is not allowed. If you need an account, please contact a system administrator.','cftp_admin');
					break;
				case 'no_account':
					$error = __('Sign-in with Google cannot be used to create new accounts at this time.','cftp_admin');
					break;
				case 'access_denied':
					$error = __('You must approve the requested permissions to sign in with Google.','cftp_admin');
                    break;
                case 'timeout':
                    $error = __('Session timed out. Please log in again.','cftp_admin');
                    break;
			}
        }
        
        return $error;
    }

    public function logout($error_code = null)
    {
        header("Cache-control: private");
		$_SESSION = array();
        session_destroy();
        session_regenerate_id(true);
        
        global $hybridauth;
        if (!empty($hybridauth)) {
            try {
                $hybridauth->disconnectAllAdapters();
            } catch (\Exception $e) {
                /*
                $return = [
                    'status' => 'error',
                    'message' => sprintf(__("Logout error: %s", 'cftp_admin'), $e->getMessage())
                ];
    
                return json_encode($return);
                */
            }
        }

        /** Record the action log */
        if (defined('CURRENT_USER_ID')) {
            $new_record_action = $this->logger->addEntry([
                'action'	=> 31,
                'owner_id'	=> CURRENT_USER_ID,
                'affected_account_name' => CURRENT_USER_NAME
            ]);
        }

		if ( isset( $_GET['timeout'] ) ) {
            $error_code = 'timeout';
        }
        $redirect_to = BASE_URI.'index.php';
        if (!empty($error_code)) {
            $redirect_to .= '?error='.$error_code;
        }

		header("Location: " . $redirect_to);
		exit;
    }
}
