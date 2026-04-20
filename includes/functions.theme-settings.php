<?php
/**
 * Theme Settings Functions for ProjectSend
 * Handles individual theme configuration options
 */

// Define table constant if not already defined
if (!defined('TABLE_THEME_SETTINGS')) {
    define('TABLE_THEME_SETTINGS', TABLES_PREFIX . 'theme_settings');
}

/**
 * Check if a theme setting exists
 *
 * @param string $theme_name
 * @param string $setting_name
 * @return bool
 */
function theme_setting_exists($theme_name, $setting_name)
{
    global $dbh;
    $statement = $dbh->prepare("SELECT id FROM " . TABLE_THEME_SETTINGS . " WHERE theme_name=:theme_name AND setting_name=:setting_name");
    $statement->execute([
        ':theme_name' => $theme_name,
        ':setting_name' => $setting_name,
    ]);
    return ($statement->rowCount() > 0);
}

/**
 * Get a theme-specific setting value
 *
 * @param string $theme_name
 * @param string $setting_name
 * @param mixed $default
 * @return mixed
 */
function get_theme_option($theme_name, $setting_name, $default = null)
{
    global $dbh;
    if (empty($dbh)) {
        return $default;
    }

    try {
        if (table_exists(TABLE_THEME_SETTINGS)) {
            $statement = $dbh->prepare("SELECT setting_value, setting_type FROM " . TABLE_THEME_SETTINGS . " WHERE theme_name=:theme_name AND setting_name=:setting_name");
            $statement->execute([
                ':theme_name' => $theme_name,
                ':setting_name' => $setting_name,
            ]);

            if ($statement->rowCount() == 0) {
                return $default;
            }

            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $row = $statement->fetch();

            $value = $row['setting_value'];
            $type = $row['setting_type'];

            // Convert value based on type
            switch ($type) {
                case 'checkbox':
                case 'boolean':
                    return (bool)$value;
                case 'number':
                case 'integer':
                    return (int)$value;
                case 'float':
                    return (float)$value;
                case 'json':
                    return json_decode($value, true);
                default:
                    return $value;
            }
        }
    } catch (\PDOException $e) {
        error_log("Theme settings error: " . $e->getMessage());
        return $default;
    }

    return $default;
}

/**
 * Save a theme-specific setting
 *
 * @param string $theme_name
 * @param string $setting_name
 * @param mixed $value
 * @param string $type
 * @return bool
 */
function save_theme_option($theme_name, $setting_name, $value, $type = 'string')
{
    global $dbh;

    // Convert value based on type for storage
    switch ($type) {
        case 'checkbox':
        case 'boolean':
            $value = $value ? '1' : '0';
            break;
        case 'json':
            $value = json_encode($value);
            break;
        default:
            $value = (string)$value;
    }

    if (theme_setting_exists($theme_name, $setting_name)) {
        $save = $dbh->prepare("UPDATE " . TABLE_THEME_SETTINGS . " SET setting_value=:value, setting_type=:type, updated_date=NOW() WHERE theme_name=:theme_name AND setting_name=:setting_name");
        $save->bindParam(':value', $value);
        $save->bindParam(':type', $type);
        $save->bindParam(':theme_name', $theme_name);
        $save->bindParam(':setting_name', $setting_name);
        $result = $save->execute();
    } else {
        if (!empty($dbh)) {
            $save = $dbh->prepare("INSERT INTO " . TABLE_THEME_SETTINGS . " (theme_name, setting_name, setting_value, setting_type) VALUES (:theme_name, :setting_name, :value, :type)");
            $save->bindParam(':theme_name', $theme_name);
            $save->bindParam(':setting_name', $setting_name);
            $save->bindParam(':value', $value);
            $save->bindParam(':type', $type);
            $result = $save->execute();
        } else {
            $result = false;
        }
    }

    return $result;
}

/**
 * Get all settings for a specific theme
 *
 * @param string $theme_name
 * @return array
 */
