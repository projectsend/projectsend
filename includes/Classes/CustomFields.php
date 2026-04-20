<?php

/**
 * Class that handles custom fields management for users and clients
 */

namespace ProjectSend\Classes;

use \ProjectSend\Classes\Validation;
use \PDO;

class CustomFields
{
    private $dbh;
    private $logger;

    private $validation_type;
    private $validation_passed;
    private $validation_errors;

    public $exists;

    public $id;
    public $field_name;
    public $field_label;
    public $field_type;
    public $field_options;
    public $default_value;
    public $is_required;
    public $is_visible_to_client;
    public $applies_to;
    public $sort_order;
    public $active;
    public $created_date;

    private $allowed_field_types;
    private $allowed_applies_to;

    public function __construct($field_id = null)
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        $this->allowed_field_types = ['text', 'textarea', 'select', 'checkbox'];
        $this->allowed_applies_to = ['user', 'client', 'both'];

        $this->exists = false;

        // Default values
        $this->is_required = false;
        $this->is_visible_to_client = true;
        $this->applies_to = 'client';
        $this->sort_order = 0;
        $this->active = true;

        if (!empty($field_id)) {
            $this->get($field_id);
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
     */
    public function getId()
    {
        if (!empty($this->id)) {
            return $this->id;
        }

        return false;
    }

    /**
     * Get field data from database
     */
    public function get($field_id)
    {
        $this->id = $field_id;

        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_CUSTOM_FIELDS . " WHERE id = :id");
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();

        if ($statement->rowCount() == 0) {
            return false;
        }

        $this->exists = true;

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        foreach ($row as $field => $value) {
            $this->$field = $value;
        }

        // Convert boolean fields
        $this->is_required = (bool)$this->is_required;
        $this->is_visible_to_client = (bool)$this->is_visible_to_client;
        $this->active = (bool)$this->active;

        return true;
    }

    /**
     * Check if field exists
     */
    public function fieldExists()
    {
        return $this->exists;
    }

