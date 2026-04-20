<?php
/**
 * Options page and form.
 */
require_once 'bootstrap.php';
check_access_enhanced(['edit_settings']);

$section = (!empty($_GET['section'])) ? $_GET['section'] : $_POST['section'];

global $flash;

switch ($section) {
    case 'general':
        $section_title = __('General options', 'cftp_admin');
        $checkboxes = array(
            'xsendfile_enable',
            'footer_custom_enable',
            'use_browser_lang',
        );
        break;
    case 'uploads':
        $section_title = __('Uploads', 'cftp_admin');
        $checkboxes = array(
            'uploads_organize_folders_by_date',
            'files_descriptions_use_ckeditor',
            'files_default_expire',
            'files_default_public',
            'download_logging_ignore_file_author',
        );
        break;
    case 'clients':
        $section_title = __('Clients', 'cftp_admin');
        $checkboxes = array(
            'clients_can_register',
            'clients_auto_approve',
            'clients_files_list_include_public',
        );
        break;
    case 'privacy':
        $section_title = __('Privacy', 'cftp_admin');
        $checkboxes = array(
            'privacy_noindex_site',
            'enable_landing_for_all_files',
            'public_listing_page_enable',
            'public_listing_logged_only',
            'public_listing_show_all_files',
            'public_listing_use_download_link',
            'public_listing_enable_preview',
            'public_listing_home_show_link',
        );
        break;
    case 'email':
        $section_title = __('E-mail notifications', 'cftp_admin');
        $checkboxes = array(
            'mail_copy_user_upload',
            'mail_copy_client_upload',
            'mail_copy_main_user',
            'mail_ssl_verify_peer',
            'mail_ssl_verify_peer_name',
            'mail_ssl_allow_self_signed',
            'notifications_send_when_saving_files',
        );
        break;
    case 'security':
        $section_title = __('Security', 'cftp_admin');
        $checkboxes = array(
            'prevent_updates_check',
            'svg_show_as_thumbnail',
            'pass_require_upper',
            'pass_require_lower',
            'pass_require_number',
            'pass_require_special',
            'recaptcha_enabled',
            'two_factor_required',
            'two_factor_allow_email',
            'two_factor_allow_totp',
            'remember_me_enabled',
        );
        break;
    case 'encryption':
        $section_title = __('File Encryption', 'cftp_admin');
        $checkboxes = array(
            'files_encryption_enabled',
            'files_encryption_required',
        );
        break;
    case 'branding':
        $section_title = __('Branding', 'cftp_admin');
        $checkboxes = array();
        break;
    case 'external_login':
        $section_title = __('External Login', 'cftp_admin');
        $checkboxes = array();
        break;
    case 'ldap':
        $section_title = __('LDAP Authentication', 'cftp_admin');
        $checkboxes = array();
        break;
    case 'social_login':
        $section_title = __('Social Networks Login', 'cftp_admin');
        $checkboxes = array();
        break;
    case 'cron':
        $section_title = __('Scheduled tasks (cron)', 'cftp_admin');
        $checkboxes = array(
            'cron_enable',
            'cron_command_line_only',
            'cron_send_emails',
            'cron_delete_expired_files',
            'cron_delete_orphan_files',
            'cron_save_log_database',
            'cron_email_summary_send',
        );
        break;
    default:
        ps_redirect(BASE_URI . 'options.php?section=general');
        break;
}

$page_title = $section_title;

$page_id = 'options';

$active_nav = 'options';

// Logo
$logo_file_info = generate_logo_url();

// Clear logo
if ($section == 'branding' && !empty($_GET['clear']) && $_GET['clear'] == 'logo') {
    save_option('logo_filename', null);
    $flash->success(__('Options updated successfully.', 'cftp_admin'));
    ps_redirect(BASE_URI . 'options.php?section=branding');
}

// Clear favicon
if ($section == 'branding' && !empty($_GET['clear']) && $_GET['clear'] == 'favicon') {
    save_option('favicon_filename', null);
    $flash->success(__('Options updated successfully.', 'cftp_admin'));
    ps_redirect(BASE_URI . 'options.php?section=branding');
}

