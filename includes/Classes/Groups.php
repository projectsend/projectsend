<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * clients groups.
 */

namespace ProjectSend\Classes;

use \ProjectSend\Classes\Validation;
use \PDO;

class Groups
{
    private $dbh;
    private $logger;

    public $id;
    public $name;
    public $description;
    public $public;
    public $public_token;
    public $public_url;
    public $members;
    public $files;
    public $created_by;
    public $created_date;

    private $validation_passed;
    private $validation_errors;

    // Permissions
    private $allowed_actions_roles;

    public function __construct($group_id = null)
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        $this->allowed_actions_roles = ['System Administrator', 'Account Manager'];

        if (!empty($group_id)) {
            $this->get($group_id);
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
     * Set the properties when editing
     */
    public function set($arguments = [])
    {
		$this->name = (!empty($arguments['name'])) ? encode_html($arguments['name']) : null;
        $this->description = (!empty($arguments['description'])) ? encode_html($arguments['description']) : null;
        $this->members = (!empty($arguments['members'])) ? $arguments['members'] : null;
        $this->public = (!empty($arguments['public'])) ? (int)$arguments['public'] : 0;
    }

    /**
     * Get existing user data from the database
     * @return bool
     */
    public function get($id)
    {
        $this->id = $id;

        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_GROUPS . " WHERE id=:id");
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        if ($statement->rowCount() == 0) {
            return false;
        }
    
        while ($row = $statement->fetch() ) {
            $this->name = html_output($row['name']);
            $this->description = htmlentities_allowed($row['description']);
            $this->public = html_output($row['public']);
            $this->public_token = html_output($row['public_token']);
            $this->public_url = BASE_URI.'public.php?id='.$this->id.'&token='.$this->public_token;
            $this->created_by = html_output($row['created_by']);
            $this->created_date = html_output($row['timestamp']);
        }

        /* Get group members IDs */
        $statement = $this->dbh->prepare("SELECT client_id FROM " . TABLE_MEMBERS . " WHERE group_id = :id");
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();
        
        if ( $statement->rowCount() > 0) {
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            while ($member = $statement->fetch() ) {
                $this->members[] = $member['client_id'];
            }
        }

        /* Get files */
        $statement = $this->dbh->prepare("SELECT group_id, file_id FROM " . TABLE_FILES_RELATIONS . " WHERE group_id = :id");
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();

        if ( $statement->rowCount() > 0) {
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            while ($file = $statement->fetch() ) {
                $this->files[] = $file['file_id'];
            }
        }

        return true;
    }

    /**
     * Return the current properties
     */
    public function getProperties()
    {
        $return = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => htmlentities_allowed($this->description),
            'members' => $this->members,
            'files' => $this->files,
            'public' => $this->public,
            'public_token' => $this->public_token,
            'public_url' => $this->public_url,
            'created_by' => $this->created_by,
            'created_date' => $this->created_date,
        ];

