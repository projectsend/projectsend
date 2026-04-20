<?php
/**
 * Custom fields helper functions
 */

/**
 * Render custom fields for forms
 */
function render_custom_fields($applies_to, $user_id = null, $form_type = 'full')
{
    $custom_fields_values = new \ProjectSend\Classes\CustomFieldValues();

    // Get fields based on applies_to and visibility settings
    $filters = [
        'applies_to' => $applies_to,
        'active' => 1
    ];

    // For self-registration or self-edit, only show visible fields
    if ($form_type === 'self' || $form_type === 'registration') {
        $filters['visible_to_client'] = 1;
    }

    $custom_fields = \ProjectSend\Classes\CustomFields::getAll($filters);

    if (empty($custom_fields)) {
        return '';
    }

    // Get existing values if user_id is provided
    $existing_values = [];
    if (!empty($user_id)) {
        $existing_values = $custom_fields_values->getFormValues($user_id, $applies_to);
    }

    $output = '';

    // Only show the section header if we have fields to show
    $output .= '<div class="custom-fields-section">';
    $output .= '<h5 class="mb-3">' . __('Additional Information', 'cftp_admin') . '</h5>';

    foreach ($custom_fields as $field) {
        // Skip hidden fields for self forms
        if (($form_type === 'self' || $form_type === 'registration') && !$field['is_visible_to_client']) {
            continue;
        }

        $field_value = isset($existing_values[$field['id']]) ? $existing_values[$field['id']] : $field['default_value'];
        $field_name = 'custom_field_' . $field['id'];
        $required_attr = $field['is_required'] ? 'required' : '';
        $required_label = $field['is_required'] ? ' <span class="required-indicator" aria-label="required">*</span>' : '';

        $output .= '<div class="form-group row">';
        $output .= '<label for="' . $field_name . '" class="col-sm-4 control-label">' . html_output($field['field_label']) . $required_label . '</label>';
        $output .= '<div class="col-sm-8">';

        switch ($field['field_type']) {
            case 'text':
                $output .= '<input type="text" name="' . $field_name . '" id="' . $field_name . '" class="form-control" value="' . html_output($field_value) . '" ' . $required_attr . ' />';
                break;

            case 'textarea':
                $output .= '<textarea name="' . $field_name . '" id="' . $field_name . '" class="form-control" rows="3" ' . $required_attr . '>' . html_output($field_value) . '</textarea>';
                break;

            case 'select':
                $options = explode("\n", trim($field['field_options']));
                $output .= '<select name="' . $field_name . '" id="' . $field_name . '" class="form-select" ' . $required_attr . '>';

                if (!$field['is_required']) {
                    $output .= '<option value="">' . __('Select an option...', 'cftp_admin') . '</option>';
                }

                foreach ($options as $option) {
                    $option = trim($option);
                    if (!empty($option)) {
                        $selected = ($field_value == $option) ? 'selected' : '';
                        $output .= '<option value="' . html_output($option) . '" ' . $selected . '>' . html_output($option) . '</option>';
                    }
                }
                $output .= '</select>';
                break;

            case 'checkbox':
                $checked = (!empty($field_value) && $field_value != '0') ? 'checked' : '';
                // Use custom label from field_options if available, otherwise default to "Yes"
                $checkbox_label = !empty($field['field_options']) ? trim($field['field_options']) : __('Yes', 'cftp_admin');
                $output .= '<div class="form-check">';
                $output .= '<input type="checkbox" name="' . $field_name . '" id="' . $field_name . '" class="form-check-input" value="1" ' . $checked . ' />';
                $output .= '<label class="form-check-label" for="' . $field_name . '">' . html_output($checkbox_label) . '</label>';
                $output .= '</div>';
                break;
        }

        $output .= '</div>';
        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
}

/**
 * Process custom fields form data
 */
function process_custom_fields($applies_to, $user_id, $post_data)
{
    $custom_fields_values = new \ProjectSend\Classes\CustomFieldValues();
    $values_to_save = [];

    // Extract custom field values from POST data
    foreach ($post_data as $key => $value) {
        if (strpos($key, 'custom_field_') === 0) {
            $field_id = str_replace('custom_field_', '', $key);
            $values_to_save[$field_id] = $value;
        }
    }

    if (!empty($values_to_save)) {
        return $custom_fields_values->saveUserValues($user_id, $values_to_save);
    }

    return ['status' => 'success', 'message' => ''];
}

/**
 * Get custom fields for display in tables
 */
function get_custom_fields_for_table($applies_to, $user_ids = [])
{
    $custom_fields_values = new \ProjectSend\Classes\CustomFieldValues();
    return $custom_fields_values->getValuesForDisplay($applies_to, $user_ids);
}

/**
 * Validate custom fields
 */
function validate_custom_fields($applies_to, $post_data)
{
    $custom_fields_values = new \ProjectSend\Classes\CustomFieldValues();
    $values_to_validate = [];

    // Extract custom field values from POST data
    foreach ($post_data as $key => $value) {
        if (strpos($key, 'custom_field_') === 0) {
            $field_id = str_replace('custom_field_', '', $key);
            $values_to_validate[$field_id] = $value;
        }
    }

    return $custom_fields_values->validateValues($values_to_validate, $applies_to);
}