/** Form sent */
if ($_POST) {
    /**
     * Escape all the posted values on a single function.
     * Defined on functions.php
     */
    /** Values that can be empty */
    $allowed_empty_values = [
        'footer_custom_content',
        'custom_download_uri',
        'mail_copy_addresses',
        'mail_smtp_host',
        'mail_smtp_port',
        'mail_smtp_user',
        'mail_smtp_pass',
        'recaptcha_site_key',
        'recaptcha_secret_key',
        'recaptcha_v3_site_key',
        'recaptcha_v3_secret_key',
        'recaptcha_v3_score_threshold',
        'cloudflare_turnstile_site_key',
        'cloudflare_turnstile_secret_key',
        'captcha_method',
        'remember_me_duration_days',
        'remember_me_max_tokens_per_user',
        'google_client_id',
        'google_client_secret',
        'facebook_client_id',
        'facebook_client_secret',
        'linkedin_client_id',
        'linkedin_client_secret',
        'openid_client_id',
        'openid_client_secret',
        'twitter_client_id',
        'twitter_client_secret',
        'windowslive_client_id',
        'windowslive_client_secret',
        'microsoftgraph_client_id',
        'microsoftgraph_client_secret',
        'microsoftgraph_client_tenant',
        'yahoo_client_id',
        'yahoo_client_secret',
        'oidc_identifier_url',
        'ldap_signin_enabled',
        'ldap_hosts',
        'ldap_port',
        'ldap_bind_dn',
        'ldap_admin_user',
        'ldap_admin_password',
        'ldap_search_base',
        'ldap_username_attribute',
        'ldap_search_filter',
        'ldap_email_attribute',
        'ldap_name_attribute',
        'ldap_account_suffix',
        'ldap_use_tls',
        'ldap_default_role',
        'ldap_auto_create_users',
        'social_login_auto_enable',
        'social_login_default_role',
        'ip_whitelist',
        'ip_blacklist',
        'cron_email_summary_address_to',
    ];

    foreach ($checkboxes as $checkbox) {
        $_POST[$checkbox] = (empty($_POST[$checkbox]) || !isset($_POST[$checkbox])) ? 0 : 1;
    }

    // Remove values that should not be saved
    $remove_keys = array(
        'csrf_token',
    );

    foreach ($remove_keys as $key) {
        unset($_POST[$key]);
    }

    $keys = array_keys($_POST);

    $options_total = count($keys);
    $options_missing = 0;

    // Check if all the options are filled.
    for ($i = 0; $i < $options_total; $i++) {
        if (!in_array($keys[$i], $allowed_empty_values)) {
            if (empty($_POST[$keys[$i]]) && $_POST[$keys[$i]] !== '0' && $_POST[$keys[$i]] !== 0) {
                $options_missing++;
            }
        }
    }

    // If uploading a logo on the branding page
    if (isset($_FILES['select_logo']) && !empty($_FILES['select_logo'])) {
        $upload_logo = option_file_upload($_FILES['select_logo'], 'image', 'logo_filename', 29);
        if ($upload_logo['status'] != 'success') {
            $flash->error($upload_logo['message']);
        }
    }

    // If uploading a favicon on the branding page
    if (isset($_FILES['select_favicon']) && !empty($_FILES['select_favicon']['name'])) {
        $upload_favicon = option_file_upload($_FILES['select_favicon'], 'image', 'favicon_filename', 30);
        if ($upload_favicon['status'] != 'success') {
            $flash->error($upload_favicon['message']);
        }
    }

    // Validate encryption settings - cannot enable without master key configured
    if ($section == 'encryption' && !empty($_POST['files_encryption_enabled'])) {
        if (!defined('ENCRYPTION_MASTER_KEY') || empty(ENCRYPTION_MASTER_KEY)) {
            $flash->error(__('Cannot enable encryption: ENCRYPTION_MASTER_KEY is not configured in sys.config.php. Please add this constant to your configuration file before enabling encryption.', 'cftp_admin'));
            ps_redirect(BASE_URI . 'options.php?section=encryption');
        }
    }

    // Validate 2FA settings - cannot require 2FA with no methods allowed
    if ($section == 'security' && !empty($_POST['two_factor_required'])) {
        if (empty($_POST['two_factor_allow_email']) && empty($_POST['two_factor_allow_totp'])) {
            $flash->error(__('Cannot require two-factor authentication when both email and authenticator app methods are disabled. Please enable at least one method.', 'cftp_admin'));
            ps_redirect(BASE_URI . 'options.php?section=security');
        }
    }

    // If every option is completed, continue
    if ($options_missing > 0) {
        $flash->error(__('Some fields were not completed. Options could not be saved.', 'cftp_admin'));
    } else {
        // Convert file types, they are posted as a json string via tagify
        if (!empty($_POST['allowed_file_types'])) {
            $_POST['allowed_file_types'] = explode(',', str_replace(' ', '', implode(', ', array_column(json_decode($_POST['allowed_file_types']), 'value'))));
            sort($_POST['allowed_file_types']);
            $_POST['allowed_file_types'] = implode(',', $_POST['allowed_file_types']);
        }

        // Base URI should always end with /
        if (!empty($_POST['base_uri'])) {
            if (substr($_POST['base_uri'], -1) != '/') {
                $_POST['base_uri'] .= '/';
            }
        }

        $updated = 0;
        for ($j = 0; $j < $options_total; $j++) {
            $save = save_option($keys[$j], $_POST[$keys[$j]]);

            if ($save) {
                $updated++;
            }
        }
        if ($updated > 0) {
            $flash->success(__('Options updated successfully.', 'cftp_admin'));
        } else {
            $flash->error(__('There was an error. Please try again.', 'cftp_admin'));
        }
    }

    // Record the action log
    $logger = new \ProjectSend\Classes\ActionsLog;
    $logger->addEntry([
        'action' => 47,
        'owner_id' => CURRENT_USER_ID,
        'owner_user' => CURRENT_USER_USERNAME,
        'details' => [
            'section' => $section,
        ],
    ]);

    // Redirect so the options are reflected immediately
    ps_redirect(BASE_URI . 'options.php?section=' . html_output($_POST['section']));
}

