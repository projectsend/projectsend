<?php
/**
 * Class that handles log in, log out and account status checks.
 */
namespace ProjectSend\Classes;
use \PDO;
use ProjectSend\Classes\Session as Session;

class Auth
{
    private $dbh;
    private $logger;
    private $error_message;
    private $bfchecker;
    private $error_strings;
    public $user;

    public function __construct(PDO $dbh = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }

        global $bfchecker;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;
        $this->bfchecker = $bfchecker;

        global $json_strings;
        $this->error_strings = $json_strings['login']['errors'];
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

        session_regenerate_id(true);

        // Record the action log
        $logger = new \ProjectSend\Classes\ActionsLog;
        $logger->addEntry([
            'action' => 1,
            'owner_id' => $user->id,
            'owner_user' => $user->username,
            'affected_account_name' => $user->name
        ]);
    }

    public function validate2faRequest($token, $code)
    {
        $auth_code = new \ProjectSend\Classes\AuthenticationCode();
        $validate = json_decode($auth_code->validateRequest($token, $code));
        if ($validate->status != 'success') {
            $this->setError($validate->message);

            return json_encode([
                'status' => 'error',
                'message' => $this->getError(),
            ]);
        }
        
        $props =  $auth_code->getProperties();
        $user = new \ProjectSend\Classes\Users;
        $user->get($props['user_id']);
            
        if ($user->isActive()) {
            $this->user = $user;
            $this->login($user);

            $results = [
                'status' => 'success',
                'user_id' => $user->id,
                'location' => $user->isClient() ? CLIENT_VIEW_FILE_LIST_URL : BASE_URI."dashboard.php",
            ];
            
            return json_encode($results);
        }

        return json_encode([
            'status' => 'error',
            'message' => $this->error_strings['2fa']['invalid'],
        ]);
    }

    public function authenticate($username, $password)
    {
        if ( !$username || !$password )
            return false;

		/** Look up the system users table to see if the entered username exists */
		$statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE user=:username OR email=:email");
		$statement->execute([
            ':username' => $username,
            ':email' => $username,
        ]);
		if ($statement->rowCount() > 0) {
			/** If the username was found on the users table */
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $row = $statement->fetch() ) {
                $user = new \ProjectSend\Classes\Users;
                $user->get($row['id']);
                $this->user = $user;
            }

			if (password_verify($password, $user->getRawPassword())) {
				if ($user->isActive()) {
                    $new2fa = new \ProjectSend\Classes\AuthenticationCode();
                    if ($new2fa->requires2fa()) {
                        $request2fa = json_decode($new2fa->requestNewCode($user->id));
                        if ($request2fa->status == 'success') {
                            $results = [
                                'status' => 'success',
                                'user_id' => $user->id,
                                'location' => BASE_URI."index.php?form=2fa_verify&token=".$request2fa->token,
                            ];
                        } else {
                            $this->setError($request2fa->message);
                            $results = [
                                'status' => 'error',
                                'message' => $request2fa->message,
                                'location' => BASE_URI,
                            ];
                        }
                        
                        return json_encode($results);
                    }

                    // When 2FA is not required, login
                    $this->login($user);

					$results = [
                        'status' => 'success',
                        'user_id' => $user->id,
                        'location' => $user->isClient() ? CLIENT_VIEW_FILE_LIST_URL : BASE_URI."dashboard.php",
					];
                    
                    return json_encode($results);
				}
				else {
                    $this->setError($this->getAccountInactiveError());
				}
			}
			else {
				$this->setError($this->error_strings['invalid_credentials']);
			}
		}
		else {
            $this->bfchecker->addFailedLoginAttempt($username, get_client_ip());

            $this->setError($this->error_strings['invalid_credentials']);
        }

		$results = [
            'status' => 'error',
            'message' => $this->getError(),
        ];

        return json_encode($results);
    }

    private function getAccountInactiveError()
    {
        $error = $this->error_strings['account_inactive'];
        if (get_option('clients_auto_approve') == 0) {
            $error .= ' ' . $this->error_strings['account_inactive_notice'];
        }

        return $error;
    }

    /** Social Login via hybridauth */
    public function socialLogin($provider) {
        if (empty($provider)) {
            exit_with_error_code(404);
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
                exit_with_error_code(404);
                break;
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
					$this->logger->addEntry([
                        'action' => 43,
                        'owner_id' => $user->id,
                        'owner_user' => $user->username,
                        'affected_account_name' => $user->name
                    ]);

					if ($user->isClient()) {
                        ps_redirect(CLIENT_VIEW_FILE_LIST_URL);
					}
					else {
                        ps_redirect(BASE_URI.'dashboard.php');
					}
				}
				else {
                    $this->setError($this->getAccountInactiveError());
                    ps_redirect(BASE_URI);
				}
            }
        } else {
            // User does not exist, create if self-registrations are allowed
            //pax($userProfile);

            if (get_option('clients_can_register') == '0') {
                $this->setError($this->error_strings['no_self_registration']);
                ps_redirect(BASE_URI);
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

            $new_client->create();
            if (!empty($new_response['id'])) {
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
                $this->logger->addEntry([
                    'action' => 42,
                    'owner_id' => $new_client->id,
                    'owner_user' => $new_client->username,
                    'affected_account_name' => $new_client->username
                ]);

                $redirect_to = BASE_URI.'register.php?success=1';

                if (get_option('clients_auto_approve') == 1) {
                    $this->authenticate($username, $password);
                    $redirect_to = 'my_files/index.php';
                }

                // Redirect
                ps_redirect($redirect_to);
            }
        }
    }

    public function loginLdap($email, $password, $language)
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

    private function setError($message)
    {
        $this->error_message = $message;
    }

    public function getError()
    {
        if (empty($this->error_message)) {
            return __("Error during log in.",'cftp_admin');
        }

        return $this->error_message;
    }

    public function logout()
    {
        header("Cache-control: private");
		$_SESSION = [];
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
            $this->logger->addEntry([
                'action'	=> 31,
                'owner_id'	=> CURRENT_USER_ID,
                'affected_account_name' => CURRENT_USER_NAME
            ]);
        }

		// if ( isset( $_GET['timeout'] ) ) {
        //     $error_code = 'timeout';
        // }
    }
}
