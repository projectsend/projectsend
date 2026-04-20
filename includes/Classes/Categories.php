<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * files categories.
 */

namespace ProjectSend\Classes;
use \PDO;

class Categories
{
    private $dbh;
    private $logger;

    private $id;
    private $name;
    private $parent;
    private $description;
    private $created_date;
    public $created_by;

    private $validation_errors;

    public function __construct($category_id = null)
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;


        if (!empty($category_id)) {
            $this->get($category_id);
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
        $this->id = (!empty($arguments['id'])) ? encode_html($arguments['id']) : null;
        $this->name = (!empty($arguments['name'])) ? encode_html($arguments['name']) : null;
        $this->parent = (!empty($arguments['parent'])) ? (int)$arguments['parent'] : null;
        $this->description = (!empty($arguments['description'])) ? encode_html($arguments['description']) : null;
    }

    /**
    * Get existing user data from the database
    * @return bool
    */
    public function get($id)
    {
        $this->id = $id;

        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_CATEGORIES . " WHERE id=:id");
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        if ($statement->rowCount() == 0) {
            return false;
        }

        while ($row = $statement->fetch() ) {
            $this->name = html_output($row['name']);
            $this->parent = html_output($row['parent']);
            $this->description = html_output($row['description']);
            $this->created_by = $row['created_by'];
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
            'parent' => $this->parent,
            'description' => $this->description,
        ];

        return $return;
    }

	/**
	 * Validate the information from the form.
	 */
	function validate()
	{
		global $json_strings;

		$validation = new \ProjectSend\Classes\Validation;
        $validation->validate_items([
            $this->name => [
                'required' => ['error' => $json_strings['validation']['no_name']],
            ],
        ]);

        if ($validation->passed()) {
            return true;
		}
		else {
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
	 * Save or create, according the the ACTION parameter
	 */
	function create()
	{
        // Check permissions
        if (!\current_user_can('create_categories')) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to create categories.', 'cftp_admin')
            ];
        }

        if (!$this->validate()) {
            return [
                'status' => 'error',
                'message' => __('Errors ocurred during validation.'),
            ];
        }

        /** Who is creating the category? */
        $this->created_by = defined('CURRENT_USER_USERNAME') ? \CURRENT_USER_USERNAME : 'system';

        /** Insert the category information into the database */
        $statement = $this->dbh->prepare("INSERT INTO " . TABLE_CATEGORIES . " (name,parent,description,created_by)"
                                            ."VALUES (:name, :parent, :description, :created_by)");
        $statement->bindParam(':name', $this->name);

        if (empty($this->parent)) {
            $this->parent = 0;
            $statement->bindValue(':parent', $this->parent, PDO::PARAM_NULL);
        }
        else {
            $statement->bindValue(':parent', $this->parent, PDO::PARAM_INT);
        }

        $statement->bindParam(':description', $this->description);
        $statement->bindParam(':created_by', $this->created_by);

        $statement->execute();

        if ($statement) {
            $this->id = $this->dbh->lastInsertId();

            /** Record the action log */
            $this->logger->addEntry([
                'action'				=> 34,
                'owner_id'				=> defined('CURRENT_USER_ID') ? \CURRENT_USER_ID : 1,
                'affected_account'		=> $this->id,
                'affected_account_name'	=> $this->name
            ]);

            return [
                'status' => 'success',
                'id' => $this->id,
            ];
        }

        return [
            'status' => 'error',
            'message' => null,
        ];
    }

    private function checkParentValidation()
    {
      if($this->id == $this->parent)
        return false;
      else{
          //Check if the parent is not a child of the current category id
          $category_parent_query = "select id, parent from ".TABLE_CATEGORIES;
          $category_parent_query_statment = $this->dbh->prepare($category_parent_query);
          $category_parent_query_statment->execute();

          $array_category_parent = $category_parent_query_statment->fetchAll(PDO::FETCH_KEY_PAIR);

          $point = $this->parent;
          while($array_category_parent[$point]!=null){
            if($array_category_parent[$point]==$this->id)
              return false;
            $point = $point->parent;
          }

      }
      return true;
    }

    /**
     * Check if a user can edit this category
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

        // User can edit if they have edit_categories permission (can edit all categories)
        if (\current_user_can('edit_categories')) {
            return true;
        }

        // User can edit their own categories if they have create_categories permission
        if (\current_user_can('create_categories') && $this->created_by == $username) {
            return true;
        }

        return false;
    }

	/**
	 * Edit an existing category.
	 */
    public function edit()
    {
        if (empty($this->id)) {
            return [
                'status' => 'error',
                'message' => __('Category id not set.'),
            ];
        }

        // Check permissions
        $current_username = defined('CURRENT_USER_USERNAME') ? \CURRENT_USER_USERNAME : null;
        $can_edit = \current_user_can('edit_categories') ||
                   (\current_user_can('create_categories') && $this->created_by == $current_username);

        if (!$can_edit) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to edit this category.', 'cftp_admin')
            ];
        }

        if (!$this->validate()) {
            return [
                'status' => 'error',
                'message' => __('Errors ocurred during validation.'),
            ];
        }

        $query_update_parent = "";
        if($this->parent == '0' || $this->checkParentValidation() )
          $query_update_parent = "parent = :parent,";

        $edit_category_query = "UPDATE " . TABLE_CATEGORIES . " SET
                                    name = :name,
                                    ".$query_update_parent."
                                    description = :description
                                    WHERE id = :id
                                    ";

        $statement = $this->dbh->prepare( $edit_category_query );
        $statement->bindParam(':name', $this->name);
        if ( $this->parent == '0' ) {
            $this->parent == null;
            $statement->bindValue(':parent', $this->parent, PDO::PARAM_NULL);
        }
        else
          if($query_update_parent!=""){
            $statement->bindValue(':parent', $this->parent, PDO::PARAM_INT);
          }
        $statement->bindParam(':description', $this->description);
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);

        $statement->execute();

        if ($statement) {
            // Record the action log
            $this->logger->addEntry([
                'action'				=> 35,
                'owner_id'				=> defined('CURRENT_USER_ID') ? \CURRENT_USER_ID : 1,
                'affected_account'		=> $this->id,
                'affected_account_name'	=> $this->name
            ]);

            return [
                'status' => 'success',
                'id' => $this->id,
            ];
        }

        return [
            'status' => 'error',
            'message' => null,
        ];
	}

	/**
	 * Delete an existing category.
	 * @return array Result with status and message
	 */
	public function delete() {
        if (empty($this->id)) {
            return [
                'status' => 'error',
                'message' => __('Category ID is required for deletion.', 'cftp_admin')
            ];
        }

        // Check permissions
        $current_username = defined('CURRENT_USER_USERNAME') ? \CURRENT_USER_USERNAME : null;
        $can_delete = \current_user_can('delete_categories') ||
                     (\current_user_can('create_categories') && $this->created_by == $current_username);

        if (!$can_delete) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to delete this category.', 'cftp_admin')
            ];
        }

        // Delete the category from database
        $sql = $this->dbh->prepare('DELETE FROM ' . TABLE_CATEGORIES . ' WHERE id=:id');
        $sql->bindParam(':id', $this->id, PDO::PARAM_INT);
        $sql->execute();

        /** Record the action log */
        $this->logger->addEntry([
            'action' => 36,
            'owner_id' => defined('CURRENT_USER_ID') ? \CURRENT_USER_ID : 1,
            'affected_account_name' => $this->name,
        ]);

        return [
            'status' => 'success',
            'message' => __('Category deleted successfully.', 'cftp_admin')
        ];
	}

}