if ($section == 'security') {
    // If .php files are allowed, set the flag for the warning message
    $allowed_file_types = explode(',', get_option('allowed_file_types'));
    if (in_array('php', $allowed_file_types)) {
        $flash->warning(__('Warning: php extension is allowed. This is a serious security problem. If you are not sure that you need it, please remove it from the list.', 'cftp_admin'));
    }
}

// Test folders
if ($section == 'general' && get_option('uploads_organize_folders_by_date') == '1') {
    $test_folder = UPLOADED_FILES_DIR.DS.'temp_folder_'.rand(10000,99999);
    if (@mkdir($test_folder, 0775)) {
        @rmdir($test_folder);
    } else {
        $flash->error("Warning: could not create a test folder in the uploads directory. Please try setting it's permissions to 775 and reload this page. If the issue persists, please disable the option to organize uploads in date based folders.");
    }
}


include_once ADMIN_VIEWS_DIR . DS . 'header.php';

// Load form sections to get navigation data
$form_file = FORMS_DIR . DS . 'options' . DS . $section . '.php';
$form_sections_for_nav = [];
if (file_exists($form_file)) {
    // Include the file and capture output to prevent double rendering
    ob_start();
    include_once $form_file;
    ob_end_clean();

    // Check if $form_sections was defined in the included file
    if (isset($form_sections)) {
        $form_sections_for_nav = $form_sections;
    }
}
?>
<div class="row">
    <?php if (!empty($form_sections_for_nav)): ?>
    <!-- Sticky vertical navigation (hidden on mobile) -->
    <div class="col-lg-2 d-none d-lg-block">
        <?php render_options_section_navigation($form_sections_for_nav); ?>
    </div>
    <?php endif; ?>

    <!-- Main content area -->
    <div class="col-12 col-lg-7">
        <div class="ps-card">
            <div class="ps-card-body">

                <form action="options.php" name="options" id="options" method="post" enctype="multipart/form-data" class="form-horizontal">
                    <?php addCsrf(); ?>
                    <input type="hidden" name="section" value="<?php echo $section; ?>">

                    <?php
                    // Check if sections have actual fields or are just navigation stubs
                    $has_fields = false;
                    if (!empty($form_sections_for_nav)) {
                        foreach ($form_sections_for_nav as $section) {
                            if (!empty($section['fields'])) {
                                $has_fields = true;
                                break;
                            }
                        }
                    }

                    // Render the form sections if using the new array-based system with fields
                    if ($has_fields) {
                        render_options_form_sections($form_sections_for_nav, false); // false = don't render nav inline
                    } else {
                        // Include the form file directly for legacy forms or navigation-only stubs
                        if (file_exists($form_file)) {
                            include $form_file;
                        }
                    }
                    ?>

                    <div class="options_divide"></div>

                    <div class="after_form_buttons">
                        <button type="submit" class="btn btn-wide btn-primary empty"><?php _e('Save options', 'cftp_admin'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