    /**
     * Create a new custom field
     */
    public function create()
    {
        if (!current_role_in(['System Administrator']) && !current_user_can('manage_custom_fields')) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to manage custom fields.', 'cftp_admin')
            ];
        }

        $this->validate();

        if ($this->validation_passed !== true) {
            return [
                'status' => 'error',
                'message' => $this->validation_errors
            ];
        }

        // Check if field name already exists
        $statement = $this->dbh->prepare("SELECT COUNT(*) as total FROM " . TABLE_CUSTOM_FIELDS . " WHERE field_name = :field_name");
        $statement->bindParam(':field_name', $this->field_name, PDO::PARAM_STR);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row['total'] > 0) {
            return [
                'status' => 'error',
                'message' => __('A field with this name already exists.', 'cftp_admin')
            ];
        }

        // Get next sort order
        $statement = $this->dbh->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM " . TABLE_CUSTOM_FIELDS . " WHERE applies_to = :applies_to");
        $statement->bindParam(':applies_to', $this->applies_to, PDO::PARAM_STR);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $this->sort_order = $row['next_order'];

        $sql = "INSERT INTO " . TABLE_CUSTOM_FIELDS . " (field_name, field_label, field_type, field_options, default_value, is_required, is_visible_to_client, applies_to, sort_order, active)
                VALUES (:field_name, :field_label, :field_type, :field_options, :default_value, :is_required, :is_visible_to_client, :applies_to, :sort_order, :active)";

        $statement = $this->dbh->prepare($sql);
        $statement->bindParam(':field_name', $this->field_name, PDO::PARAM_STR);
        $statement->bindParam(':field_label', $this->field_label, PDO::PARAM_STR);
        $statement->bindParam(':field_type', $this->field_type, PDO::PARAM_STR);
        $statement->bindParam(':field_options', $this->field_options, PDO::PARAM_STR);
        $statement->bindParam(':default_value', $this->default_value, PDO::PARAM_STR);
        $statement->bindParam(':is_required', $this->is_required, PDO::PARAM_INT);
        $statement->bindParam(':is_visible_to_client', $this->is_visible_to_client, PDO::PARAM_INT);
        $statement->bindParam(':applies_to', $this->applies_to, PDO::PARAM_STR);
        $statement->bindParam(':sort_order', $this->sort_order, PDO::PARAM_INT);
        $statement->bindParam(':active', $this->active, PDO::PARAM_INT);

        if ($statement->execute()) {
            $this->id = $this->dbh->lastInsertId();

            $this->logger->addEntry([
                'action' => 22,
                'owner_id' => CURRENT_USER_ID,
                'affected_account_name' => $this->field_label,
                'details' => ['field_name' => $this->field_name, 'applies_to' => $this->applies_to]
            ]);

            return [
                'status' => 'success',
                'message' => __('Custom field created successfully.', 'cftp_admin')
            ];
        }

        return [
            'status' => 'error',
            'message' => __('There was an error creating the custom field.', 'cftp_admin')
        ];
    }

    /**
     * Update existing custom field
     */
    public function update()
    {
        if (!current_role_in(['System Administrator']) && !current_user_can('manage_custom_fields')) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to manage custom fields.', 'cftp_admin')
            ];
        }

        if (!$this->exists) {
            return [
                'status' => 'error',
                'message' => __('Custom field not found.', 'cftp_admin')
            ];
        }

        $this->validate();

        if ($this->validation_passed !== true) {
            return [
                'status' => 'error',
                'message' => $this->validation_errors
            ];
        }

        // Field name should never be changed after creation to maintain data integrity
        // We don't check for duplicate field_name since we're not updating it
        $sql = "UPDATE " . TABLE_CUSTOM_FIELDS . " SET
                field_label = :field_label,
                field_type = :field_type,
                field_options = :field_options,
                default_value = :default_value,
                is_required = :is_required,
                is_visible_to_client = :is_visible_to_client,
                applies_to = :applies_to,
                active = :active
                WHERE id = :id";

        $statement = $this->dbh->prepare($sql);
        $statement->bindParam(':field_label', $this->field_label, PDO::PARAM_STR);
        $statement->bindParam(':field_type', $this->field_type, PDO::PARAM_STR);
        $statement->bindParam(':field_options', $this->field_options, PDO::PARAM_STR);
        $statement->bindParam(':default_value', $this->default_value, PDO::PARAM_STR);
        $statement->bindParam(':is_required', $this->is_required, PDO::PARAM_INT);
        $statement->bindParam(':is_visible_to_client', $this->is_visible_to_client, PDO::PARAM_INT);
        $statement->bindParam(':applies_to', $this->applies_to, PDO::PARAM_STR);
        $statement->bindParam(':active', $this->active, PDO::PARAM_INT);
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($statement->execute()) {
            $this->logger->addEntry([
                'action' => 23,
                'owner_id' => CURRENT_USER_ID,
                'affected_account_name' => $this->field_label,
                'details' => ['field_name' => $this->field_name, 'applies_to' => $this->applies_to]
            ]);

            return [
                'status' => 'success',
                'message' => __('Custom field updated successfully.', 'cftp_admin')
            ];
        }

        return [
            'status' => 'error',
            'message' => __('There was an error updating the custom field.', 'cftp_admin')
        ];
    }

    /**
     * Delete custom field and all associated values
     */
    public function delete()
    {
        if (!current_role_in(['System Administrator']) && !current_user_can('manage_custom_fields')) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to manage custom fields.', 'cftp_admin')
            ];
        }

        if (!$this->exists) {
            return [
                'status' => 'error',
                'message' => __('Custom field not found.', 'cftp_admin')
            ];
        }

        // Delete field values first (handled by foreign key cascade)
        $statement = $this->dbh->prepare("DELETE FROM " . TABLE_CUSTOM_FIELDS . " WHERE id = :id");
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);

        if ($statement->execute()) {
            $this->logger->addEntry([
                'action' => 24,
                'owner_id' => CURRENT_USER_ID,
                'affected_account_name' => $this->field_label,
                'details' => ['field_name' => $this->field_name, 'applies_to' => $this->applies_to]
            ]);

            return [
                'status' => 'success',
                'message' => __('Custom field deleted successfully.', 'cftp_admin')
            ];
        }

        return [
            'status' => 'error',
            'message' => __('There was an error deleting the custom field.', 'cftp_admin')
        ];
    }

    /**
     * Set field properties
     */
    public function set($arguments = [])
    {
        foreach ($arguments as $field => $value) {
            if (property_exists($this, $field)) {
                $this->$field = $value;
            }
        }
    }

    /**
     * Get field properties as array
     */
    public function getProperties()
    {
        $return = [
            'id' => $this->id,
            'field_name' => $this->field_name,
            'field_label' => $this->field_label,
            'field_type' => $this->field_type,
            'field_options' => $this->field_options,
            'default_value' => $this->default_value,
            'is_required' => $this->is_required,
            'is_visible_to_client' => $this->is_visible_to_client,
            'applies_to' => $this->applies_to,
            'sort_order' => $this->sort_order,
            'active' => $this->active,
            'created_date' => $this->created_date
        ];

        return $return;
    }

    /**
     * Validate field data
     */
    private function validate()
    {
        $this->validation_passed = true;
        $this->validation_errors = [];

        // Field name validation
        if (empty($this->field_name)) {
            $this->validation_errors[] = __('Field name is required.', 'cftp_admin');
            $this->validation_passed = false;
        } elseif (!preg_match('/^[a-z0-9_]+$/', $this->field_name)) {
            $this->validation_errors[] = __('Field name can only contain lowercase letters, numbers, and underscores.', 'cftp_admin');
            $this->validation_passed = false;
        }

        // Field label validation
        if (empty($this->field_label)) {
            $this->validation_errors[] = __('Field label is required.', 'cftp_admin');
            $this->validation_passed = false;
        }

        // Field type validation
        if (!in_array($this->field_type, $this->allowed_field_types)) {
            $this->validation_errors[] = __('Invalid field type.', 'cftp_admin');
            $this->validation_passed = false;
        }

        // Applies to validation
        if (!in_array($this->applies_to, $this->allowed_applies_to)) {
            $this->validation_errors[] = __('Invalid applies to value.', 'cftp_admin');
            $this->validation_passed = false;
        }

        // Field options validation for select fields
        if ($this->field_type === 'select' && empty($this->field_options)) {
            $this->validation_errors[] = __('Options are required for select fields.', 'cftp_admin');
            $this->validation_passed = false;
        }

        // Convert validation errors array to string
        if (!$this->validation_passed) {
            $this->validation_errors = implode('<br>', $this->validation_errors);
        }
    }

    /**
     * Get all custom fields
     */
    public static function getAll($filters = [])
    {
        global $dbh;

        $where_conditions = [];
        $params = [];

        if (!empty($filters['applies_to'])) {
            $where_conditions[] = "(applies_to = :applies_to OR applies_to = 'both')";
            $params[':applies_to'] = $filters['applies_to'];
        }

        if (!empty($filters['active'])) {
            $where_conditions[] = "active = :active";
            $params[':active'] = (int)$filters['active'];
        }

        if (!empty($filters['visible_to_client'])) {
            $where_conditions[] = "is_visible_to_client = :visible_to_client";
            $params[':visible_to_client'] = (int)$filters['visible_to_client'];
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = " WHERE " . implode(' AND ', $where_conditions);
        }

        $sql = "SELECT * FROM " . TABLE_CUSTOM_FIELDS . $where_clause . " ORDER BY sort_order ASC, field_label ASC";
        $statement = $dbh->prepare($sql);

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update field sort order
     */
    public function updateSortOrder($new_order)
    {
        if (!current_role_in(['System Administrator']) && !current_user_can('manage_custom_fields')) {
            return false;
        }

        if (!$this->exists) {
            return false;
        }

        $sql = "UPDATE " . TABLE_CUSTOM_FIELDS . " SET sort_order = :sort_order WHERE id = :id";
        $statement = $this->dbh->prepare($sql);
        $statement->bindParam(':sort_order', $new_order, PDO::PARAM_INT);
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);

        return $statement->execute();
    }

    /**
     * Get select field options as array
     */
    public function getSelectOptions()
    {
        if ($this->field_type !== 'select' || empty($this->field_options)) {
            return [];
        }

        $options = [];
        $lines = explode("\n", trim($this->field_options));

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $options[] = $line;
            }
        }

        return $options;
    }
}