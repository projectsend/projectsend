<?php
/**
 * Form generation functions for options pages
 * Converts array configurations into HTML forms
 */

/**
 * Sanitize a title for use as an HTML ID
 *
 * @param string $title The title to sanitize
 * @return string Sanitized title suitable for use as HTML ID
 */
function sanitize_title($title) {
    // Convert to lowercase
    $sanitized = strtolower($title);
    // Remove HTML tags
    $sanitized = strip_tags($sanitized);
    // Replace spaces and special chars with hyphens
    $sanitized = preg_replace('/[^a-z0-9]+/', '-', $sanitized);
    // Remove leading/trailing hyphens
    $sanitized = trim($sanitized, '-');
    return $sanitized;
}

/**
 * Renders navigation pills for option sections
 *
 * @param array $sections Array of section configurations
 * @return void Outputs HTML directly
 */
function render_options_section_navigation($sections) {
    // Generate navigation pills if there are sections with titles
    $sections_with_titles = array_filter($sections, function($section) {
        return !empty($section['title']);
    });

    if (count($sections_with_titles) > 0) {
        echo '<nav class="options-section-nav mb-3">';
        echo '<ul class="nav nav-pills">';

        foreach ($sections as $index => $section) {
            if (!empty($section['title'])) {
                $section_id = 'section-' . sanitize_title($section['title']);
                echo '<li class="nav-item">';
                echo '<a class="nav-link" href="#' . $section_id . '">' . $section['title'] . '</a>';
                echo '</li>';
            }
        }

        echo '</ul>';
        echo '</nav>';
    }
}

/**
 * Renders a complete options form section from configuration array
 *
 * @param array $sections Array of section configurations
 * @param bool $render_nav Whether to render navigation inline (default true for backward compatibility)
 * @return void Outputs HTML directly
 */
function render_options_form_sections($sections, $render_nav = true) {
    // Render navigation pills first if requested (for backward compatibility)
    if ($render_nav) {
        render_options_section_navigation($sections);
    }

    // Render sections
    foreach ($sections as $section) {
        // Section header
        if (!empty($section['title'])) {
            $section_id = 'section-' . sanitize_title($section['title']);
            echo '<h3 id="' . $section_id . '">' . $section['title'] . '</h3>';
        }

        if (!empty($section['description'])) {
            echo '<p>' . $section['description'] . '</p>';
        }

        // Custom HTML before fields
        if (!empty($section['html_before'])) {
            echo $section['html_before'];
        }

        // Render fields
        if (!empty($section['fields'])) {
            foreach ($section['fields'] as $field) {
                render_option_field($field);
            }
        }

        // Custom HTML after fields
        if (!empty($section['html_after'])) {
            echo $section['html_after'];
        }

        // Add divider unless explicitly disabled
        if (!isset($section['divider']) || $section['divider'] !== false) {
            echo '<div class="options_divide"></div>';
        }
    }
}

/**
 * Renders a single form field based on configuration
 *
 * @param array $field Field configuration array
 * @return void Outputs HTML directly
 */
