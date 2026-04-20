<?php
/**
 * Theme Settings Management Interface
 * Allows administrators to configure individual theme options
 */
require_once 'bootstrap.php';
check_access_enhanced(['change_template']);

$page_title = __("Theme Settings", 'cftp_admin');
$active_nav = 'themes';

// Get theme parameter
$theme_name = $_GET['theme'] ?? get_option('selected_clients_template');

if (empty($theme_name)) {
    ps_redirect('themes.php');
}

// Validate theme exists and has settings
if (!theme_has_settings($theme_name)) {
    global $flash;
    $flash->error(__('This theme does not have configurable settings.', 'cftp_admin'));
    ps_redirect('themes.php');
}

// Load theme configuration
$theme_config = load_theme_settings_config($theme_name);
if (!$theme_config) {
    global $flash;
    $flash->error(__('Could not load theme settings configuration.', 'cftp_admin'));
    ps_redirect('themes.php');
}

// Get theme info for display
$templates = look_for_templates();
$theme_info = null;
foreach ($templates as $template) {
    if ($template['location'] === $theme_name) {
        $theme_info = $template;
        break;
    }
}

// Handle form submission
if ($_POST && validateCsrfToken()) {
    $success = true;
    $settings_updated = 0;

    foreach ($theme_config['settings'] as $setting_name => $setting_config) {
        $post_key = 'theme_' . $setting_name;
        $type = $setting_config['type'] ?? 'string';

        // Get value from POST data
        if (isset($_POST[$post_key])) {
            $value = $_POST[$post_key];

            // Validate based on type
            switch ($type) {
                case 'number':
                case 'integer':
                    $value = (int)$value;
                    if (isset($setting_config['min']) && $value < $setting_config['min']) {
                        $value = $setting_config['min'];
                    }
                    if (isset($setting_config['max']) && $value > $setting_config['max']) {
                        $value = $setting_config['max'];
                    }
                    break;
                case 'checkbox':
                case 'boolean':
                    $value = ($value === '1' || $value === 'on') ? true : false;
                    break;
                case 'select':
                    if (isset($setting_config['options']) && !array_key_exists($value, $setting_config['options'])) {
                        $value = $setting_config['default'] ?? '';
                    }
                    break;
                case 'color':
                    // Validate hex color format
                    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                        $value = $setting_config['default'] ?? '#000000';
                    }
                    break;
                default:
                    $value = trim($value);
            }
        } else {
            // Handle checkboxes that aren't checked (not in POST)
            if ($type === 'checkbox' || $type === 'boolean') {
                $value = false;
            } else {
                continue; // Skip if not in POST and not a checkbox
            }
        }

        // Save the setting
        if (save_theme_option($theme_name, $setting_name, $value, $type)) {
            $settings_updated++;
        } else {
            $success = false;
        }
    }

    global $flash;
    if ($success && $settings_updated > 0) {
        $flash->success(sprintf(__('%d theme settings updated successfully.', 'cftp_admin'), $settings_updated));
    } elseif ($settings_updated === 0) {
        $flash->info(__('No settings were changed.', 'cftp_admin'));
    } else {
        $flash->error(__('There was an error updating some settings. Please try again.', 'cftp_admin'));
    }

    ps_redirect('theme-settings.php?theme=' . urlencode($theme_name));
}

// Get current settings values
$current_settings = get_all_theme_options($theme_name);

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">
        <div class="ps-card">
            <div class="ps-card-body">
                <h3>
                    <?php echo sprintf(__('Settings for %s Theme', 'cftp_admin'), '<strong>' . html_output($theme_info['name'] ?? $theme_name) . '</strong>'); ?>
                </h3>

                <form action="" method="post" class="form-horizontal">
                    <?php addCsrf(); ?>

                    <?php foreach ($theme_config['settings'] as $setting_name => $setting_config): ?>
                        <?php
                        $field_name = 'theme_' . $setting_name;
                        $current_value = $current_settings[$setting_name] ?? ($setting_config['default'] ?? '');
                        $type = $setting_config['type'] ?? 'string';
                        ?>

                        <div class="form-group row">
                            <label class="col-sm-4 control-label" for="<?php echo $field_name; ?>">
                                <?php echo html_output($setting_config['label'] ?? ucwords(str_replace('_', ' ', $setting_name))); ?>
                            </label>
                            <div class="col-sm-8">
                                <?php switch ($type):
                                    case 'checkbox':
                                    case 'boolean': ?>
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>" value="1" <?php echo $current_value ? 'checked' : ''; ?>>
                                                <?php echo html_output($setting_config['label'] ?? ucwords(str_replace('_', ' ', $setting_name))); ?>
                                            </label>
                                        </div>
                                        <?php break;

                                    case 'number':
                                    case 'integer': ?>
                                        <input type="number" class="form-control" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>" value="<?php echo html_output($current_value); ?>"
                                               <?php if (isset($setting_config['min'])): ?>min="<?php echo $setting_config['min']; ?>"<?php endif; ?>
                                               <?php if (isset($setting_config['max'])): ?>max="<?php echo $setting_config['max']; ?>"<?php endif; ?>>
                                        <?php break;

                                    case 'select': ?>
                                        <select class="form-select" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>">
                                            <?php if (isset($setting_config['options'])): ?>
                                                <?php foreach ($setting_config['options'] as $option_value => $option_label): ?>
                                                    <option value="<?php echo html_output($option_value); ?>" <?php echo ($current_value == $option_value) ? 'selected' : ''; ?>>
                                                        <?php echo html_output($option_label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <?php break;

                                    case 'textarea': ?>
                                        <textarea class="form-control" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>" rows="<?php echo $setting_config['rows'] ?? 3; ?>"><?php echo html_output($current_value); ?></textarea>
                                        <?php break;

                                    case 'color': ?>
                                        <input type="color" class="form-control" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>" value="<?php echo html_output($current_value); ?>" style="max-width: 100px; height: 40px;">
                                        <small class="field_note form-text">Current: <?php echo html_output($current_value); ?></small>
                                        <?php break;

                                    default: // text input ?>
                                        <input type="text" class="form-control" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>" value="<?php echo html_output($current_value); ?>">
                                        <?php break;
                                endswitch; ?>

                                <?php if (!empty($setting_config['description'])): ?>
                                    <p class="field_note"><?php echo html_output($setting_config['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="options_divide"></div>

                    <div class="after_form_buttons">
                        <button type="submit" class="btn btn-wide btn-primary empty">
                            <?php _e('Save Settings', 'cftp_admin'); ?>
                        </button>
                        <a href="themes.php" class="btn btn-default btn-wide">
                            <?php _e('Back to Themes', 'cftp_admin'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_VIEWS_DIR . DS . 'footer.php'; ?>