function get_all_theme_options($theme_name)
{
    global $dbh;
    $settings = [];

    try {
        if (table_exists(TABLE_THEME_SETTINGS)) {
            $statement = $dbh->prepare("SELECT setting_name, setting_value, setting_type FROM " . TABLE_THEME_SETTINGS . " WHERE theme_name=:theme_name ORDER BY setting_name");
            $statement->execute([':theme_name' => $theme_name]);

            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $value = $row['setting_value'];
                $type = $row['setting_type'];

                // Convert value based on type
                switch ($type) {
                    case 'checkbox':
                    case 'boolean':
                        $value = (bool)$value;
                        break;
                    case 'number':
                    case 'integer':
                        $value = (int)$value;
                        break;
                    case 'float':
                        $value = (float)$value;
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }

                $settings[$row['setting_name']] = $value;
            }
        }
    } catch (\PDOException $e) {
        error_log("Theme settings error: " . $e->getMessage());
    }

    return $settings;
}

/**
 * Check if a theme has settings capability
 *
 * @param string $theme_name
 * @return bool
 */
function theme_has_settings($theme_name)
{
    $settings_file = TEMPLATES_DIR . DS . $theme_name . DS . 'settings.php';
    return file_exists($settings_file);
}

/**
 * Load theme settings configuration
 *
 * @param string $theme_name
 * @return array|false
 */
function load_theme_settings_config($theme_name)
{
    $settings_file = TEMPLATES_DIR . DS . $theme_name . DS . 'settings.php';

    if (!file_exists($settings_file)) {
        return false;
    }

    $config = include $settings_file;
    return is_array($config) ? $config : false;
}

/**
 * Delete a theme setting
 *
 * @param string $theme_name
 * @param string $setting_name
 * @return bool
 */
function delete_theme_option($theme_name, $setting_name)
{
    global $dbh;

    $statement = $dbh->prepare("DELETE FROM " . TABLE_THEME_SETTINGS . " WHERE theme_name=:theme_name AND setting_name=:setting_name");
    $statement->execute([
        ':theme_name' => $theme_name,
        ':setting_name' => $setting_name,
    ]);

    return $statement->rowCount() > 0;
}

/**
 * Delete all settings for a theme
 *
 * @param string $theme_name
 * @return bool
 */
function delete_all_theme_options($theme_name)
{
    global $dbh;

    $statement = $dbh->prepare("DELETE FROM " . TABLE_THEME_SETTINGS . " WHERE theme_name=:theme_name");
    $statement->execute([':theme_name' => $theme_name]);

    return $statement->rowCount() > 0;
}

/**
 * Initialize default theme settings from config
 *
 * @param string $theme_name
 * @return bool
 */
function initialize_theme_settings($theme_name)
{
    $config = load_theme_settings_config($theme_name);
    if (!$config || !isset($config['settings'])) {
        return false;
    }

    $success = true;
    foreach ($config['settings'] as $setting_name => $setting_config) {
        if (!theme_setting_exists($theme_name, $setting_name) && isset($setting_config['default'])) {
            $type = $setting_config['type'] ?? 'string';
            $result = save_theme_option($theme_name, $setting_name, $setting_config['default'], $type);
            if (!$result) {
                $success = false;
            }
        }
    }

    return $success;
}

/**
 * Get theme setting with theme prefix (for backward compatibility)
 *
 * @param string $theme_name
 * @param string $setting_name
 * @param mixed $default
 * @return mixed
 */
function get_theme_setting($theme_name, $setting_name, $default = null)
{
    // Try new theme settings first
    $value = get_theme_option($theme_name, $setting_name, null);
    if ($value !== null) {
        return $value;
    }

    // Fallback to old-style prefixed options for backward compatibility
    $prefixed_name = $theme_name . '_' . $setting_name;
    return get_option($prefixed_name, false, $default);
}