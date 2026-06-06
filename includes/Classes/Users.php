<?php

/**
 * Class that handles all the actions and functions that can be applied to
 * users accounts.
 */

namespace ProjectSend\Classes;

use \ProjectSend\Classes\Validation;
use \ProjectSend\Classes\GroupsMemberships;
use \PDO;

class Users
{
    private $dbh;
    private $logger;

    private $validation_type;
    private $validation_passed;
    private $validation_errors;
    private $is_ldap_creation = false;

    public $exists;

    public $id;
    public $name;
    public $email;
    public $username;
    public $password;
    public $password_raw;
    public $account_type;
    public $role_id;
    public $role; // Backward compatibility property
    public $active;
    public $notify_account;
    public $max_file_size;
    public ?int $max_disk_quota;
    public $can_upload_public;
    public $created_by;
    public $created_date;
    public $metadata;
    public $require_password_change;
    public $limit_upload_to;

    // Uploaded files
    public $files;

    // Groups where the client is member
    public $groups;

    // @todo implement meta data
    public $meta;

    // @todo Move this to meta
    public $address;
    public $phone;
    public $contact;
    public $notify_upload;
    public $account_request;
    public $recaptcha;

    // Custom field data
    public $custom_field_data;

    // Permissions
    private $allowed_actions_roles;

    public function __construct($user_id = null)
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        // Default to client role
        $this->role_id = \ProjectSend\Classes\Roles::getClientRoleId();
        $this->role = $this->role_id; // Backward compatibility

        $this->allowed_actions_roles = [9];
        $this->exists = false;
        $this->require_password_change = false;

        $this->metadata = [];
        $this->custom_field_data = [];