function render_option_field($field) {
    // Skip if field is not properly configured
    if (empty($field['type']) || empty($field['name'])) {
        return;
    }

    $field_name = $field['name'];
    $field_id = $field['id'] ?? $field_name;
    $field_value = isset($field['value']) ? $field['value'] : get_option($field_name);

    // Apply default value if field value is null/empty and default is specified
    if (($field_value === null || $field_value === '') && isset($field['default'])) {
        $field_value = $field['default'];
    }
    $field_label = $field['label'] ?? '';
    $field_description = $field['description'] ?? '';
    $field_note = $field['note'] ?? '';
    $field_required = isset($field['required']) && $field['required'] ? 'required' : '';
    $field_class = $field['class'] ?? 'form-control';
    $wrapper_class = $field['wrapper_class'] ?? 'form-group row';
    $label_class = $field['label_class'] ?? 'col-sm-4 control-label';
    $input_wrapper_class = $field['input_wrapper_class'] ?? 'col-sm-8';

    // Custom HTML wrapper
    if (!empty($field['html'])) {
        echo $field['html'];
        return;
    }

    // Start wrapper
    echo '<div class="' . $wrapper_class . '">';

    switch ($field['type']) {
        case 'text':
        case 'email':
        case 'number':
        case 'url':
            if ($field_label) {
                echo '<label for="' . $field_id . '" class="' . $label_class . '">' . $field_label . '</label>';
            }
            echo '<div class="' . $input_wrapper_class . '">';
            echo '<input type="' . $field['type'] . '" name="' . $field_name . '" id="' . $field_id . '" class="' . $field_class . '" value="' . html_output($field_value) . '" ' . $field_required;

            // Add additional attributes
            if (!empty($field['min'])) echo ' min="' . $field['min'] . '"';
            if (!empty($field['max'])) echo ' max="' . $field['max'] . '"';
            if (!empty($field['step'])) echo ' step="' . $field['step'] . '"';
            if (!empty($field['placeholder'])) echo ' placeholder="' . html_output($field['placeholder']) . '"';
            if (!empty($field['pattern'])) echo ' pattern="' . $field['pattern'] . '"';
            if (!empty($field['disabled'])) echo ' disabled';

            echo ' />';

            if ($field_note) {
                echo '<p class="field_note form-text">' . $field_note . '</p>';
            }
            echo '</div>';
            break;

        case 'textarea':
            if ($field_label) {
                echo '<label for="' . $field_id . '" class="' . $label_class . '">' . $field_label . '</label>';
            }
            echo '<div class="' . $input_wrapper_class . '">';
            $rows = $field['rows'] ?? 3;
            echo '<textarea name="' . $field_name . '" id="' . $field_id . '" class="' . $field_class . '" rows="' . $rows . '" ' . $field_required . '>';
            echo html_output($field_value);
            echo '</textarea>';

            if ($field_note) {
                echo '<p class="field_note form-text">' . $field_note . '</p>';
            }
            echo '</div>';
            break;

        case 'select':
            if ($field_label) {
                echo '<label for="' . $field_id . '" class="' . $label_class . '">' . $field_label . '</label>';
            }
            echo '<div class="' . $input_wrapper_class . '">';
            echo '<select name="' . $field_name . '" id="' . $field_id . '" class="form-select" ' . $field_required . '>';

            if (!empty($field['options'])) {
                foreach ($field['options'] as $option_value => $option_label) {
                    if (is_array($option_label)) {
                        // Optgroup: key is group label, value is array of options
                        echo '<optgroup label="' . html_output((string)$option_value) . '">';
                        foreach ($option_label as $group_value => $group_label) {
                            $selected = ((string)$field_value === (string)$group_value) ? 'selected="selected"' : '';
                            echo '<option value="' . html_output((string)$group_value) . '" ' . $selected . '>' . html_output($group_label) . '</option>';
                        }
                        echo '</optgroup>';
                    } else {
                        $selected = ((string)$field_value === (string)$option_value) ? 'selected="selected"' : '';
                        echo '<option value="' . html_output((string)$option_value) . '" ' . $selected . '>' . html_output($option_label) . '</option>';
                    }
                }
            }

            echo '</select>';

            if ($field_note) {
                echo '<p class="field_note form-text">' . $field_note . '</p>';
            }
            echo '</div>';
            break;

        case 'checkbox':
            echo '<div class="' . $input_wrapper_class . ' offset-sm-4">';
            echo '<label for="' . $field_id . '">';

            $checked = ($field_value == 1) ? 'checked="checked"' : '';
            $disabled = (!empty($field['disabled'])) ? 'disabled="disabled"' : '';
            echo '<input type="checkbox" value="1" name="' . $field_name . '" id="' . $field_id . '" class="checkbox_options" ' . $checked . ' ' . $disabled . ' /> ';
            echo $field_label;
            echo '</label>';

            if ($field_note) {
                echo '<p class="field_note form-text">' . $field_note . '</p>';
            }
            echo '</div>';
            break;

        case 'radio':
            if ($field_label) {
                echo '<label class="' . $label_class . '">' . $field_label . '</label>';
            }
            echo '<div class="' . $input_wrapper_class . '">';

            if (!empty($field['options'])) {
                foreach ($field['options'] as $option_value => $option_label) {
                    $checked = ($field_value == $option_value) ? 'checked="checked"' : '';
                    echo '<div class="form-check">';
                    echo '<input type="radio" name="' . $field_name . '" id="' . $field_id . '_' . $option_value . '" value="' . html_output($option_value) . '" class="form-check-input" ' . $checked . ' />';
                    echo '<label for="' . $field_id . '_' . $option_value . '" class="form-check-label">' . html_output($option_label) . '</label>';
                    echo '</div>';
                }
            }

            if ($field_note) {
                echo '<p class="field_note form-text">' . $field_note . '</p>';
            }
            echo '</div>';
            break;

        case 'file':
            if ($field_label) {
                echo '<label for="' . $field_id . '" class="' . $label_class . '">' . $field_label . '</label>';
            }
            echo '<div class="' . $input_wrapper_class . '">';

            $accept = $field['accept'] ?? '';
            echo '<input type="file" name="' . $field_name . '" id="' . $field_id . '" class="' . $field_class . '"';
            if ($accept) echo ' accept="' . $accept . '"';
            echo ' />';

            if ($field_note) {
                echo '<p class="field_note form-text">' . $field_note . '</p>';
            }
            echo '</div>';
            break;

        case 'custom':
            // Allow for custom field rendering via callback
            if (!empty($field['render_callback']) && is_callable($field['render_callback'])) {
                call_user_func($field['render_callback'], $field);
            }
            break;
    }

    // End wrapper
    echo '</div>';
}

/**
 * Helper function to generate options array from range
 *
 * @param int $start Start of range
 * @param int $end End of range
 * @param int $step Step increment
 * @param string $suffix Optional suffix for labels
 * @return array Options array for select/radio fields
 */
function generate_range_options($start, $end, $step = 1, $suffix = '') {
    $options = [];
    for ($i = $start; $i <= $end; $i += $step) {
        $options[$i] = $i . $suffix;
    }
    return $options;
}

/**
 * Helper function to generate yes/no options
 *
 * @return array Options array for select/radio fields
 */
function generate_yes_no_options() {
    return [
        '1' => __('Yes', 'cftp_admin'),
        '0' => __('No', 'cftp_admin')
    ];
}