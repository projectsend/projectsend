<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * clients groups.
 */

namespace ProjectSend\Classes;

use \ProjectSend\Classes\Validation;
use \PDO;

class CustomAsset
{
    private $dbh;
    private $logger;

    public $id;
    public $title;
    public $content;
    public $language;
    public $language_formatted;
    public $location;
    public $position;
    public $enabled;
    public $created_date;

    private $validation_passed;
    private $validation_errors;

    // Permissions
    private $allowed_actions_roles;

    public function __construct($asset_id = null)
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        $this->allowed_actions_roles = [9];

        if (!empty($asset_id)) {
            $this->get($asset_id);
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
		$this->title = (!empty($arguments['title'])) ? encode_html($arguments['title']) : null;
        $this->content = (!empty($arguments['content'])) ? $arguments['content'] : null;
        $this->language = (!empty($arguments['language'])) ? $arguments['language'] : null;
        $this->location = (!empty($arguments['location'])) ? $arguments['location'] : null;
        $this->position = (!empty($arguments['position'])) ? $arguments['position'] : null;
        $this->enabled = (!empty($arguments['enabled'])) ? (int)$arguments['enabled'] : 0;
    }

    /**
     * Get existing user data from the database
     * @return bool
     */
    public function get($id)
    {
        $this->id = $id;

        $this->statement = $this->dbh->prepare("SELECT * FROM " . TABLE_CUSTOM_ASSETS . " WHERE id=:id");
        $this->statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $this->statement->execute();
        $this->statement->setFetchMode(PDO::FETCH_ASSOC);

        if ($this->statement->rowCount() == 0) {
            return false;
        }
    
        while ($this->row = $this->statement->fetch() ) {
            $this->title = html_output($this->row['title']);
            $this->content = htmlentities_allowed_code_editor($this->row['content']);
            $this->language = html_output($this->row['language']);
            $this->location = html_output($this->row['location']);
            $this->position = html_output($this->row['position']);
            $this->enabled = html_output($this->row['enabled']);
            $this->created_date = html_output($this->row['timestamp']);
            $this->language_formatted = format_asset_language_name($this->language);
            return true;
        }

        return false;
    }

    /**
     * Return the current properties
     */
    public function getProperties()
    {
        $return = [
            'id' => $this->id,
            'title' => $this->title,
            'content' => htmlentities_allowed_code_editor($this->content),
            'language' => $this->language,
            'language_formatted' =>  format_asset_language_name($this->language),
            'location' => $this->location,
            'position' => $this->position,
            'enabled' => $this->enabled,
            'created_date' => $this->created_date,
        ];

        return $return;
    }

    /**
     * Is asset enabled?
     * @return bool
     */
    public function isEnabled()
    {
        if ($this->enabled == 1) {
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
            $this->title => [
                'required' => ['error' => $json_strings['validation']['no_title']],
            ],
            $this->language => [
                'in_enum' => [
                    'error' => __('Language is not valid', 'cftp_admin'),
                    'valid_values' => array_keys(get_asset_languages()),
                ],
            ],
            $this->location => [
                'in_enum' => [
                    'error' => __('Location is not valid', 'cftp_admin'),
                    'valid_values' => array_keys(get_asset_locations()),
                ],
            ],
            $this->position => [
                'in_enum' => [
                    'error' => __('Position is not valid', 'cftp_admin'),
                    'valid_values' => array_keys(get_asset_positions()),
                ],
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

    public function validationPassed()
    {
        return $this->validation_passed;
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
	 * Create a new asset.
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

        $this->sql_query = $this->dbh->prepare("INSERT INTO " . TABLE_CUSTOM_ASSETS . " (title, content, language, location, position, enabled)"
                                                ." VALUES (:title, :content, :language, :location, :position, :enabled)");
        $this->sql_query->bindParam(':title', $this->title);
        $this->sql_query->bindParam(':content', $this->content);
        $this->sql_query->bindParam(':language', $this->language);
        $this->sql_query->bindParam(':location', $this->location);
        $this->sql_query->bindParam(':position', $this->position);
        $this->sql_query->bindParam(':enabled', $this->enabled, PDO::PARAM_INT);
        $this->sql_query->execute();

        $this->id = $this->dbh->lastInsertId();
        $state['id'] = $this->id;

        if ($this->sql_query) {
            $state['query'] = 1;

            $record = $this->logAction(50);
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

        $state = [];

        if (!$this->validate()) {
            $state = [];
            return $state;
        }

        /** SQL query */
		$this->sql_query = $this->dbh->prepare( "UPDATE " . TABLE_CUSTOM_ASSETS . " SET title = :title, content = :content, location = :location, position = :position, enabled = :enabled WHERE id = :id" );
		$this->sql_query->bindParam(':title', $this->title);
        $this->sql_query->bindParam(':content', $this->content);
        $this->sql_query->bindParam(':location', $this->location);
        $this->sql_query->bindParam(':position', $this->position);
        $this->sql_query->bindParam(':enabled', $this->enabled, PDO::PARAM_INT);
		$this->sql_query->bindParam(':id', $this->id, PDO::PARAM_INT);
		$this->sql_query->execute();

		if ($this->sql_query) {
			$state['query'] = 1;

            $record = $this->logAction(51);
        }
        
		return $state;
	}

    public function enable()
    {
        return $this->setEnabledStatus(1);
    }

    public function disable()
    {
        return $this->setEnabledStatus(0);
    }

    private function setEnabledStatus($change_to)
	{
        $asset = $this->get($this->id);
        if (!$asset) {
            return false;
        }

        switch ($change_to) {
            case 0:
                $log_action_number = 54;
                break;
            case 1:
                $log_action_number = 53;
                break;
            default:
                return false;
                break;
        }

        /** Do a permissions check */
        if (isset($this->allowed_actions_roles) && current_role_in($this->allowed_actions_roles)) {
            $this->sql = $this->dbh->prepare('UPDATE ' . TABLE_CUSTOM_ASSETS . ' SET enabled=:enabled_state WHERE id=:id');
            $this->sql->bindParam(':enabled_state', $change_to, PDO::PARAM_INT);
            $this->sql->bindParam(':id', $this->id, PDO::PARAM_INT);
            $this->sql->execute();

            $record = $this->logAction($log_action_number);
            
            return true;
        }
        
        return false;
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
            $this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_CUSTOM_ASSETS . ' WHERE id=:id');
            $this->sql->bindParam(':id', $this->id, PDO::PARAM_INT);
            $this->sql->execute();
        }
        
        $record = $this->logAction(52);

        return true;
    }

    /** Record the action log */
    private function logAction($number)
    {
        $this->logger->addEntry([
            'action' => $number,
            'owner_id' => CURRENT_USER_ID,
            'details' => json_encode([
                'title' => $this->title,
                'language' => $this->language
            ]),
        ]);
    }
}
