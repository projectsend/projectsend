<?php

/**
 * Class that handles custom field values for users and clients
 */

namespace ProjectSend\Classes;

use \PDO;

class CustomFieldValues
{
    private $dbh;

    public function __construct()
    {
        global $dbh;

        $this->dbh = $dbh;
    }

    /**
     * Get custom field values for a user
     */
    public function getUserValues($user_id, $applies_to = null)
    {
        $where_conditions = [];
        $params = [':user_id' => $user_id];

        if ($applies_to) {
            $where_conditions[] = "(cf.applies_to = :applies_to OR cf.applies_to = 'both')";
            $params[':applies_to'] = $applies_to;
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = ' AND ' . implode(' AND ', $where_conditions);
        }

        $sql = "SELECT cf.*, cfv.field_value
                FROM " . TABLE_CUSTOM_FIELDS . " cf
                LEFT JOIN " . TABLE_CUSTOM_FIELD_VALUES . " cfv ON cf.id = cfv.field_id AND cfv.user_id = :user_id
                WHERE cf.active = 1" . $where_clause . "
                ORDER BY cf.sort_order ASC, cf.field_label ASC";

        $statement = $this->dbh->prepare($sql);

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific field value for a user
     */
    public function getValue($field_id, $user_id)
    {
        $sql = "SELECT field_value FROM " . TABLE_CUSTOM_FIELD_VALUES . "
                WHERE field_id = :field_id AND user_id = :user_id";

        $statement = $this->dbh->prepare($sql);
        $statement->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['field_value'] : null;
    }

    /**
     * Save custom field values for a user
     */
    public function saveUserValues($user_id, $values = [])
    {
        if (empty($values)) {
            return [
                'status' => 'success',
                'message' => __('No values to save.', 'cftp_admin')
            ];
        }

        $errors = [];
        $saved_count = 0;

        foreach ($values as $field_id => $field_value) {
            $result = $this->saveValue($field_id, $user_id, $field_value);

            if ($result['status'] === 'success') {
                $saved_count++;
            } else {
                $errors[] = $result['message'];
            }
        }

        if (!empty($errors)) {
            return [
                'status' => 'error',
                'message' => implode('<br>', $errors)
            ];
        }

        return [
            'status' => 'success',
            'message' => sprintf(__('%d custom field values saved successfully.', 'cftp_admin'), $saved_count)
        ];
    }

    /**
     * Save a single field value for a user
     */
    public function saveValue($field_id, $user_id, $field_value)
    {
        // Get field information to validate
        $field = new CustomFields($field_id);
        if (!$field->fieldExists()) {
            return [
                'status' => 'error',
                'message' => __('Custom field not found.', 'cftp_admin')
            ];
        }

        // Validate required fields
        if ($field->is_required && empty($field_value) && $field_value !== '0') {
            return [
                'status' => 'error',
                'message' => sprintf(__('Field "%s" is required.', 'cftp_admin'), $field->field_label)
            ];
        }

        // Validate select field options
        if ($field->field_type === 'select' && !empty($field_value)) {
            $valid_options = $field->getSelectOptions();
            if (!empty($valid_options) && !in_array($field_value, $valid_options)) {
                return [
                    'status' => 'error',
                    'message' => sprintf(__('Invalid value for field "%s".', 'cftp_admin'), $field->field_label)
                ];
            }
        }

        // Normalize checkbox values
        if ($field->field_type === 'checkbox') {
            $field_value = !empty($field_value) ? '1' : '0';
        }

        // Check if value already exists
        $check_sql = "SELECT id FROM " . TABLE_CUSTOM_FIELD_VALUES . "
                      WHERE field_id = :field_id AND user_id = :user_id";

        $check_statement = $this->dbh->prepare($check_sql);
        $check_statement->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $check_statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $check_statement->execute();

        if ($check_statement->rowCount() > 0) {
            // Update existing value
            $sql = "UPDATE " . TABLE_CUSTOM_FIELD_VALUES . "
                    SET field_value = :field_value, updated_date = NOW()
                    WHERE field_id = :field_id AND user_id = :user_id";
        } else {
            // Insert new value
            $sql = "INSERT INTO " . TABLE_CUSTOM_FIELD_VALUES . " (field_id, user_id, field_value)
                    VALUES (:field_id, :user_id, :field_value)";
        }

        $statement = $this->dbh->prepare($sql);
        $statement->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->bindParam(':field_value', $field_value, PDO::PARAM_STR);

        if ($statement->execute()) {
            return [
                'status' => 'success',
                'message' => __('Field value saved successfully.', 'cftp_admin')
            ];
        }

        return [
            'status' => 'error',
            'message' => __('There was an error saving the field value.', 'cftp_admin')
        ];
    }

    /**
     * Delete all custom field values for a user
     */
    public function deleteUserValues($user_id)
    {
        $sql = "DELETE FROM " . TABLE_CUSTOM_FIELD_VALUES . " WHERE user_id = :user_id";
        $statement = $this->dbh->prepare($sql);
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        return $statement->execute();
    }

    /**
     * Delete a specific field value for a user
     */
    public function deleteValue($field_id, $user_id)
    {
        $sql = "DELETE FROM " . TABLE_CUSTOM_FIELD_VALUES . "
                WHERE field_id = :field_id AND user_id = :user_id";

        $statement = $this->dbh->prepare($sql);
        $statement->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        return $statement->execute();
    }

    /**
     * Get custom field values for display in tables (e.g., client/user lists)
     */
    public function getValuesForDisplay($applies_to, $user_ids = [])
    {
        $where_conditions = ["(cf.applies_to = :applies_to OR cf.applies_to = 'both')", "cf.active = 1"];
        $params = [':applies_to' => $applies_to];

        if (!empty($user_ids)) {
            $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
            $where_conditions[] = "cfv.user_id IN ($placeholders)";
            $params = array_merge($params, $user_ids);
        }

        $sql = "SELECT cf.id as field_id, cf.field_name, cf.field_label, cf.field_type,
                       cfv.user_id, cfv.field_value
                FROM " . TABLE_CUSTOM_FIELDS . " cf
                LEFT JOIN " . TABLE_CUSTOM_FIELD_VALUES . " cfv ON cf.id = cfv.field_id
                WHERE " . implode(' AND ', $where_conditions) . "
                ORDER BY cf.sort_order ASC, cf.field_label ASC";

        $statement = $this->dbh->prepare($sql);

        $param_index = 1;
        foreach ($params as $param) {
            if (is_string($param) && strpos($param, ':') === 0) {
                $statement->bindValue($param, $applies_to);
            } else {
                $statement->bindValue($param_index, $param);
                $param_index++;
            }
        }

        $statement->execute();
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Organize results by user_id and field_name
        $organized = [];
        foreach ($results as $row) {
            if (!isset($organized[$row['user_id']])) {
                $organized[$row['user_id']] = [];
            }
            $organized[$row['user_id']][$row['field_name']] = [
                'label' => $row['field_label'],
                'type' => $row['field_type'],
                'value' => $row['field_value']
            ];
        }

        return $organized;
    }

    /**
     * Validate custom field values before saving
     */
    public function validateValues($values, $applies_to)
    {
        $errors = [];

        // Get all required fields for this applies_to
        $required_fields = CustomFields::getAll([
            'applies_to' => $applies_to,
            'active' => 1
        ]);

        foreach ($required_fields as $field) {
            if ($field['is_required']) {
                $field_value = isset($values[$field['id']]) ? $values[$field['id']] : '';

                if (empty($field_value) && $field_value !== '0') {
                    $errors[] = sprintf(__('Field "%s" is required.', 'cftp_admin'), $field['field_label']);
                }
            }
        }

        return $errors;
    }

    /**
     * Get custom field values formatted for form display
     */
    public function getFormValues($user_id, $applies_to = null)
    {
        $values = $this->getUserValues($user_id, $applies_to);
        $formatted = [];

        foreach ($values as $field) {
            $formatted[$field['id']] = $field['field_value'];
        }

        return $formatted;
    }

    /**
     * Copy custom field values from one user to another
     */
    public function copyUserValues($from_user_id, $to_user_id)
    {
        // Get all values for the source user
        $sql = "SELECT field_id, field_value FROM " . TABLE_CUSTOM_FIELD_VALUES . "
                WHERE user_id = :from_user_id";

        $statement = $this->dbh->prepare($sql);
        $statement->bindParam(':from_user_id', $from_user_id, PDO::PARAM_INT);
        $statement->execute();

        $values = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (empty($values)) {
            return true;
        }

        // Copy each value to the target user
        foreach ($values as $value) {
            $this->saveValue($value['field_id'], $to_user_id, $value['field_value']);
        }

        return true;
    }
}