        return $return;
    }

    /**
     * Is group public?
     * @return bool
     */
    public function isPublic()
    {
        if ($this->public == 1) {
            return true;
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
        $validation->validate_items([
            $this->name => [
                'required' => ['error' => $json_strings['validation']['no_name']],
            ],
        ]);

        if ($validation->passed()) {
            $this->validation_passed = true;
            return true;
		}
		else {
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

	/**
	 * Create a new group.
	 */
	public function create()
	{
        // Check permissions
        if (!\current_user_can('create_groups')) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to create groups.', 'cftp_admin')
            ];
        }

        if (!$this->validate()) {
            return [
                'status' => 'error',
                'message' => __('Validation errors occurred.', 'cftp_admin')
            ];
        }

        /** Who is creating the client? */
        $this->created_by = CURRENT_USER_USERNAME;

        /** Define the group information */
        $this->public_token = generate_random_string(32);

        $sql_query = $this->dbh->prepare("INSERT INTO " . TABLE_GROUPS . " (name, description, public, public_token, created_by)"
                                                ." VALUES (:name, :description, :public, :token, :admin)");
        $sql_query->bindParam(':name', $this->name);
        $sql_query->bindParam(':description', $this->description);
        $sql_query->bindParam(':public', $this->public, PDO::PARAM_INT);
        $sql_query->bindParam(':admin', $this->created_by);
        $sql_query->bindParam(':token', $this->public_token);
        $sql_query->execute();

        $this->id = $this->dbh->lastInsertId();
        $state['id'] = $this->id;
        $state['public_token'] = $this->public_token;

        /** Create the members records */
        if ( !empty( $this->members ) ) {
            foreach ($this->members as $member) {
                $sql_member = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
                                                        ." VALUES (:admin, :member, :id)");
                $sql_member->bindParam(':admin', $this->created_by);
                $sql_member->bindParam(':member', $member, PDO::PARAM_INT);
                $sql_member->bindParam(':id', $this->id, PDO::PARAM_INT);
                $sql_member->execute();
            }
        }

        if ($sql_query) {
            /** Record the action log */
            $this->logger->addEntry([
                'action' => 23,
                'owner_id' => defined('CURRENT_USER_ID') ? \CURRENT_USER_ID : 1,
                'affected_account' => $this->id,
                'affected_account_name' => $this->name,
            ]);

            return [
                'status' => 'success',
                'id' => $this->id,
                'message' => __('Group created successfully.', 'cftp_admin')
            ];
        }

		return [
            'status' => 'error',
            'message' => __('Failed to create group.', 'cftp_admin')
        ];
	}

    /**
     * Check if a user can edit this group
     * @param string $username Username to check (defaults to current user)
     * @return bool
     */
    public function canUserEdit($username = null)
    {
        if ($username === null) {
            if (defined('CURRENT_USER_USERNAME')) {
                $username = \CURRENT_USER_USERNAME;
            } else {
                return false; // No user logged in
            }
        }

        // User can edit if they have edit_groups permission (can edit all groups)
        if (\current_user_can('edit_groups')) {
            return true;
        }

        // User can edit their own groups if they have create_groups permission
        if (\current_user_can('create_groups') && $this->created_by == $username) {
            return true;
        }

        return false;
    }

	/**
	 * Edit an existing group.
	 */
	public function edit()
	{
        if (empty($this->id)) {
            return [
                'status' => 'error',
                'message' => __('Group ID is required for editing.', 'cftp_admin')
            ];
        }

        // Check permissions
        $current_username = defined('CURRENT_USER_USERNAME') ? \CURRENT_USER_USERNAME : null;
        $can_edit = \current_user_can('edit_groups') ||
                   (\current_user_can('create_groups') && $this->created_by == $current_username);

        if (!$can_edit) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to edit this group.', 'cftp_admin')
            ];
        }

        if (!$this->validate()) {
            return [
                'status' => 'error',
                'message' => __('Validation errors occurred.', 'cftp_admin')
            ];
        }

        /** Who is editing the group? */
        $editing_user = defined('CURRENT_USER_USERNAME') ? \CURRENT_USER_USERNAME : 'system';

		/** SQL query */
		$sql_query = $this->dbh->prepare( "UPDATE " . TABLE_GROUPS . " SET name = :name, description = :description, public = :public WHERE id = :id" );
		$sql_query->bindParam(':name', $this->name);
		$sql_query->bindParam(':description', $this->description);
		$sql_query->bindParam(':public', $this->public, PDO::PARAM_INT);
		$sql_query->bindParam(':id', $this->id, PDO::PARAM_INT);
		$sql_query->execute();

		/** Clean the members table */
		$sql_clean = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS . " WHERE group_id = :id");
		$sql_clean->bindParam(':id', $this->id, PDO::PARAM_INT);
		$sql_clean->execute();
		
		/** Create the members records */
		if (!empty($this->members)) {
			foreach ($this->members as $member) {
				$sql_member = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
														." VALUES (:admin, :member, :id)");
				$sql_member->bindParam(':admin', $editing_user);
				$sql_member->bindParam(':member', $member, PDO::PARAM_INT);
				$sql_member->bindParam(':id', $this->id, PDO::PARAM_INT);
				$sql_member->execute();
			}
		}

		if ($sql_query) {
            /** Record the action log */
            $this->logger->addEntry([
                'action' => 15,
                'owner_id' => defined('CURRENT_USER_ID') ? \CURRENT_USER_ID : 1,
                'affected_account' => $this->id,
                'affected_account_name' => $this->name,
            ]);

            return [
                'status' => 'success',
                'message' => __('Group updated successfully.', 'cftp_admin')
            ];
        }

		return [
            'status' => 'error',
            'message' => __('Failed to update group.', 'cftp_admin')
        ];
	}

	/**
	 * Delete an existing group.
	 * @return array Result with status and message
	 */
	public function delete()
	{
        if (empty($this->id)) {
            return [
                'status' => 'error',
                'message' => __('Group ID is required for deletion.', 'cftp_admin')
            ];
        }

        // Check permissions
        $current_username = defined('CURRENT_USER_USERNAME') ? \CURRENT_USER_USERNAME : null;
        $can_delete = \current_user_can('delete_groups') ||
                     (\current_user_can('create_groups') && $this->created_by == $current_username);

        if (!$can_delete) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to delete this group.', 'cftp_admin')
            ];
        }

        // Delete the group from database
        $statement = $this->dbh->prepare('DELETE FROM ' . TABLE_GROUPS . ' WHERE id=:id');
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();

        /** Record the action log */
        $this->logger->addEntry([
            'action' => 18,
            'owner_id' => defined('CURRENT_USER_ID') ? \CURRENT_USER_ID : 1,
            'affected_account_name' => $this->name,
        ]);

        return [
            'status' => 'success',
            'message' => __('Group deleted successfully.', 'cftp_admin')
        ];
    }
}
