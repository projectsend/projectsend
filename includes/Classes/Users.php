<?php

/**
 * Class that handles all the actions and functions that can be applied to
 * users accounts.
 */

namespace ProjectSend\Classes;

use \ProjectSend\Classes\Validation;
use \ProjectSend\Classes\GroupsMemberships;
use \PDO;

class Users extends Base
{
    private $validation_type;
    private $validation_passed;
    private $validation_errors;

    public $exists;

    public $id;
    public $name;
    public $email;
    public $username;
    public $password;
    public $password_raw;
    public $account_type;
    public $role;
    public $active;
    public $notify_account;
    public $max_file_size;
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
    public $contact_name;
    public $notify_upload;
    public $account_request;
    public $recaptcha;

    // Permissions
    private $allowed_actions_roles;

    public function __construct($user_id = null)
    {
        parent::__construct();

        $this->role = 0; // by default, create "client" role

        $this->allowed_actions_roles = [9];
        $this->exists = false;
        $this->require_password_change = false;

        $this->metadata = [];

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
            Delete users: [9]
            Delete clients: [8, 9]
        */
        switch ($this->role) {
            case 7:
            case 8:
            case 9:
                $this->allowed_actions_roles = [9];
                break;
            case 0;
                $this->allowed_actions_roles = [8, 9];
                break;
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
        $this->role = (!empty($arguments['role'])) ? (int)$arguments['role'] : 0;
        $this->active = (!empty($arguments['active'])) ? (int)$arguments['active'] : 0;
        $this->notify_account = (!empty($arguments['notify_account'])) ? (int)$arguments['notify_account'] : 0;
        $this->max_file_size = (!empty($arguments['max_file_size'])) ? (int)$arguments['max_file_size'] : 0;
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

        $this->statement = $this->dbh->prepare("SELECT * FROM " . get_table('users') . " WHERE id=:id");
        $this->statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $this->statement->execute();
        $this->statement->setFetchMode(PDO::FETCH_ASSOC);

        if ($this->statement->rowCount() == 0) {
            return false;
        }

        $this->exists = true;

        while ($this->row = $this->statement->fetch()) {
            $this->name = html_output($this->row['name']);
            $this->email = html_output($this->row['email']);
            $this->username = html_output($this->row['user']);
            $this->password = html_output($this->row['password']);
            $this->password_raw = $this->row['password'];
            $this->role = html_output($this->row['level']);
            $this->account_type = ($this->role == 0) ? 'client' : 'user';
            $this->active = html_output($this->row['active']);
            $this->max_file_size = html_output($this->row['max_file_size']);
            $this->created_date = html_output($this->row['timestamp']);
            $this->created_by = html_output($this->row['created_by']);

            // See if user requires password change
            if (user_meta_exists($this->id, 'require_password_change')) {
                $meta = (get_user_meta($this->id, 'require_password_change'));
                $this->require_password_change = ($meta['value'] == 'true') ? true : false;
            }

            $this->limit_upload_to = $this->limitUploadToGet();

            // Specific for clients
            $this->address = html_output($this->row['address']);
            $this->phone = html_output($this->row['phone']);
            $this->contact = html_output($this->row['contact']);
            $this->notify_upload = html_output($this->row['notify']);
            $this->can_upload_public = html_output($this->row['can_upload_public']);

            // Files
            $this->statement = $this->dbh->prepare("SELECT DISTINCT id FROM " . get_table('files') . " WHERE uploader = :username");
            $this->statement->bindParam(':username', $this->username);
            $this->statement->execute();

            if ($this->statement->rowCount() > 0) {
                $this->statement->setFetchMode(PDO::FETCH_ASSOC);
                while ($this->file = $this->statement->fetch()) {
                    $this->files[] = $this->file['id'];
                }
            }

            // Groups
            $groups_object = new \ProjectSend\Classes\GroupsMemberships();
            $this->groups = $groups_object->getGroupsByClient([
                'client_id'    => $this->id
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
            'role' => $this->role,
            'active' => $this->active,
            'max_file_size' => $this->max_file_size,
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
        if (!empty($this->role) && $this->role != 0) {
            return false;
        }

        return true;
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
            $this->role => [
                'required' => ['error' => $json_strings['validation']['no_role']],
            ],
            $this->max_file_size => [
                'number' => ['error' => $json_strings['validation']['file_size']],
            ],
        ];

        if ($this->validation_type == 'new_user' || $this->validation_type == 'new_client') {
            $validation_items[$this->email]['email_exists'] = ['error' => $json_strings['validation']['email_exists']];
            $validation_items[$this->username] = [
                'required' => ['error' => $json_strings['validation']['no_user']],
                'user_exists' => ['error' => $json_strings['validation']['user_exists']],
                'alpha_or_dot' => ['error' => $json_strings['validation']['alpha_user']],
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
        $state = array(
            'query' => 0,
        );

        if (!$this->validate()) {
            $state = [];
            return $state;
        }

        $this->password_hashed = $this->hashPassword($this->password);

        if (strlen($this->password_hashed) >= 20) {
            /** Who is creating the client? */
            $this->created_by = (defined('CURRENT_USER_USERNAME')) ? CURRENT_USER_USERNAME : null;

            /** Insert the client information into the database */
            $this->timestamp = time();
            $this->statement = $this->dbh->prepare(
                "INSERT INTO " . get_table('users') . " (
                    name, user, password, level, address, phone, email, notify, contact, created_by, active, account_requested, max_file_size, can_upload_public
                )
                VALUES (
                    :name, :username, :password, :role, :address, :phone, :email, :notify_upload, :contact, :created_by, :active, :request, :max_file_size, :can_upload_public
                )"
            );
            $this->statement->bindParam(':name', $this->name);
            $this->statement->bindParam(':username', $this->username);
            $this->statement->bindParam(':password', $this->password_hashed);
            $this->statement->bindParam(':role', $this->role, PDO::PARAM_INT);
            $this->statement->bindParam(':address', $this->address);
            $this->statement->bindParam(':phone', $this->phone);
            $this->statement->bindParam(':email', $this->email);
            $this->statement->bindParam(':notify_upload', $this->notify_upload, PDO::PARAM_INT);
            $this->statement->bindParam(':contact', $this->contact);
            $this->statement->bindParam(':created_by', $this->created_by);
            $this->statement->bindParam(':active', $this->active, PDO::PARAM_INT);
            $this->statement->bindParam(':request', $this->account_request, PDO::PARAM_INT);
            $this->statement->bindParam(':max_file_size', $this->max_file_size, PDO::PARAM_INT);
            $this->statement->bindParam(':can_upload_public', $this->can_upload_public, PDO::PARAM_INT);
            $this->statement->execute();

            if ($this->statement) {
                $this->id = $this->dbh->lastInsertId();
                $state['id'] = $this->id;
                $state['query'] = 1;

                if ($this->require_password_change == true) {
                    save_user_meta($this->id, 'require_password_change', 'true');
                }

                // Uploader role: limit who user can upload to
                $this->limitUploadToSave($this->limit_upload_to);

                switch ($this->role) {
                    case 0:
                        $email_type = "new_client";
                        break;
                    case 7:
                    case 8:
                    case 9:
                        $email_type = "new_user";
                        break;
                }

                /** Send account data by email */
                $this->notify_user = new \ProjectSend\Classes\Emails;
                if ($this->notify_account == 1) {
                    if ($this->notify_user->send([
                        'type'        => $email_type,
                        'address'    => $this->email,
                        'username'    => $this->username,
                        'password'    => $this->password
                    ])) {
                        $state['email'] = 1;
                    } else {
                        $state['email'] = 0;
                    }
                } else {
                    $state['email'] = 2;
                }
            }
        }

        return $state;
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
     */
    public function edit()
    {
        if (empty($this->id)) {
            return false;
        }

        $state = array(
            'query' => 0,
        );

        if (!$this->validate()) {
            $state = [];
            return $state;
        }

        $previous_data = get_user_by_id($this->id);
        if ($previous_data['active'] != $this->active) {
            $this->setActiveStatus($this->active);
        }

        // Some fields should not be allowed to be written if the current user is not a client,
        // as they are meant to be null for system users
        if ($this->role != 0) {
            $this->address = null;
            $this->phone = null;
            $this->contact = null;
        }

        /** SQL query */
        $this->query = "UPDATE " . get_table('users') . " SET
                                    name = :name,
                                    level = :role,
                                    address = :address,
                                    phone = :phone,
                                    email = :email,
                                    contact = :contact,
                                    notify = :notify_upload,
                                    max_file_size = :max_file_size,
                                    can_upload_public = :can_upload_public
                                    ";

        /** Add the password to the query if it's not the dummy value '' */
        if (!empty($this->password)) {
            $this->query .= ", password = :password";
        }

        $this->query .= " WHERE id = :id";

        $this->statement = $this->dbh->prepare($this->query);
        $this->statement->bindParam(':name', $this->name);
        $this->statement->bindParam(':role', $this->role, PDO::PARAM_INT);
        $this->statement->bindParam(':address', $this->address);
        $this->statement->bindParam(':phone', $this->phone);
        $this->statement->bindParam(':email', $this->email);
        $this->statement->bindParam(':contact', $this->contact);
        $this->statement->bindParam(':notify_upload', $this->notify_upload, PDO::PARAM_INT);
        $this->statement->bindParam(':max_file_size', $this->max_file_size, PDO::PARAM_INT);
        $this->statement->bindParam(':can_upload_public', $this->can_upload_public, PDO::PARAM_INT);
        $this->statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        if (!empty($this->password)) {
            $this->password_hashed = $this->hashPassword($this->password);
            $this->statement->bindParam(':password', $this->password_hashed);
        }

        $this->statement->execute();

        if ($this->statement) {
            // See if user requires password change
            if (user_meta_exists($this->id, 'require_password_change')) {
                if (!empty($this->password)) {
                    delete_user_meta($this->id, 'require_password_change');
                }
            }

            $this->limitUploadToSave($this->limit_upload_to);

            $state['query'] = 1;

            switch ($this->role) {
                case 0:
                    $log_action_number = 14;
                    break;
                case 7:
                case 8:
                case 9:
                    $log_action_number = 13;
                    break;
            }

            /** Record the action log */
            $this->logger->addEntry([
                'action' => $log_action_number,
                'owner_id' => CURRENT_USER_ID,
                'affected_account' => $this->id,
                'affected_account_name' => $this->username,
                'username_column' => true
            ]);
        }

        return $state;
    }

    /**
     * Delete an existing user.
     */
    public function delete()
    {
        if ($this->id == CURRENT_USER_ID) {
            return false;
        }

        if (isset($this->id)) {
            /** Do a permissions check */
            if (isset($this->allowed_actions_roles) && current_role_in($this->allowed_actions_roles)) {
                $this->sql = $this->dbh->prepare('DELETE FROM ' . get_table('users') . ' WHERE id=:id');
                $this->sql->bindParam(':id', $this->id, PDO::PARAM_INT);
                $this->sql->execute();

                switch ($this->role) {
                    case 0:
                        $log_action_number = 17;
                        break;
                    case 7:
                    case 8:
                    case 9:
                        $log_action_number = 16;
                        break;
                }

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
                $log_action_number = ($this->role == 0) ? 20 : 28;
                break;
            case 1:
                $log_action_number = ($this->role == 0) ? 19 : 27;
                break;
            default:
                return false;
                break;
        }

        if (isset($this->id)) {
            /** Do a permissions check */
            if (isset($this->allowed_actions_roles) && current_role_in($this->allowed_actions_roles)) {
                $this->sql = $this->dbh->prepare('UPDATE ' . get_table('users') . ' SET active=:active_state WHERE id=:id');
                $this->sql->bindParam(':active_state', $change_to, PDO::PARAM_INT);
                $this->sql->bindParam(':id', $this->id, PDO::PARAM_INT);
                $this->sql->execute();

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
                $this->sql = $this->dbh->prepare('UPDATE ' . get_table('users') . ' SET active=:active, account_requested=:requested, account_denied=:denied WHERE id=:id');
                $this->sql->bindValue(':active', 1, PDO::PARAM_INT);
                $this->sql->bindValue(':requested', 0, PDO::PARAM_INT);
                $this->sql->bindValue(':denied', 0, PDO::PARAM_INT);
                $this->sql->bindValue(':id', $this->id, PDO::PARAM_INT);
                $this->status = $this->sql->execute();

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
                $this->sql = $this->dbh->prepare('UPDATE ' . get_table('users') . ' SET active=:active, account_requested=:account_requested, account_denied=:account_denied WHERE id=:id');
                $this->sql->bindValue(':active', 0, PDO::PARAM_INT);
                $this->sql->bindValue(':account_requested', 1, PDO::PARAM_INT);
                $this->sql->bindValue(':account_denied', 1, PDO::PARAM_INT);
                $this->sql->bindValue(':id', $this->id, PDO::PARAM_INT);
                $this->status = $this->sql->execute();

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
        if (!table_exists(get_table('user_limit_upload_to'))) {
            return $clients_ids;
        }

        $statement = $this->dbh->prepare("SELECT * FROM " . get_table('user_limit_upload_to') . " WHERE user_id = :user_id");
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
        if (CURRENT_USER_LEVEL == 7) {
            return;
        }

        if (CURRENT_USER_ID == $this->id) {
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
            $statement = $this->dbh->prepare("DELETE FROM " . get_table('user_limit_upload_to') . " WHERE user_id = :user_id AND FIND_IN_SET(client_id, :delete)");
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
            $statement = $this->dbh->prepare("INSERT INTO " . get_table('user_limit_upload_to') . " (user_id, client_id) VALUES (:user_id, :client_id)");
            $statement->bindParam(':user_id', $this->id);
            $statement->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $statement->execute();
        }

        // Get again to refresh the properties
        $this->limitUploadToGet();
    }

    public function shouldLimitUploadTo()
    {
        if (!$this->isClient() && $this->role == '7') {
            return true;
        }
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
            $statement = $this->dbh->prepare("UPDATE " . get_table('users') . " SET password = :password WHERE id = :id");
            $statement->bindParam(':password', $hashed);
            $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
            if ($statement->execute()) {
                return true;
            }
        }

        return false;
    }
}