        if (!empty($user_id)) {
            $this->get($user_id);
        }
    }

    /**
     * Set the ID
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Return the ID
     * @return int
     */
    public function getId()
    {
        if (!empty($this->id)) {
            return $this->id;
        }

        return false;
    }

    /**
     * Get role data for this user
     * @return array|null
     */
    public function getRoleData()
    {
        if (empty($this->role_id)) {
            return null;
        }

        try {
            $role = new \ProjectSend\Classes\Roles($this->role_id);
            if ($role->exists()) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'is_system_role' => $role->is_system_role,
                    'active' => $role->active
                ];
            }
        } catch (Exception $e) {
            error_log("ProjectSend: Error getting role data for user {$this->id}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Get role name for this user
     * @return string
     */
    public function getRoleName()
    {
        $role_data = $this->getRoleData();
        return $role_data ? $role_data['name'] : __('Unknown Role', 'cftp_admin');
    }

    public function userExists()
    {
        return $this->exists;
    }

    /**
     * Set the validation type (user or client, new or edit)
     */
    public function setType($type)
    {
        $this->validation_type = $type;

        $this->setActionsPermissions();
    }

    /**
     * Set the permissions to delete, activate, deactivate, approve or deny an account
     */
    private function setActionsPermissions()
    {
        /* Allowed roles for:
            Delete users: System Admin only
            Delete clients: Admin and System Admin
        */
        if ($this->isClient()) {
            // Clients can be managed by Account Managers and System Admins
            $this->allowed_actions_roles = ['Account Manager', 'System Administrator'];
        } else {
            // System users can only be managed by System Admins
            $this->allowed_actions_roles = ['System Administrator'];
        }
    }

    /**
     * Set the properties when editing
     */
    public function set($arguments = [])
    {
        $this->name = (!empty($arguments['name'])) ? encode_html($arguments['name']) : null;
        $this->email = (!empty($arguments['email'])) ? encode_html($arguments['email']) : null;
        $this->username = (!empty($arguments['username'])) ? encode_html($arguments['username']) : null;
        $this->password = (!empty($arguments['password'])) ? $arguments['password'] : null;
        $this->role_id = (!empty($arguments['role_id'])) ? (int)$arguments['role_id'] : (!empty($arguments['role']) ? (int)$arguments['role'] : null);
        $this->role = $this->role_id; // Backward compatibility
        $this->active = (!empty($arguments['active'])) ? (int)$arguments['active'] : 0;
        $this->notify_account = (!empty($arguments['notify_account'])) ? (int)$arguments['notify_account'] : 0;
        $this->max_file_size = (!empty($arguments['max_file_size'])) ? (int)$arguments['max_file_size'] : 0;
        $this->max_disk_quota = (!empty($arguments['max_disk_quota'])) ? (int)$arguments['max_disk_quota'] : 0;
        $this->can_upload_public = (!empty($arguments['can_upload_public'])) ? (int)$arguments['can_upload_public'] : 0;
        $this->require_password_change = (!empty($arguments['require_password_change'])) ? $arguments['require_password_change'] : false;
        $this->limit_upload_to = (!empty($arguments['limit_upload_to'])) ? $arguments['limit_upload_to'] : null;

        // Specific for clients
        $this->address = (!empty($arguments['address'])) ? encode_html($arguments['address']) : null;
        $this->phone = (!empty($arguments['phone'])) ? encode_html($arguments['phone']) : null;
        $this->contact = (!empty($arguments['contact'])) ? encode_html($arguments['contact']) : null;
        $this->notify_upload = (!empty($arguments['notify_upload'])) ? (int)$arguments['notify_upload'] : 0;
        $this->account_request = (!empty($arguments['account_requested'])) ? (int)$arguments['account_requested'] : 0;
        $this->recaptcha = (!empty($arguments['recaptcha'])) ? $arguments['recaptcha'] : null;

        $this->setActionsPermissions();
    }

    /**
     * Get existing user data from the database
     * @return bool
     */
    public function get($id)
    {
        $this->id = $id;

        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        if ($statement->rowCount() == 0) {
            return false;
        }

        $this->exists = true;

        while ($row = $statement->fetch()) {
            $this->name = html_output($row['name']);
            $this->email = html_output($row['email']);
            $this->username = html_output($row['user']);
            $this->password = html_output($row['password']);
            $this->password_raw = $row['password'];
            $this->role_id = (int)$row['role_id'];
            $this->role = $this->role_id; // Backward compatibility
            $this->account_type = $this->isClient() ? 'client' : 'user';
            $this->active = html_output($row['active']);
            $this->max_file_size = ($row['max_file_size'] !== null) ? (int)$row['max_file_size'] : 0;
            $this->max_disk_quota = ($row['max_disk_quota'] !== null) ? (int)$row['max_disk_quota'] : 0;
            $this->created_date = html_output($row['timestamp']);
            $this->created_by = html_output($row['created_by']);

            // See if user requires password change
            if (user_meta_exists($this->id, 'require_password_change')) {
                $meta = (get_user_meta($this->id, 'require_password_change'));
                $this->require_password_change = ($meta['value'] == 'true') ? true : false;
            }

            $this->limit_upload_to = $this->limitUploadToGet();

            // Specific for clients
            $this->address = html_output($row['address']);
            $this->phone = html_output($row['phone']);
            $this->contact = html_output($row['contact']);
            $this->notify_upload = html_output($row['notify']);
            $this->can_upload_public = html_output($row['can_upload_public']);

            // Files
            $statement = $this->dbh->prepare("SELECT DISTINCT id FROM " . TABLE_FILES . " WHERE uploader = :username");
            $statement->bindParam(':username', $this->username);
            $statement->execute();

            if ($statement->rowCount() > 0) {
                $statement->setFetchMode(PDO::FETCH_ASSOC);
                while ($file = $statement->fetch()) {
                    $this->files[] = $file['id'];
                }
            }

            // Groups
            $groups_object = new \ProjectSend\Classes\GroupsMemberships();
            $this->groups = $groups_object->getGroupsByClient([
                'client_id' => $this->id
            ]);

            $this->validation_type = "existing_user";
        }

        $this->setActionsPermissions();

        return true;
    }

    public function requiresPasswordChange()
    {
        return $this->require_password_change;
    }

    public function getRawPassword()
    {
        return $this->password_raw;
    }

    /**
     * Return the current properties
     */
    public function getProperties()
    {
        $return = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'password' => $this->password,
            'role_id' => (int)$this->role_id,
            'active' => $this->active,
            'max_file_size' => $this->max_file_size,
            'max_disk_quota' => $this->max_disk_quota,
            'can_upload_public' => $this->can_upload_public,
            'created_date' => $this->created_date,
            'address' => $this->address,
            'phone' => $this->phone,
            'contact' => $this->contact,
            'notify_upload' => $this->notify_upload,
            'files' => $this->files,
            'groups' => $this->groups,
            'meta' => $this->meta,
            'limit_upload_to' => $this->limit_upload_to,
        ];

        return $return;
    }

    public function getAllMetaData()
    {
        $this->metadata = get_all_user_meta($this->id);

        return $this->metadata;
    }

    /**
     * Is user active
     * @return bool
     */
    public function isActive()
    {
        if ($this->active == 1) {
            return true;
        }

        return false;
    }

    public function canUploadPublic()
    {
        if (!$this->isClient()) {
            return true;
        }

        return client_can_upload_public($this->id);
    }

    public function isClient()
    {
        if (empty($this->role_id)) {
            return true; // Default to client if no role
        }

        try {
            $role = new \ProjectSend\Classes\Roles($this->role_id);
            return $role->isClientRole();
        } catch (Exception $e) {
            return true; // Default to client on error
        }
    }

    /**
     * Check if a user can edit this user account
     * @param int $user_id User ID to check (defaults to current user)
     * @return bool
     */
    public function canUserEdit($user_id = null)
    {
        if ($user_id === null) {
            if (defined('CURRENT_USER_ID')) {
                $user_id = \CURRENT_USER_ID;
            } else {
                return false; // No user logged in
            }
        }

        // Users can edit themselves
        if ($this->id == $user_id) {
            return true;
        }

        // Check based on account type
        if ($this->isClient()) {
            // For clients: Users with edit_clients permission can edit all clients
            if (\current_user_can('edit_clients')) {
                return true;
            }

            // Users with create_clients permission can edit their own created clients
            if (\current_user_can('create_clients') && $this->created_by == \CURRENT_USER_USERNAME) {
                return true;
            }
        } else {
            // For system users: Users with edit_users permission can edit all users
            if (\current_user_can('edit_users')) {
                return true;
            }

            // Users with create_users permission can edit their own created users
            if (\current_user_can('create_users') && $this->created_by == \CURRENT_USER_USERNAME) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate the information from the form.
     */
    public function validate()
    {
        global $json_strings;

        $validation = new \ProjectSend\Classes\Validation;
        $validate_password = false;

        $validation_items = [
            $this->name => [
                'required' => ['error' => $json_strings['validation']['no_name']],
            ],
            $this->email => [
                'required' => ['error' => $json_strings['validation']['no_email']],
                'email' => ['error' => $json_strings['validation']['invalid_email']],
            ],
            $this->role_id => [
                'required' => ['error' => $json_strings['validation']['no_role']],
            ],
            $this->max_file_size => [
                'number' => ['error' => $json_strings['validation']['file_size']],
            ],
            $this->max_disk_quota => [
                'number' => ['error' => $json_strings['validation']['disk_quota']],
            ],
        ];

        if ($this->validation_type == 'new_user' || $this->validation_type == 'new_client') {
            $validation_items[$this->email]['email_exists'] = ['error' => $json_strings['validation']['email_exists']];
            $validation_items[$this->username] = [
                'required' => ['error' => $json_strings['validation']['no_user']],
                'user_exists' => ['error' => $json_strings['validation']['user_exists']],
                'alpha_underscores' => ['error' => $json_strings['validation']['alpha_user']],
                'length' => ['error' => $json_strings['validation']['length_user'], 'min' => MIN_USER_CHARS, 'max' => MAX_USER_CHARS],
            ];

            $validate_password = true;
        } else if ($this->validation_type == 'existing_user') {
            $validation_items[$this->email]['email_exists'] = ['error' => $json_strings['validation']['email_exists'], 'id_ignore' => $this->id];

            // Changing password is optional.
            if (!empty($this->password)) {
                $validate_password = true;
            }
        }

        // Password checks
        if ($validate_password === true) {
            $validation_items[$this->password] = [
                'required' => ['error' => $json_strings['validation']['no_pass']],
                'password' => ['error' => $json_strings['validation']['valid_pass'] . " " . addslashes($json_strings['validation']['valid_chars'])],
                'password_rules' => ['error' => $json_strings['validation']['rules_pass']],
                'length' => ['error' => $json_strings['validation']['length_pass'], 'min' => MIN_PASS_CHARS, 'max' => MAX_PASS_CHARS],
            ];
        }

        if (!empty($this->recaptcha)) {
            $validation_items[$this->recaptcha]['recaptcha2'] = ['error' => $json_strings['validation']['recaptcha']];
        }

        $validation->validate_items($validation_items);

        if ($validation->passed()) {
            $this->validation_passed = true;
            return true;
        } else {
            $this->validation_passed = false;
            $this->validation_errors = $validation->list_errors();
        }

        return false;
    }

    /**
     * Return the validation errors the the front end
     */
    public function getValidationErrors()
    {
        if (!empty($this->validation_errors)) {
            return $this->validation_errors;
        }

        return false;
    }

    private function hashPassword($password)
    {
        $hashed = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST_LOG2]);
        return $hashed;
    }

    /**
     * Create a new user.
     */
    public function create()
    {
        // Check permissions based on account type
        if ($this->isClient()) {
            // Allow self-registration if not logged in and clients_can_register is enabled
            $is_self_registration = ($this->validation_type == 'new_client' && !user_is_logged_in() && get_option('clients_can_register') == '1');
            // Allow LDAP auto-creation of client accounts
            $is_ldap_client_creation = ($this->is_ldap_creation && !user_is_logged_in() && get_option('ldap_auto_create_users', null, 'true') == 'true');

            if (!$is_self_registration && !$is_ldap_client_creation && !\current_user_can('create_clients')) {
                return [
                    'status' => 'error',
                    'message' => __('You do not have permission to create clients.', 'cftp_admin')
                ];
            }
        } else {
            // Allow LDAP auto-creation if not logged in and LDAP auto-create is enabled
            $is_ldap_auto_creation = ($this->is_ldap_creation && !user_is_logged_in() && get_option('ldap_auto_create_users', null, 'true') == 'true');

            if (!$is_ldap_auto_creation && !\current_user_can('create_users')) {
                return [
                    'status' => 'error',
                    'message' => __('You do not have permission to create users.', 'cftp_admin')
                ];
            }
        }

        if (!$this->validate()) {
            return [
                'status' => 'error',
                'message' => __('Validation errors occurred.', 'cftp_admin'),
                'errors' => $this->getValidationErrors()
            ];
        }

        $password_hashed = $this->hashPassword($this->password);

        $hash_info = $password_hashed ? password_get_info($password_hashed) : null;
        if ($hash_info && $hash_info['algo'] !== null && $hash_info['algo'] !== 0) {
            /** Who is creating the client? */
            $this->created_by = (defined('CURRENT_USER_USERNAME')) ? CURRENT_USER_USERNAME : null;

            /** Insert the client information into the database */
            $statement = $this->dbh->prepare(
                "INSERT INTO " . TABLE_USERS . " (
                    name, user, password, role_id, address, phone, email, notify, contact, created_by, active, account_requested, max_file_size, max_disk_quota, can_upload_public
                )
                VALUES (
                    :name, :username, :password, :role_id, :address, :phone, :email, :notify_upload, :contact, :created_by, :active, :request, :max_file_size, :max_disk_quota, :can_upload_public
                )"
            );
            $statement->bindParam(':name', $this->name);
            $statement->bindParam(':username', $this->username);
            $statement->bindParam(':password', $password_hashed);
            $statement->bindParam(':role_id', $this->role_id, PDO::PARAM_INT);
            $statement->bindParam(':address', $this->address);
            $statement->bindParam(':phone', $this->phone);
            $statement->bindParam(':email', $this->email);
            $statement->bindParam(':notify_upload', $this->notify_upload, PDO::PARAM_INT);
            $statement->bindParam(':contact', $this->contact);
            $statement->bindParam(':created_by', $this->created_by);
            $statement->bindParam(':active', $this->active, PDO::PARAM_INT);
            $statement->bindParam(':request', $this->account_request, PDO::PARAM_INT);
            $statement->bindParam(':max_file_size', $this->max_file_size, PDO::PARAM_INT);
            $statement->bindParam(':max_disk_quota', $this->max_disk_quota, PDO::PARAM_INT);
            $statement->bindParam(':can_upload_public', $this->can_upload_public, PDO::PARAM_INT);
            $statement->execute();

            if ($statement) {
                $this->id = $this->dbh->lastInsertId();

                if ($this->require_password_change == true) {
                    save_user_meta($this->id, 'require_password_change', 'true');
                }

                // Uploader role: limit who user can upload to
                $this->limitUploadToSave($this->limit_upload_to);

                // Process custom field data if provided
                if (!empty($this->custom_field_data)) {
                    $custom_fields_values = new \ProjectSend\Classes\CustomFieldValues();
                    $applies_to = $this->isClient() ? 'client' : 'user';
                    $custom_fields_values->saveUserValues($this->id, $this->custom_field_data);
                }

                $email_type = $this->isClient() ? "new_client" : "new_user";

                /** Send account data by email */
                $email_status = 2; // Default: not sent
                $notify_user = new \ProjectSend\Classes\Emails;
                if ($this->notify_account == 1) {
                    if ($notify_user->send([
                        'type'        => $email_type,
                        'address'    => $this->email,
                        'username'    => $this->username,
                        'password'    => $this->password
                    ])) {
                        $email_status = 1; // Success
                    } else {
                        $email_status = 0; // Failed
                    }
                }

                return [
                    'status' => 'success',
                    'id' => $this->id,
                    'email' => $email_status,
                    'message' => $this->isClient() ? __('Client created successfully.', 'cftp_admin') : __('User created successfully.', 'cftp_admin')
                ];
            }
        }

        return [
            'status' => 'error',
            'message' => __('Failed to create user.', 'cftp_admin')
        ];
    }

    public function triggerAfterSelfRegister($arguments = null)
    {
        define('REGISTERING', true);

        /**
         * Check if the option to auto-add to a group
         * is active.
         */
        if (get_option('clients_auto_group') != '0' && get_option('clients_auto_approve') == 1) {
            $this->addToAutoGroup();
        }

        /**
         * Check if the client requested memberships to groups
         */
        if (!empty($arguments['groups'])) {
            $request = new \ProjectSend\Classes\GroupsMemberships;
            $request->groupRequestMembership([
                'client_id' => $this->id,
                'group_ids' => $arguments['groups'],
                'request_by' => $this->created_by,
            ]);
        }

        /**
         * Prepare and send an email to administrator(s)
         */
        $notify_admin = new \ProjectSend\Classes\Emails;
        $email_arguments = array(
            'type' => 'new_client_self',
            'address' => get_option('admin_email_address'),
            'username' => $this->username,
            'name' => $this->name,
        );
        if (!empty($execute_requests['requests'])) {
            $email_arguments['memberships'] = $execute_requests['requests'];
        }

        $notify_admin->send($email_arguments);
    }

    /**
     * Edit an existing user.
     * @return array Result with status and message
     */
    public function edit()
    {
        if (empty($this->id)) {
            return [
                'status' => 'error',
                'message' => __('User ID is required for editing.', 'cftp_admin')
            ];
        }

        // Check permissions
        $current_user_id = defined('CURRENT_USER_ID') ? \CURRENT_USER_ID : null;
        $current_username = defined('CURRENT_USER_USERNAME') ? \CURRENT_USER_USERNAME : null;

        $can_edit = false;

        // Users can edit themselves
        if ($this->id == $current_user_id) {
            $can_edit = true;
        }
        // Check based on account type
        elseif ($this->isClient()) {
            // For clients: Users with edit_clients permission can edit all clients
            if (\current_user_can('edit_clients')) {
                $can_edit = true;
            }
            // Users with create_clients permission can edit their own created clients
            elseif (\current_user_can('create_clients') && $this->created_by == $current_username) {
                $can_edit = true;
            }
        } else {
            // For system users: Users with edit_users permission can edit all users
            if (\current_user_can('edit_users')) {
                $can_edit = true;
            }
            // Users with create_users permission can edit their own created users
            elseif (\current_user_can('create_users') && $this->created_by == $current_username) {
                $can_edit = true;
            }
        }

        if (!$can_edit) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to edit this user.', 'cftp_admin')
            ];
        }

        if (!$this->validate()) {
            return [
                'status' => 'error',
                'message' => __('Validation errors occurred.', 'cftp_admin'),
                'errors' => $this->getValidationErrors()
            ];
        }

        $previous_data = get_user_by_id($this->id);
        if ($previous_data['active'] != $this->active) {
            $this->setActiveStatus($this->active);
        }

        // Some fields should not be allowed to be written if the current user is not a client,
        // as they are meant to be null for system users
        if (!$this->isClient()) {
            $this->address = null;
            $this->phone = null;
            $this->contact = null;
        }

        /** SQL query */
        $query = "UPDATE " . TABLE_USERS . " SET
                                    name = :name,
                                    role_id = :role_id,
                                    address = :address,
                                    phone = :phone,
                                    email = :email,
                                    contact = :contact,
                                    notify = :notify_upload,
                                    max_file_size = :max_file_size,
                                    max_disk_quota = :max_disk_quota,
                                    can_upload_public = :can_upload_public
                                    ";

        /** Add the password to the query if it's not the dummy value '' */
        if (!empty($this->password)) {
            $query .= ", password = :password";
        }

        $query .= " WHERE id = :id";

        $statement = $this->dbh->prepare($query);
        $statement->bindParam(':name', $this->name);
        $statement->bindParam(':role_id', $this->role_id, PDO::PARAM_INT);
        $statement->bindParam(':address', $this->address);
        $statement->bindParam(':phone', $this->phone);
        $statement->bindParam(':email', $this->email);
        $statement->bindParam(':contact', $this->contact);
        $statement->bindParam(':notify_upload', $this->notify_upload, PDO::PARAM_INT);
        $statement->bindParam(':max_file_size', $this->max_file_size, PDO::PARAM_INT);
        $statement->bindParam(':max_disk_quota', $this->max_disk_quota, PDO::PARAM_INT);
        $statement->bindParam(':can_upload_public', $this->can_upload_public, PDO::PARAM_INT);
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        if (!empty($this->password)) {
            $password_hashed = $this->hashPassword($this->password);
            $statement->bindParam(':password', $password_hashed);
        }

        $statement->execute();

        if ($statement) {
            // See if user requires password change
            if (user_meta_exists($this->id, 'require_password_change')) {
                if (!empty($this->password)) {
                    delete_user_meta($this->id, 'require_password_change');
                }
            }

            $this->limitUploadToSave($this->limit_upload_to);

            // Process custom field data if provided
            if (!empty($this->custom_field_data)) {
                $custom_fields_values = new \ProjectSend\Classes\CustomFieldValues();
                $applies_to = $this->isClient() ? 'client' : 'user';
                $custom_fields_values->saveUserValues($this->id, $this->custom_field_data);
            }

            $log_action_number = $this->isClient() ? 14 : 13;

            /** Record the action log */
            $this->logger->addEntry([
                'action' => $log_action_number,
                'owner_id' => defined('CURRENT_USER_ID') ? \CURRENT_USER_ID : 1,
                'affected_account' => $this->id,
                'affected_account_name' => $this->username,
                'username_column' => true
            ]);

            return [
                'status' => 'success',
                'message' => __('User updated successfully.', 'cftp_admin')
            ];
        }

        return [
            'status' => 'error',
            'message' => __('Failed to update user.', 'cftp_admin')
        ];
    }

    /**
     * Delete an existing user.
     */
    public function delete()
    {
        if (empty($this->id)) {
            return [
                'status' => 'error',
                'message' => __('User ID is required for deletion.', 'cftp_admin')
            ];
        }

        // Prevent self-deletion
        if ($this->id == (defined('CURRENT_USER_ID') ? \CURRENT_USER_ID : null)) {
            return [
                'status' => 'error',
                'message' => __('You cannot delete your own account.', 'cftp_admin')
            ];
        }

        // Check permissions based on account type
        $current_username = defined('CURRENT_USER_USERNAME') ? \CURRENT_USER_USERNAME : null;
        $can_delete = false;

        if ($this->isClient()) {
            // For clients: Users with delete_clients permission can delete all clients
            if (\current_user_can('delete_clients')) {
                $can_delete = true;
            }
            // Users with create_clients permission can delete their own created clients
            elseif (\current_user_can('create_clients') && $this->created_by == $current_username) {
                $can_delete = true;
            }
        } else {
            // For system users: Users with delete_users permission can delete all users
            if (\current_user_can('delete_users')) {
                $can_delete = true;
            }
            // Users with create_users permission can delete their own created users
            elseif (\current_user_can('create_users') && $this->created_by == $current_username) {
                $can_delete = true;
            }
        }

        if (!$can_delete) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to delete this user.', 'cftp_admin')
            ];
        }

        $sql = $this->dbh->prepare('DELETE FROM ' . TABLE_USERS . ' WHERE id=:id');
        $sql->bindParam(':id', $this->id, PDO::PARAM_INT);
        $sql->execute();

        $log_action_number = $this->isClient() ? 17 : 16;

        /** Record the action log */
        $this->logger->addEntry([
            'action' => $log_action_number,
            'owner_id' => defined('CURRENT_USER_ID') ? \CURRENT_USER_ID : 1,
            'affected_account_name' => $this->name,
        ]);

        return [
            'status' => 'success',
            'message' => $this->isClient() ? __('Client deleted successfully.', 'cftp_admin') : __('User deleted successfully.', 'cftp_admin')
        ];
    }

    /**
     * Mark the user as active or inactive.
     */
    public function setActiveStatus($change_to)
    {
        if ($this->id == CURRENT_USER_ID) {
            return false;
        }

        $user = $this->get($this->id);
        if (!$user) {
            return false;
        }

        switch ($change_to) {
            case 0:
                $log_action_number = $this->isClient() ? 20 : 28;
                break;
            case 1:
                $log_action_number = $this->isClient() ? 19 : 27;
                break;
            default:
                return false;
                break;
        }

        if (isset($this->id)) {
            /** Do a permissions check */
            if (isset($this->allowed_actions_roles) && current_role_in($this->allowed_actions_roles)) {
                $sql = $this->dbh->prepare('UPDATE ' . TABLE_USERS . ' SET active=:active_state WHERE id=:id');
                $sql->bindParam(':active_state', $change_to, PDO::PARAM_INT);
                $sql->bindParam(':id', $this->id, PDO::PARAM_INT);
                $sql->execute();

                /** Record the action log */
                $this->logger->addEntry([
                    'action' => $log_action_number,
                    'owner_id' => CURRENT_USER_ID,
                    'affected_account_name' => $this->name,
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Approve account
     */
    public function accountApprove()
    {
        if (isset($this->id)) {
            /** Do a permissions check */
            if (isset($this->allowed_actions_roles) && current_role_in($this->allowed_actions_roles)) {
                $sql = $this->dbh->prepare('UPDATE ' . TABLE_USERS . ' SET active=:active, account_requested=:requested, account_denied=:denied WHERE id=:id');
                $sql->bindValue(':active', 1, PDO::PARAM_INT);
                $sql->bindValue(':requested', 0, PDO::PARAM_INT);
                $sql->bindValue(':denied', 0, PDO::PARAM_INT);
                $sql->bindValue(':id', $this->id, PDO::PARAM_INT);
                $status = $sql->execute();

                /**
                 * Check if the option to auto-add to a group
                 * is active.
                 */
                if (get_option('clients_auto_group') != '0') {
                    $this->addToAutoGroup();
                }

                /** Record the action log */
                $this->logger->addEntry([
                    'action' => 44,
                    'owner_id' => CURRENT_USER_ID,
                    'affected_account_name' => $this->name,
                ]);

                return true;
            }
        }

        return false;
    }

    private function addToAutoGroup()
    {
        $group_id = get_option('clients_auto_group');

        $autogroup = new \ProjectSend\Classes\GroupsMemberships;
        $autogroup->clientAddToGroups([
            'client_id' => $this->id,
            'group_ids' => $group_id,
        ]);
    }

    /**
     * Deny account
     */
    public function accountDeny()
    {
        if (isset($this->id)) {
            /** Do a permissions check */
            if (isset($this->allowed_actions_roles) && current_role_in($this->allowed_actions_roles)) {
                $sql = $this->dbh->prepare('UPDATE ' . TABLE_USERS . ' SET active=:active, account_requested=:account_requested, account_denied=:account_denied WHERE id=:id');
                $sql->bindValue(':active', 0, PDO::PARAM_INT);
                $sql->bindValue(':account_requested', 1, PDO::PARAM_INT);
                $sql->bindValue(':account_denied', 1, PDO::PARAM_INT);
                $sql->bindValue(':id', $this->id, PDO::PARAM_INT);
                $status = $sql->execute();

                /** Record the action log */
                $this->logger->addEntry([
                    'action' => 45,
                    'owner_id' => CURRENT_USER_ID,
                    'affected_account_name' => $this->name,
                ]);

                return true;
            }
        }

        return false;
    }

    // Methods to handle who this user is limited to upload to. Only Uploader role
    /**
     * Get from database. Returns array of client ids
     *
     * @return array
     */
    private function limitUploadToGet()
    {
        $clients_ids = [];
        if (!table_exists(TABLE_USER_LIMIT_UPLOAD_TO)) {
            return $clients_ids;
        }

        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_USER_LIMIT_UPLOAD_TO . " WHERE user_id = :user_id");
        $statement->bindParam(':user_id', $this->id, PDO::PARAM_INT);
        $statement->execute();
        if ($statement->rowCount() > 0) {
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            while ($row = $statement->fetch()) {
                $clients_ids[] = $row['client_id'];
            }
        }

        $this->limit_upload_to = $clients_ids;

        return $clients_ids;
    }

    private function limitUploadToSave($clients_ids = [])
    {
        // Check if current user can manage upload restrictions
        if (!current_user_can('edit_users')) {
            return;
        }

        if (defined('CURRENT_USER_ID') && CURRENT_USER_ID == $this->id) {
            return;
        }

        $current_client_ids = $this->limitUploadToGet();

        // Remove clients that are not in the new array
        $delete = [];
        foreach ($current_client_ids as $client_id) {
            if (empty($clients_ids) || !in_array($client_id, $clients_ids)) {
                $delete[] = $client_id;
            }
        }

        if (!empty($delete)) {
            $delete = implode(',', $delete);
            $statement = $this->dbh->prepare("DELETE FROM " . TABLE_USER_LIMIT_UPLOAD_TO . " WHERE user_id = :user_id AND FIND_IN_SET(client_id, :delete)");
            $statement->bindParam(':user_id', $this->id);
            $statement->bindParam(':delete', $delete);
            $statement->execute();

            $this->limit_upload_to = [];
        }

        // Add those that are new and do not exist in the database
        $add = [];
        foreach ($clients_ids as $client_id) {
            if (!in_array($client_id, $current_client_ids)) {
                $add[] = $client_id;
            }
        }

        foreach ($add as $client_id) {
            $statement = $this->dbh->prepare("INSERT INTO " . TABLE_USER_LIMIT_UPLOAD_TO . " (user_id, client_id) VALUES (:user_id, :client_id)");
            $statement->bindParam(':user_id', $this->id);
            $statement->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $statement->execute();
        }

        // Get again to refresh the properties
        $this->limitUploadToGet();
    }

    public function shouldLimitUploadTo()
    {
        if (!$this->isClient()) {
            try {
                $role = new \ProjectSend\Classes\Roles($this->role_id);
                return $role->name === 'Uploader';
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
    }

    public function validatePassword($password = null)
    {
        if (empty($password)) {
            return [
                'status' => 'error',
                'message' => null,
            ];
        }

        // Validate password
        global $json_strings;
        $validation = new \ProjectSend\Classes\Validation;
        $validation->validate_items([
            $_POST['password'] => [
                'required' => ['error' => $json_strings['validation']['no_pass']],
                'password' => ['error' => $json_strings['validation']['valid_pass'] . ' ' . $json_strings['validation']['valid_chars']],
                'password_rules' => ['error' => $json_strings['validation']['rules_pass']],
                'length' => ['error' => $json_strings['validation']['length_pass'], 'min' => MIN_PASS_CHARS, 'max' => MAX_PASS_CHARS],
            ],
        ]);

        return $validation->passed();
    }

    public function setNewPassword($password = null)
    {
        if (empty($this->id)) {
            return false;
        }

        if (empty($password)) {
            return false;
        }

        if (!$this->validatePassword($password)) {
            return false;
        }

        $hashed = $this->hashPassword($password);
        if (strlen($hashed) >= 20) {
            $statement = $this->dbh->prepare("UPDATE " . TABLE_USERS . " SET password = :password WHERE id = :id");
            $statement->bindParam(':password', $hashed);
            $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
            if ($statement->execute()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a new user from LDAP authentication
     */
    public function createFromLdap($ldap_attributes, $email, $password = null)
    {
        // Extract user information from LDAP attributes
        $name = $this->extractLdapAttribute($ldap_attributes, ['displayName', 'cn', 'name'], $email);
        $username = $this->generateUsernameFromEmail($email);
        
        // Generate a random password since LDAP users authenticate via LDAP
        if (empty($password)) {
            $password = generate_random_password();
        }

        // Get default role for LDAP users
        $client_role_id = \ProjectSend\Classes\Roles::getClientRoleId();
        $default_role = get_option('ldap_default_role', null, $client_role_id);

        // Set user properties
        $this->setType('new_client');
        $this->set([
            'username' => $username, // This sets $this->username for validation
            'password' => $password,
            'name' => $name,
            'email' => $email,
            'role_id' => $default_role, // Set role_id property
            'address' => $this->extractLdapAttribute($ldap_attributes, ['postalAddress', 'streetAddress']),
            'phone' => $this->extractLdapAttribute($ldap_attributes, ['telephoneNumber', 'mobile']),
            'contact' => null,
            'max_file_size' => get_option('ldap_default_max_file_size', null, '0'),
            'max_disk_quota' => get_option('ldap_default_disk_quota', null, '0'),
            'notify' => 1, // Use 'notify' column instead of 'notify_upload'
            'active' => 1, // LDAP users are auto-approved
            'can_upload_public' => get_option('ldap_default_can_upload_public', null, '0'),
            'account_requested' => 0,
            'account_denied' => 0, // Add this required field
            'type' => ($default_role == $client_role_id) ? 'new_client' : 'new_user',
        ]);

        // Create the user, bypassing permission check for LDAP auto-creation
        $this->is_ldap_creation = true;
        $result = $this->create();

        if (!empty($result['id'])) {
            // Store LDAP metadata
            $this->storeLdapMetadata($result['id'], $ldap_attributes);
            
            // Log the LDAP user creation
            $this->logger->addEntry([
                'action' => 44, // New action for LDAP user creation
                'owner_id' => $result['id'],
                'owner_user' => $username,
                'affected_account_name' => $name,
                'details' => 'User created via LDAP authentication'
            ]);
        }

        return $result;
    }

    /**
     * Extract attribute value from LDAP attributes array
     */
    private function extractLdapAttribute($attributes, $possible_names, $default = null)
    {
        foreach ($possible_names as $name) {
            if (isset($attributes[$name])) {
                $value = $attributes[$name];
                // LDAP attributes are often arrays
                if (is_array($value) && isset($value[0])) {
                    return $value[0];
                } elseif (is_string($value)) {
                    return $value;
                }
            }
        }
        return $default;
    }

    /**
     * Generate username from email address
     */
    private function generateUsernameFromEmail($email)
    {
        $email_parts = explode('@', $email);
        $base_username = $email_parts[0];
        
        // Clean the username
        $username = preg_replace('/[^a-zA-Z0-9._]/', '', $base_username);
        
        // Ensure username meets minimum length requirement (5 characters)
        if (strlen($username) < 5) {
            $username = $username . str_repeat('_', 5 - strlen($username));
        }
        
        // Check if username exists and generate unique one if needed
        if (username_exists($username)) {
            $counter = 1;
            while (username_exists($username . $counter)) {
                $counter++;
            }
            $username = $username . $counter;
        }
        
        return $username;
    }

    /**
     * Store LDAP metadata for user identification
     */
    private function storeLdapMetadata($user_id, $ldap_attributes)
    {
        // Store that this user was created via LDAP
        save_user_meta($user_id, 'auth_method', 'ldap');
        save_user_meta($user_id, 'ldap_dn', $ldap_attributes['dn'] ?? '');
        save_user_meta($user_id, 'ldap_created_date', date('Y-m-d H:i:s'));
        
        // Store additional LDAP attributes that might be useful
        $attributes_to_store = ['department', 'title', 'company', 'manager'];
        foreach ($attributes_to_store as $attr) {
            $value = $this->extractLdapAttribute($ldap_attributes, [$attr]);
            if (!empty($value)) {
                save_user_meta($user_id, 'ldap_' . $attr, $value);
            }
        }
    }

    /**
     * Check if user was created via LDAP
     */
    public function isLdapUser()
    {
        if (empty($this->id)) {
            return false;
        }
        
        $auth_method = get_user_meta($this->id, 'auth_method');
        return ($auth_method === 'ldap');
    }

    /**
     * Sync user data from LDAP on login
     */
    public function syncFromLdap($ldap_attributes)
    {
        if (empty($this->id) || !$this->isLdapUser()) {
            return false;
        }

        // Update user information from LDAP
        $name = $this->extractLdapAttribute($ldap_attributes, ['displayName', 'cn', 'name'], $this->name);
        $address = $this->extractLdapAttribute($ldap_attributes, ['postalAddress', 'streetAddress'], $this->address);
        $phone = $this->extractLdapAttribute($ldap_attributes, ['telephoneNumber', 'mobile'], $this->phone);

        // Update database if values have changed
        $updates = [];
        if ($name !== $this->name) $updates['name'] = $name;
        if ($address !== $this->address) $updates['address'] = $address;
        if ($phone !== $this->phone) $updates['phone'] = $phone;

        if (!empty($updates)) {
            $set_parts = [];
            $params = [':id' => $this->id];
            
            foreach ($updates as $field => $value) {
                $set_parts[] = $field . " = :" . $field;
                $params[':' . $field] = $value;
            }
            
            $sql = "UPDATE " . TABLE_USERS . " SET " . implode(', ', $set_parts) . " WHERE id = :id";
            $statement = $this->dbh->prepare($sql);
            $statement->execute($params);

            // Update object properties
            foreach ($updates as $field => $value) {
                $this->$field = $value;
            }

            return true;
        }

        return false;
    }
}
