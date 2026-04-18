<?php
/**
 * Contains all the functions used to validate the current logged in
 * client or user.
 */

function log_in_required($allowed_levels)
{
    // Check for an active session
    redirect_if_not_logged_in();

    // Check if the current user has permission to view this page.
    redirect_if_role_not_allowed($allowed_levels);
}

function extend_session()
{
    $_SESSION['last_call'] = time();
}

function session_expired()
{
    if ( defined('SESSION_TIMEOUT_EXPIRE') && SESSION_TIMEOUT_EXPIRE == true ) {
        if (isset($_SESSION['last_call']) && (time() - $_SESSION['last_call'] > SESSION_EXPIRE_TIME)) {
            return true;
        }
    }

    return false;
}

/**
 * Used on header.php to check if there is an active session or valid
 * cookie before generating the content.
 * If none is found, redirect to the log in form.
 */
function redirect_if_not_logged_in()
{
    $redirect = false;
    if (!user_is_logged_in()) {
        $redirect = true;
    } else {
        if (isset($_SESSION['user_id'])) {
            $user = new \ProjectSend\Classes\Users($_SESSION['user_id']);
            if (!$user->userExists()) {
                $redirect = true;
            }
        }

        // Also check if CURRENT_USER_ID is properly defined
        // This can be undefined if session is partially invalid
        if (!defined('CURRENT_USER_ID')) {
            $redirect = true;
        }
    }

    if ($redirect) {
        $_SESSION = [];
        session_destroy();
        ps_redirect(BASE_URI . "index.php");
        exit; // Ensure script execution stops
    }
}

function user_is_logged_in()
{
    if (isset($_SESSION['user_id'])) {
        $user = new \ProjectSend\Classes\Users($_SESSION['user_id']);
        if ($user->userExists()) {
            return true;
        }
    }

    // Check for remember me token if no valid session
    if (get_option('remember_me_enabled', null, '1')) {
        global $auth;
        if (!$auth) {
            $auth = new \ProjectSend\Classes\Auth();
        }
        
        if ($auth->loginWithRememberMe()) {
            return true;
        }
    }

    return false;
}

/**
 * Clean up expired remember me tokens
 * Should be called periodically (e.g., on cron jobs or random login attempts)
 */
function cleanup_expired_remember_tokens()
{
    if (get_option('remember_me_enabled', null, '1')) {
        // Run cleanup randomly on 1% of requests to avoid performance impact
        if (mt_rand(1, 100) <= 1) {
            $rememberMe = new \ProjectSend\Classes\RememberMe();
            $cleaned = $rememberMe->cleanExpiredTokens();
            if ($cleaned > 0) {
                error_log("ProjectSend: Cleaned up $cleaned expired remember me tokens");
            }
        }
        
        // Also check if current browser has an invalid remember me cookie
        // and clean it up (regardless of session status)
        $rememberMe = new \ProjectSend\Classes\RememberMe();
        $current_token = $rememberMe->getTokenFromCookie();
        
        if ($current_token) {
            // Validate token against database
            $token_hash = hash('sha256', $current_token);
            global $dbh;
            $stmt = $dbh->prepare("SELECT id FROM " . TABLE_REMEMBER_TOKENS . " WHERE token_hash = ? AND expires_at > NOW()");
            $stmt->execute([$token_hash]);
            
            if (!$stmt->fetch()) {
                // Token is invalid or expired, clear the cookie
                $rememberMe->clearCookie();
                error_log("ProjectSend: Cleared invalid remember me cookie");
            }
        }
    }
}

/**
 * Used on header.php to check if the current logged in system user has the
 * permission to view this page.
 */
