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

    public function __construct(?PDO $dbh = null)
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
        $_SESSION['role_id'] = (int)$user->role_id;
        $_SESSION['account_type'] = $user->account_type;

        session_regenerate_id(true);

        // Initialize session timestamp to prevent immediate expiration
        extend_session();

        // Record the action log
        $logger = new \ProjectSend\Classes\ActionsLog;
        $logger->addEntry([
            'action' => 1,
            'owner_id' => $user->id,
            'owner_user' => $user->username,
            'affected_account_name' => $user->name
        ]);
    }

    public function validate2faRequest($token, $code, bool $remember_me = false)
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
        $user = new \ProjectSend\Classes\Users($props['user_id']);

        if ($user->isActive()) {
            $this->user = $user;
            $this->login($user);

            // Handle remember me functionality
            if ($remember_me && get_option('remember_me_enabled', null, '1')) {
                $rememberMe = new \ProjectSend\Classes\RememberMe();
                $token = $rememberMe->generateToken();
                if ($rememberMe->storeToken($user->id, $token)) {
                    $rememberMe->setCookie($token);
                }
            }

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

    /**
     * Validate a TOTP code or backup code during login
     */
    public function validateTotpRequest($token, $code, bool $remember_me = false)
    {
        global $json_strings;

        // Look up token to get user_id
        $auth_code = new \ProjectSend\Classes\AuthenticationCode();
        if (!$auth_code->getByToken($token)) {
            $this->setError($json_strings['login']['errors']['2fa']['invalid']);
            return json_encode([
                'status' => 'error',
                'message' => $this->getError(),
            ]);
        }

        $props = $auth_code->getProperties();

        // Check if token was already used
        if ($props['used'] != '0') {
            $this->setError($json_strings['login']['errors']['2fa']['used']);
            return json_encode([
                'status' => 'error',
                'message' => $this->getError(),
            ]);
        }

        // Check expiry (TOTP tokens expire after 5 minutes like email codes)
        if ($auth_code->codeExpired()) {
            $this->setError($json_strings['login']['errors']['2fa']['expired']);
            return json_encode([
                'status' => 'error',
                'message' => $this->getError(),
            ]);
        }

        $user_id = $props['user_id'];
        $totp = new \ProjectSend\Classes\Totp();
        $secret = $totp->getUserSecret($user_id);

        if (!$secret) {
            $this->setError($json_strings['login']['errors']['2fa']['invalid']);
            return json_encode([
                'status' => 'error',
                'message' => $this->getError(),
            ]);
        }

        $valid = false;
        $is_backup = false;

        // Try TOTP code first
        if ($totp->verifyCode($secret, $code)) {
            $valid = true;
        }

        // If TOTP fails, try backup code
        if (!$valid && $totp->validateBackupCode($user_id, $code)) {
            $valid = true;
            $is_backup = true;
        }

        if (!$valid) {
            $this->setError($json_strings['login']['errors']['2fa']['totp_invalid']);
            return json_encode([
                'status' => 'error',
                'message' => $this->getError(),
            ]);
        }

        // Mark the token as used
        $auth_code->markAsUsed();

        $user = new \ProjectSend\Classes\Users($user_id);
        if ($user->isActive()) {
            $this->user = $user;
            $this->login($user);

            // Handle remember me
            if ($remember_me && get_option('remember_me_enabled', null, '1')) {
                $rememberMe = new \ProjectSend\Classes\RememberMe();
                $rmToken = $rememberMe->generateToken();
                if ($rememberMe->storeToken($user->id, $rmToken)) {
                    $rememberMe->setCookie($rmToken);
                }
            }

            $results = [
                'status' => 'success',
                'user_id' => $user->id,
                'location' => $user->isClient() ? CLIENT_VIEW_FILE_LIST_URL : BASE_URI . "dashboard.php",
            ];

            if ($is_backup) {
                $remaining = $totp->getRemainingBackupCodesCount($user_id);
                $results['backup_code_used'] = true;
                $results['remaining_backup_codes'] = $remaining;
            }

            return json_encode($results);
        }

        return json_encode([
            'status' => 'error',
            'message' => $this->error_strings['2fa']['invalid'],
        ]);
    }

    public function authenticate($username, $password, $remember_me = false)
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
                $user = new \ProjectSend\Classes\Users($row['id']);
                $this->user = $user;
            }

			if (password_verify($password, $user->getRawPassword())) {
				if ($user->isActive()) {
                    $new2fa = new \ProjectSend\Classes\AuthenticationCode();
                    if ($new2fa->requires2fa($user->id)) {
                        $method = $new2fa->get2faMethod($user->id);

                        if ($method === 'totp') {
                            // User has TOTP configured - create token and redirect to TOTP form
                            $request2fa = json_decode($new2fa->createTotpToken($user->id));
                            if ($request2fa->status == 'success') {
                                $results = [
                                    'status' => 'success',
                                    'user_id' => $user->id,
                                    'location' => BASE_URI."index.php?form=2fa_verify_totp&remember_me=". (int)$remember_me ."&token=".$request2fa->token,
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
                        } elseif ($method === 'totp_setup_required') {
                            // TOTP is required but user hasn't set it up yet
                            // Log them in and let the middleware redirect to TOTP setup
                            $this->login($user);

                            if ($remember_me && get_option('remember_me_enabled', null, '1')) {
                                $rememberMe = new \ProjectSend\Classes\RememberMe();
                                $token = $rememberMe->generateToken();
                                if ($rememberMe->storeToken($user->id, $token)) {
                                    $rememberMe->setCookie($token);
                                }
                            }

                            $results = [
                                'status' => 'success',
                                'user_id' => $user->id,
                                'location' => BASE_URI . 'totp-setup.php',
                            ];
                            return json_encode($results);
                        }

                        // Email-based 2FA (default)
                        $request2fa = json_decode($new2fa->requestNewCode($user->id));
                        if ($request2fa->status == 'success') {
                            $results = [
                                'status' => 'success',
                                'user_id' => $user->id,
                                'location' => BASE_URI."index.php?form=2fa_verify&remember_me=". (int)$remember_me ."&token=".$request2fa->token,
                            ];
                        } else {
                            // Throttled: a pending code already exists. Find it and redirect to the form.
                            $pending = $new2fa->getLatestPendingToken($user->id);
                            if ($pending) {
                                $results = [
                                    'status' => 'success',
                                    'user_id' => $user->id,
                                    'location' => BASE_URI."index.php?form=2fa_verify&remember_me=". (int)$remember_me ."&token=".$pending,
                                ];
                            } else {
                                $this->setError($request2fa->message);
                                $results = [
                                    'status' => 'error',
                                    'message' => $request2fa->message,
                                    'location' => BASE_URI,
                                ];
                            }
                        }

                        return json_encode($results);
                    }

                    // When 2FA is not required, login
                    $this->login($user);

                    // Handle remember me functionality
                    if ($remember_me && get_option('remember_me_enabled', null, '1')) {
                        $rememberMe = new \ProjectSend\Classes\RememberMe();
                        $token = $rememberMe->generateToken();
                        if ($rememberMe->storeToken($user->id, $token)) {
                            $rememberMe->setCookie($token);
                        }
                    }

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

        // Validate provider is in our supported list
        $supported_providers = ['google', 'facebook', 'linkedin', 'x', 'windowslive', 'yahoo', 'microsoftgraph', 'genericoidc'];
        if (!in_array(strtolower($provider), $supported_providers)) {
            exit_with_error_code(404);
        }

        global $hybridauth;
        $adapter = $hybridauth->authenticate($provider);
        if ($adapter->isConnected()) {
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
                $user = new \ProjectSend\Classes\Users($row['id']);
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
            if (get_option('clients_can_register') == '0') {
                $this->setError($this->error_strings['no_self_registration']);
                ps_redirect(BASE_URI);
            }

            $email_parts = explode('@', $userProfile->email);
            $username = (!username_exists($email_parts[0])) ? $email_parts[0] : generate_username($email_parts[0]);
            $password = generate_random_password();

            // Get social login settings
            $auto_enable = get_option('social_login_auto_enable', null, 'true') == 'true';
            $default_role = get_option('social_login_default_role', null, '0');

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
                'role' => $default_role,
                'max_file_size' => 0,
                'notify_upload' => 1,
                'notify_account' => 1,
                'active' => $auto_enable ? 1 : 0,
                'account_requested'	=> $auto_enable ? 0 : 1,
                'type' => 'new_client',
                'recaptcha' => null,
            ]);

            $new_response = $new_client->create();
            if (!empty($new_response['id'])) {
                $new_client->triggerAfterSelfRegister();

                // Save social network profile as metadata
                $meta_name = 'social_network';
                $meta_value = json_encode($userProfile);
                $statement = $this->dbh->prepare("INSERT INTO " . TABLE_USER_META . " (user_id, name, value)"
                                ."VALUES (:id, :name, :value)");
                $statement->bindParam(':id', $new_response['id'], PDO::PARAM_INT);
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

                if ($auto_enable) {
                    $this->authenticate($username, $password);
                    $redirect_to = 'my_files/index.php';
                }

                // Redirect
                ps_redirect($redirect_to);
            }
        }
    }

    public function loginLdap($email, $password, $language, $remember_me = false)
    {
        global $logger;
        
        // Debug logging
        error_log("LDAP Login Debug - Starting authentication for: " . $email);
        
        if ( !$email || !$password ) {
            error_log("LDAP Login Debug - Empty email or password");
            $return = [
                'status' => 'error',
                'message' => __("Email and password cannot be empty.",'cftp_admin')
            ];
    
            return json_encode($return);    
        }

		$selected_form_lang = (!empty( $language ) ) ? $language : SITE_LANG;

        // Bind to server
        $ldap_server = get_option('ldap_hosts');
        $ldap_bind_dn = get_option('ldap_bind_dn');
        $ldap_admin_user = get_option('ldap_admin_user');
        $ldap_admin_password = get_option('ldap_admin_password');
        
        // Debug logging
        error_log("LDAP Login Debug - Server: " . $ldap_server);
        error_log("LDAP Login Debug - Bind DN: " . $ldap_bind_dn);
        error_log("LDAP Login Debug - Admin User: " . $ldap_admin_user);

        try {
            $ldap = ldap_connect($ldap_server);
            error_log("LDAP Login Debug - Connected to server successfully");
        } catch (\Exception $e) {
            error_log("LDAP Login Debug - Connection failed: " . $e->getMessage());
            $return = [
                'status' => 'error',
                'message' => sprintf(__("LDAP connection error: %s", 'cftp_admin'), $e->getMessage())
            ];

            return json_encode($return);
        }

        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, get_option('ldap_protocol_version', null, '3'));
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        try {
            error_log("LDAP Login Debug - Attempting admin bind");
            $bind = ldap_bind($ldap, $ldap_admin_user, $ldap_admin_password);
            if ($bind) {
                error_log("LDAP Login Debug - Admin bind successful");
                $ldap_search_base = get_option('ldap_search_base');
                error_log("LDAP Login Debug - Search base: " . $ldap_search_base);
                
                $arr = array('dn', 1);
                error_log("LDAP Login Debug - Searching for user: " . $email);
                $result = @ldap_search($ldap, $ldap_search_base, "(mail=$email)", $arr);
                $entries = @ldap_get_entries($ldap, $result);
                
                error_log("LDAP Login Debug - Search result count: " . ($entries ? $entries['count'] : 'false'));

                if ($entries['count'] > 0) {
                    // Bind with user to verify password
                    if (ldap_bind($ldap, $entries[0]['dn'], $password)) {
                        // Get full LDAP attributes for user creation/sync
                        $ldap_user_dn = $entries[0]['dn'];
                        $attributes = ['mail', 'displayName', 'cn', 'name', 'telephoneNumber', 'mobile', 'postalAddress', 'streetAddress', 'department', 'title', 'company', 'manager'];
                        $user_result = @ldap_search($ldap, $ldap_user_dn, "(objectClass=*)", $attributes);
                        $user_data = @ldap_get_entries($ldap, $user_result);
                        
                        if ($user_data['count'] > 0) {
                            $ldap_attributes = $user_data[0];
                            $ldap_attributes['dn'] = $ldap_user_dn; // Store DN for metadata
                        } else {
                            $ldap_attributes = ['dn' => $ldap_user_dn];
                        }

                        // Check if user exists in local database
                        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE email = :email");
                        $statement->execute([':email' => $email]);
                        
                        if ($statement->rowCount() > 0) {
                            // User exists - login and sync data
                            $row = $statement->fetch(PDO::FETCH_ASSOC);
                            $user = new \ProjectSend\Classes\Users($row['id']);
                            $this->user = $user;
                            
                            // Sync user data from LDAP if this is an LDAP user
                            if ($user->isLdapUser()) {
                                $user->syncFromLdap($ldap_attributes);
                            }
                            
                            if ($user->isActive()) {
                                // Check for 2FA requirement
                                $new2fa = new \ProjectSend\Classes\AuthenticationCode();
                                if ($new2fa->requires2fa($user->id)) {
                                    $method = $new2fa->get2faMethod($user->id);

                                    if ($method === 'totp') {
                                        $request2fa = json_decode($new2fa->createTotpToken($user->id));
                                        if ($request2fa->status == 'success') {
                                            $results = [
                                                'status' => 'success',
                                                'user_id' => $user->id,
                                                'location' => BASE_URI."index.php?form=2fa_verify_totp&remember_me=". (int)$remember_me ."&token=".$request2fa->token,
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

                                    // Email-based 2FA (default)
                                    $request2fa = json_decode($new2fa->requestNewCode($user->id));
                                    if ($request2fa->status == 'success') {
                                        $results = [
                                            'status' => 'success',
                                            'user_id' => $user->id,
                                            'location' => BASE_URI."index.php?form=2fa_verify&remember_me=". (int)$remember_me ."&token=".$request2fa->token,
                                        ];
                                    } else {
                                        // Throttled: find pending token and redirect to form
                                        $pending = $new2fa->getLatestPendingToken($user->id);
                                        if ($pending) {
                                            $results = [
                                                'status' => 'success',
                                                'user_id' => $user->id,
                                                'location' => BASE_URI."index.php?form=2fa_verify&remember_me=". (int)$remember_me ."&token=".$pending,
                                            ];
                                        } else {
                                            $this->setError($request2fa->message);
                                            $results = [
                                                'status' => 'error',
                                                'message' => $request2fa->message,
                                                'location' => BASE_URI,
                                            ];
                                        }
                                    }

                                    return json_encode($results);
                                }
                                
                                // Login the user
                                $this->login($user);
                                
                                // Handle remember me functionality for LDAP
                                if ($remember_me && get_option('remember_me_enabled', null, '1')) {
                                    $rememberMe = new \ProjectSend\Classes\RememberMe();
                                    $token = $rememberMe->generateToken();
                                    if ($rememberMe->storeToken($user->id, $token)) {
                                        $rememberMe->setCookie($token);
                                    }
                                }
                                
                                // Log the LDAP login
                                $this->logger->addEntry([
                                    'action' => 45, // New action for LDAP login
                                    'owner_id' => $user->id,
                                    'owner_user' => $user->username,
                                    'affected_account_name' => $user->name,
                                    'details' => 'LDAP authentication successful'
                                ]);
                                
                                $return = [
                                    'status' => 'success',
                                    'user_id' => $user->id,
                                    'location' => $user->isClient() ? CLIENT_VIEW_FILE_LIST_URL : BASE_URI."dashboard.php",
                                ];
                    
                                return json_encode($return);
                            } else {
                                $return = [
                                    'status' => 'error',
                                    'message' => $this->getAccountInactiveError()
                                ];
                    
                                return json_encode($return);
                            }
                        } else {
                            // User doesn't exist - create new user if LDAP user creation is enabled
                            if (get_option('ldap_auto_create_users', null, 'true') == 'true') {
                                $new_user = new \ProjectSend\Classes\Users();
                                $create_result = $new_user->createFromLdap($ldap_attributes, $email);

                                if (!empty($create_result['id'])) {
                                    // Get the created user and login
                                    $user = new \ProjectSend\Classes\Users($create_result['id']);
                                    $this->user = $user;
                                    $this->login($user);
                                    
                                    // Handle remember me functionality for new LDAP user
                                    if ($remember_me && get_option('remember_me_enabled', null, '1')) {
                                        $rememberMe = new \ProjectSend\Classes\RememberMe();
                                        $token = $rememberMe->generateToken();
                                        if ($rememberMe->storeToken($user->id, $token)) {
                                            $rememberMe->setCookie($token);
                                        }
                                    }
                                    
                                    $return = [
                                        'status' => 'success',
                                        'user_id' => $user->id,
                                        'location' => $user->isClient() ? CLIENT_VIEW_FILE_LIST_URL : BASE_URI."dashboard.php",
                                    ];
                        
                                    return json_encode($return);
                                } else {
                                    $return = [
                                        'status' => 'error',
                                        'message' => __("Error creating user account from LDAP.", 'cftp_admin')
                                    ];
                        
                                    return json_encode($return);
                                }
                            } else {
                                // User creation disabled
                                $return = [
                                    'status' => 'error',
                                    'message' => __("Your LDAP account is not authorized. Please contact an administrator.", 'cftp_admin')
                                ];
                    
                                return json_encode($return);
                            }
                        }
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
                    error_log("LDAP Login Debug - User not found in LDAP");
                    $this->setError(__("The supplied email or password does not match an existing record.", 'cftp_admin'));
                    $return = [
                        'status' => 'error',
                        'message' => __("The supplied email or password does not match an existing record.", 'cftp_admin')
                    ];
        
                    return json_encode($return);        
                }
            }
            else {
                error_log("LDAP Login Debug - Admin bind failed");
                $this->setError(__("Error binding to LDAP server.",'cftp_admin'));
                $return = [
                    'status' => 'error',
                    'message' => __("Error binding to LDAP server.",'cftp_admin')
                ];
    
                return json_encode($return);    
            }
        } catch (\Exception $e) {
            error_log("LDAP Login Debug - Exception caught: " . $e->getMessage());
            error_log("LDAP Login Debug - Exception trace: " . $e->getTraceAsString());
            $this->setError($e->getMessage());
            $return = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];

            return json_encode($return);
        }
    }

    /**
     * Test LDAP connection with current settings
     */
    public function testLdapConnection()
    {
        $errors = [];
        $success_messages = [];

        // Check if LDAP extension is loaded
        if (!extension_loaded('ldap')) {
            return [
                'status' => 'error',
                'message' => __('LDAP extension is not loaded in PHP.', 'cftp_admin')
            ];
        }

        // Get LDAP settings
        $ldap_server = get_option('ldap_hosts');
        $ldap_bind_dn = get_option('ldap_bind_dn');
        $ldap_admin_user = get_option('ldap_admin_user');
        $ldap_admin_password = get_option('ldap_admin_password');
        $ldap_protocol_version = get_option('ldap_protocol_version', null, '3');

        // Validate required settings
        if (empty($ldap_server)) {
            $errors[] = __('LDAP server is not configured.', 'cftp_admin');
        }
        if (empty($ldap_admin_user)) {
            $errors[] = __('LDAP admin user is not configured.', 'cftp_admin');
        }
        if (empty($ldap_admin_password)) {
            $errors[] = __('LDAP admin password is not configured.', 'cftp_admin');
        }

        if (!empty($errors)) {
            return [
                'status' => 'error',
                'message' => implode(' ', $errors)
            ];
        }

        try {
            // Step 1: Connect to LDAP server
            $ldap = ldap_connect($ldap_server);
            if (!$ldap) {
                return [
                    'status' => 'error',
                    'message' => __('Could not connect to LDAP server.', 'cftp_admin')
                ];
            }
            $success_messages[] = __('Successfully connected to LDAP server.', 'cftp_admin');

            // Step 2: Set LDAP options
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldap_protocol_version);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            $success_messages[] = sprintf(__('LDAP protocol version set to %s.', 'cftp_admin'), $ldap_protocol_version);

            // Step 3: Bind with admin credentials
            $bind = ldap_bind($ldap, $ldap_admin_user, $ldap_admin_password);
            if (!$bind) {
                ldap_close($ldap);
                return [
                    'status' => 'error',
                    'message' => __('Could not bind to LDAP server with admin credentials. Please check username and password.', 'cftp_admin')
                ];
            }
            $success_messages[] = __('Successfully authenticated with admin credentials.', 'cftp_admin');

            // Step 4: Test search base if configured
            $ldap_search_base = get_option('ldap_search_base');
            if (!empty($ldap_search_base)) {
                // Suppress size limit warnings - we only need to test accessibility
                $search_result = @ldap_search($ldap, $ldap_search_base, "(objectClass=*)", [], 0, 1);
                if ($search_result) {
                    $entries = @ldap_get_entries($ldap, $search_result);
                    $success_messages[] = sprintf(__('Search base "%s" is accessible (%d entries found).', 'cftp_admin'), $ldap_search_base, $entries['count']);
                } else {
                    $ldap_error = ldap_error($ldap);
                    $errors[] = sprintf(__('Could not search in base "%s": %s', 'cftp_admin'), $ldap_search_base, $ldap_error);
                }
            }

            // Close connection
            ldap_close($ldap);

            if (!empty($errors)) {
                return [
                    'status' => 'warning',
                    'message' => implode(' ', $success_messages) . ' ' . __('However, there were some issues: ', 'cftp_admin') . implode(' ', $errors)
                ];
            }

            return [
                'status' => 'success',
                'message' => implode(' ', $success_messages) . ' ' . __('LDAP connection test completed successfully.', 'cftp_admin')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => sprintf(__('LDAP connection test failed: %s', 'cftp_admin'), $e->getMessage())
            ];
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

    public function logout($clear_remember_me = true)
    {
        // Clear remember me token if enabled
        if ($clear_remember_me && get_option('remember_me_enabled', null, '1')) {
            $rememberMe = new \ProjectSend\Classes\RememberMe();
            
            // Get current token from cookie before clearing session
            $current_token = $rememberMe->getTokenFromCookie();
            
            if ($current_token) {
                // Only revoke current token, not all user tokens
                $rememberMe->revokeToken($current_token);
            }
            
            $rememberMe->clearCookie();
        }

        header("Cache-control: private");
		$_SESSION = [];
        session_destroy();
        session_regenerate_id(true);
        
        global $hybridauth;
        if (!empty($hybridauth)) {
            try {
                $hybridauth->disconnectAllAdapters();
            } catch (\Exception $e) {
                // Silently fail if disconnect fails - user is already logged out locally
                error_log('HybridAuth disconnect error: ' . $e->getMessage());
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
    }

    /**
     * Attempt automatic login using remember me token
     * @return bool Success
     */
    public function loginWithRememberMe()
    {
        if (!get_option('remember_me_enabled', null, '1')) {
            return false;
        }

        $rememberMe = new \ProjectSend\Classes\RememberMe();
        $token = $rememberMe->getTokenFromCookie();
        
        if (!$token) {
            return false;
        }

        $user_data = $rememberMe->validateToken($token);
        
        if (!$user_data) {
            // Clear invalid cookie
            $rememberMe->clearCookie();
            return false;
        }

        // Create user object and login
        $user = new \ProjectSend\Classes\Users($user_data['user_id']);
        if ($user->userExists() && $user->isActive()) {
            $this->login($user);
            return true;
        } else {
            // User no longer exists or is inactive, revoke token
            $rememberMe->revokeToken($token);
            $rememberMe->clearCookie();
            return false;
        }
    }


    /**
     * Logout from all devices (revoke all remember me tokens)
     */
    public function logoutFromAllDevices()
    {
        if (isset($_SESSION['user_id'])) {
            $rememberMe = new \ProjectSend\Classes\RememberMe();
            $rememberMe->revokeUserTokens($_SESSION['user_id']);
            $rememberMe->clearCookie();
        }

        $this->logout(false); // Don't double-clear remember me tokens
    }
}
