<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * clients groups.
 *
 * @package		ProjectSend
 * @subpackage	Classes
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

    public function __construct(PDO $dbh = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        $this->allowed_actions_roles = [9, 8];
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

        $this->statement = $this->dbh->prepare("SELECT * FROM " . TABLE_GROUPS . " WHERE id=:id");
        $this->statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $this->statement->execute();
        $this->statement->setFetchMode(PDO::FETCH_ASSOC);

        if ($this->statement->rowCount() == 0) {
            return false;
        }
    
        while ($this->row = $this->statement->fetch() ) {
            $this->name = html_output($this->row['name']);
            $this->description = htmlentities_allowed($this->row['description']);
            $this->public = html_output($this->row['public']);
            $this->public_token = html_output($this->row['public_token']);
            $this->public_url = BASE_URI.'public.php?id='.$this->id.'&token='.$this->public_token;
            $this->created_by = html_output($this->row['created_by']);
            $this->created_date = html_output($this->row['timestamp']);
        }

        /* Get group members IDs */
        $this->statement = $this->dbh->prepare("SELECT client_id FROM " . TABLE_MEMBERS . " WHERE group_id = :id");
        $this->statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $this->statement->execute();
        
        if ( $this->statement->rowCount() > 0) {
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
            while ($this->member = $this->statement->fetch() ) {
                $this->members[] = $this->member['client_id'];
            }
        }

        /* Get files */
        $this->statement = $this->dbh->prepare("SELECT group_id, file_id FROM " . TABLE_FILES_RELATIONS . " WHERE group_id = :id");
        $this->statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $this->statement->execute();

        if ( $this->statement->rowCount() > 0) {
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
            while ($this->file = $this->statement->fetch() ) {
                $this->files[] = $this->file['file_id'];
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
		$state = array(
            'query' => 0,
        );
        
        if (!$this->validate()) {
            $state = [];
            return $state;
        }

        /** Who is creating the client? */
        $this->created_by = CURRENT_USER_USERNAME;

        /** Define the group information */
        $this->public_token = generateRandomString(32);

        $this->sql_query = $this->dbh->prepare("INSERT INTO " . TABLE_GROUPS . " (name, description, public, public_token, created_by)"
                                                ." VALUES (:name, :description, :public, :token, :admin)");
        $this->sql_query->bindParam(':name', $this->name);
        $this->sql_query->bindParam(':description', $this->description);
        $this->sql_query->bindParam(':public', $this->public, PDO::PARAM_INT);
        $this->sql_query->bindParam(':admin', $this->created_by);
        $this->sql_query->bindParam(':token', $this->public_token);
        $this->sql_query->execute();

        $this->id = $this->dbh->lastInsertId();
        $state['id'] = $this->id;
        $state['public_token'] = $this->public_token;

        /** Create the members records */
        if ( !empty( $this->members ) ) {
            foreach ($this->members as $this->member) {
                $this->sql_member = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
                                                        ." VALUES (:admin, :member, :id)");
                $this->sql_member->bindParam(':admin', $this->created_by);
                $this->sql_member->bindParam(':member', $this->member, PDO::PARAM_INT);
                $this->sql_member->bindParam(':id', $this->id, PDO::PARAM_INT);
                $this->sql_member->execute();
            }
        }

        if ($this->sql_query) {
            $state['query'] = 1;

            /** Record the action log */
            $this->logger->addEntry([
                'action' => 23,
                'owner_id' => CURRENT_USER_ID,
                'affected_account' => $this->id,
                'affected_account_name' => $this->name,
            ]);
        }
		
		return $state;
	}

	/**
	 * Edit an existing group.
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

        /** Who is creating the client? */
        $this->created_by = CURRENT_USER_USERNAME;

		/** SQL query */
		$this->sql_query = $this->dbh->prepare( "UPDATE " . TABLE_GROUPS . " SET name = :name, description = :description, public = :public WHERE id = :id" );
		$this->sql_query->bindParam(':name', $this->name);
		$this->sql_query->bindParam(':description', $this->description);
		$this->sql_query->bindParam(':public', $this->public, PDO::PARAM_INT);
		$this->sql_query->bindParam(':id', $this->id, PDO::PARAM_INT);
		$this->sql_query->execute();

		/** Clean the members table */
		$this->sql_clean = $this->dbh->prepare("DELETE FROM " . TABLE_MEMBERS . " WHERE group_id = :id");
		$this->sql_clean->bindParam(':id', $this->id, PDO::PARAM_INT);
		$this->sql_clean->execute();
		
		/** Create the members records */
		if (!empty($this->members)) {
			foreach ($this->members as $this->member) {
				$this->sql_member = $this->dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
														." VALUES (:admin, :member, :id)");
				$this->sql_member->bindParam(':admin', $this->created_by);
				$this->sql_member->bindParam(':member', $this->member, PDO::PARAM_INT);
				$this->sql_member->bindParam(':id', $this->id, PDO::PARAM_INT);
				$this->sql_member->execute();
			}
		}

		if ($this->sql_query) {
			$state['query'] = 1;

            /** Record the action log */
            $this->logger->addEntry([
                'action' => 15,
                'owner_id' => CURRENT_USER_ID,
                'affected_account' => $this->id,
                'affected_account_name' => $this->name,
            ]);
        }
		
		return $state;
	}

	/**
	 * Delete an existing group.
	 */
	public function delete()
	{
        if (empty($this->id)) {
            return false;
        }

        /** Do a permissions check */
        if (isset($this->allowed_actions_roles) && current_role_in($this->allowed_actions_roles)) {
            $this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_GROUPS . ' WHERE id=:id');
            $this->sql->bindParam(':id', $this->id, PDO::PARAM_INT);
            $this->sql->execute();
        }
        
        /** Record the action log */
        $this->logger->addEntry([
            'action' => 18,
            'owner_id' => CURRENT_USER_ID,
            'affected_account_name' => $this->name,
        ]);

        return true;
    }
}
