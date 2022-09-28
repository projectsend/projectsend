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

    private $validation_passed;
    private $validation_errors;

    // Permissions
    private $allowed_actions_roles;

    public function __construct($category_id = null)
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        $this->allowed_actions_roles = [9, 8, 7];

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

        $this->statement = $this->dbh->prepare("SELECT * FROM " . TABLE_CATEGORIES . " WHERE id=:id");
        $this->statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $this->statement->execute();
        $this->statement->setFetchMode(PDO::FETCH_ASSOC);

        if ($this->statement->rowCount() == 0) {
            return false;
        }

        while ($this->row = $this->statement->fetch() ) {
            $this->name = html_output($this->row['name']);
            $this->parent = html_output($this->row['parent']);
            $this->description = html_output($this->row['description']);
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
	 * Save or create, according the the ACTION parameter
	 */
	function create()
	{
        if (!$this->validate()) {
            return [
                'status' => 'error',
                'message' => __('Errors ocurred during validation.'),
            ];
        }

        /** Who is creating the category? */
        $this->created_by = CURRENT_USER_USERNAME;

        /** Insert the category information into the database */
        $this->statement = $this->dbh->prepare("INSERT INTO " . TABLE_CATEGORIES . " (name,parent,description,created_by)"
                                            ."VALUES (:name, :parent, :description, :created_by)");
        $this->statement->bindParam(':name', $this->name);

        if (empty($this->parent)) {
            $this->parent = 0;
            $this->statement->bindValue(':parent', $this->parent, PDO::PARAM_NULL);
        }
        else {
            $this->statement->bindValue(':parent', $this->parent, PDO::PARAM_INT);
        }

        $this->statement->bindParam(':description', $this->description);
        $this->statement->bindParam(':created_by', $this->created_by);

        $this->statement->execute();

        if ($this->statement) {
            $this->id = $this->dbh->lastInsertId();

            /** Record the action log */
            $this->logger->addEntry([
                'action'				=> 34,
                'owner_id'				=> CURRENT_USER_ID,
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
	 * Edit an existing user.
	 */
    public function edit()
    {
        if (empty($this->id)) {
            return [
                'status' => 'error',
                'message' => __('Category id not set.'),
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

        $this->edit_category_query = "UPDATE " . TABLE_CATEGORIES . " SET
                                    name = :name,
                                    ".$query_update_parent."
                                    description = :description
                                    WHERE id = :id
                                    ";

        $this->statement = $this->dbh->prepare( $this->edit_category_query );
        $this->statement->bindParam(':name', $this->name);
        if ( $this->parent == '0' ) {
            $this->parent == null;
            $this->statement->bindValue(':parent', $this->parent, PDO::PARAM_NULL);
        }
        else
          if($query_update_parent!=""){
            $this->statement->bindValue(':parent', $this->parent, PDO::PARAM_INT);
          }
        $this->statement->bindParam(':description', $this->description);
        $this->statement->bindParam(':id', $this->id, PDO::PARAM_INT);

        $this->statement->execute();

        if ($this->statement) {
            // Record the action log
            $this->logger->addEntry([
                'action'				=> 35,
                'owner_id'				=> CURRENT_USER_ID,
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
	 */
	function delete() {
        if (empty($this->id)) {
            return false;
        }

        /** Do a permissions check */
        if (isset($this->allowed_actions_roles) && current_role_in($this->allowed_actions_roles)) {
            $this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_CATEGORIES . ' WHERE id=:id');
            $this->sql->bindParam(':id', $this->id, PDO::PARAM_INT);
            $this->sql->execute();

            /** Record the action log */
            $this->logger->addEntry([
                'action' => 36,
                'owner_id' => CURRENT_USER_ID,
                'affected_account_name' => $this->name,
            ]);
        }

        return true;
	}

}