function redirect_if_role_not_allowed($allowed_levels = null) {
	$permission = false;

    if (!empty($allowed_levels)) {
		/**
		 * Check for a session, and if found see if the user
		 * level is among those defined by the page.
		 *
		 * $allowed_levels in defined on each page before the inclusion of header.php
         *
         * UPDATED: Now supports both old level system and new role-based system
        */
        if (user_is_logged_in()) {
            $user = new \ProjectSend\Classes\Users($_SESSION['user_id']);

            // NEW: Check using role names (preferred method)
            $role_data = $user->getRoleData();
            if ($role_data) {
                $role_name = $role_data['name'];

                // Map old levels to role names for backward compatibility
                $allowed_roles = [];
                foreach ($allowed_levels as $level) {
                    switch ($level) {
                        case 9:
                            $allowed_roles[] = 'System Administrator';
                            break;
                        case 8:
                            $allowed_roles[] = 'Account Manager';
                            break;
                        case 7:
                            $allowed_roles[] = 'Uploader';
                            break;
                        case 0:
                            $allowed_roles[] = 'Client';
                            break;
                        default:
                            // Handle custom roles by loading from database
                            try {
                                $custom_role = \ProjectSend\Classes\Roles::getRoleByLevel($level);
                                if ($custom_role) {
                                    $allowed_roles[] = $custom_role['name'];
                                }
                            } catch (Exception $e) {
                                // Continue without adding unknown role
                            }
                            break;
                    }
                }

                if (in_array($role_name, $allowed_roles)) {
                    $permission = true;
                }
            }

            // FALLBACK: If role data not available, use old method
            if (!$permission) {
                $user_data = $user->getProperties();
                if (isset($user_data['role_id'])) {
                    // Convert role_id to role name for compatibility with numeric level checks
                    try {
                        $role = new \ProjectSend\Classes\Roles($user_data['role_id']);
                        if ($role->exists()) {
                            // Map role names to allowed levels (backwards compatibility)
                            $role_level_map = [
                                'System Administrator' => 9,
                                'Account Manager' => 8,
                                'Uploader' => 7,
                                'Client' => 0
                            ];

                            if (isset($role_level_map[$role->name])) {
                                $role_level = $role_level_map[$role->name];
                                if (in_array($role_level, $allowed_levels)) {
                                    $permission = true;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // Continue with legacy check
                    }
                }
            }
        }
		/**
		 * After the checks, if the user is allowed, continue.
		 * If not, show the "Not allowed message", then the footer, then die(); so the
		 * actual page content is not generated.
		*/
    }

    if ($permission != true) {
        exit_with_error_code(403);
    }
}

/**
 * Enhanced access control based on permissions
 * @param array $required_permissions Array of permission names required
 * @param string $access_type Type of access required: 'any' (default) or 'all'
 */
function check_access_enhanced($required_permissions = null, $access_type = 'any') {
    // Check permission-based access if provided
    if (!empty($required_permissions) && user_is_logged_in()) {
        $permissions = new \ProjectSend\Classes\Permissions($_SESSION['user_id']);

        if ($access_type === 'all') {
            // User must have ALL specified permissions
            foreach ($required_permissions as $permission) {
                if (!$permissions->can($permission)) {
                    exit_with_error_code(403);
                }
            }
        } else {
            // User must have ANY of the specified permissions (default)
            $has_permission = false;
            foreach ($required_permissions as $permission) {
                if ($permissions->can($permission)) {
                    $has_permission = true;
                    break;
                }
            }
            if (!$has_permission) {
                exit_with_error_code(403);
            }
        }
        return; // Permission access granted
    }

    // If no permission access was granted
    if (!empty($required_permissions)) {
        exit_with_error_code(403);
    }
}

/**
 * Check permissions before page access - supports both permissions and roles
 * @param array $required_permissions Array of permission names required
 * @param array $allowed_roles Fallback array of role levels (for backward compatibility)
 */
function redirect_if_permission_not_allowed($required_permissions = null, $allowed_roles = null) {
    $permission = false;

    // If permissions are specified, check them first
    if (!empty($required_permissions) && user_is_logged_in()) {
        foreach ($required_permissions as $perm) {
            if (current_user_can($perm)) {
                $permission = true;
                break;
            }
        }
    }

    // Fallback to role-based check if permission check failed and roles are specified
    if (!$permission && !empty($allowed_roles)) {
        redirect_if_role_not_allowed($allowed_roles);
        return; // Will exit if fails
    }

    // If only roles specified (no permissions), use legacy function
    if (empty($required_permissions) && !empty($allowed_roles)) {
        redirect_if_role_not_allowed($allowed_roles);
        return;
    }

    if (!$permission) {
        exit_with_error_code(403);
    }
}

/**
 * Enhanced log_in_required function that supports permissions
 * @param array $allowed_levels Legacy role levels
 * @param array $required_permissions New permission-based checks
 */
function log_in_required_enhanced($allowed_levels = null, $required_permissions = null) {
    // Check for an active session
    redirect_if_not_logged_in();

    // Check permissions or roles
    redirect_if_permission_not_allowed($required_permissions, $allowed_levels);
}

// Requires password change?
function password_change_required()
{
    global $flash;

    if (!defined('CURRENT_USER_ID')) {
        return;
    }

    $session_user = new \ProjectSend\Classes\Users(CURRENT_USER_ID);

    if ($session_user->requiresPasswordChange()) {
        $url = current_role_in(['Client']) ? 'clients-edit.php' : 'users-edit.php';
        if (basename($_SERVER["SCRIPT_FILENAME"]) != $url) {
            $flash->error(__('Password change is required for your account', 'cftp_admin'));

            $url .= '?id='.CURRENT_USER_ID;
            ps_redirect(BASE_URI.$url);
        }
    }
}

// Requires TOTP setup?
function totp_setup_required()
{
    global $flash;

    if (!defined('CURRENT_USER_ID')) {
        return;
    }

    if (!(bool)get_option('two_factor_required', null, '0')) {
        return;
    }

    if (!(bool)get_option('two_factor_allow_totp', null, '1')) {
        return;
    }

    $totp = new \ProjectSend\Classes\Totp();
    if ($totp->isEnabledForUser(CURRENT_USER_ID)) {
        return;
    }

    // Allow TOTP setup page and process.php (handles logout)
    $current_page = basename($_SERVER["SCRIPT_FILENAME"]);
    $allowed_pages = ['totp-setup.php', 'process.php'];
    if (in_array($current_page, $allowed_pages)) {
        return;
    }

    $flash->warning(__('Two-factor authentication is required for your account. Please set up an authenticator app before continuing.', 'cftp_admin'));
    ps_redirect(BASE_URI . 'totp-setup.php');
}

function user_can_upload_any_file_type($user_id = null)
{
    // Use CURRENT_USER_ID if no user_id provided and constant is defined
    if ($user_id === null) {
        if (defined('CURRENT_USER_ID')) {
            $user_id = CURRENT_USER_ID;
        } else {
            return false;
        }
    }

    $user = new \ProjectSend\Classes\Users($user_id);

    if (!empty(get_option('file_types_limit_to'))) {
        switch ( get_option('file_types_limit_to') ) {
            case 'noone':
                return true;
            break;
            case 'all':
                return false;
            break;
            case 'clients':
                if ($user->isClient()) {
                    return false;
                }
            break;
        }
    }
    unset($user);
    
    return true;
}

function current_user_can_view_files_list()
{
    if (defined('IS_PUBLIC_VIEW')) {
        return true;
    }

    // Check if user is properly logged in with CURRENT_USER_ID defined
    if (!defined('CURRENT_USER_ID') || !defined('CURRENT_USER_USERNAME')) {
        return false;
    }

    $user = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
    $props = $user->getProperties();

    if ( $props['active'] == '0' ) {
        return false;
    }

    if (!$user->isClient()) {
        return true;
    } else {
        if ($props['username'] == CURRENT_USER_USERNAME) {
            return true;
        }
    }

    return false;
}
