<?php
/**
 * Options page and form.
 */
$allowed_levels = array(9);

$section = (!empty($_GET['section'])) ? $_GET['section'] : $_POST['section'];

$flash = get_container_item('flash');

switch ($section) {
    case 'general':
        $section_title = __('General options', 'cftp_admin');
        $checkboxes = array(
            'xsendfile_enable',
            'footer_custom_enable',
            'files_default_expire',
            'files_descriptions_use_ckeditor',
            'use_browser_lang',
        );
        break;
    case 'clients':
        $section_title = __('Clients', 'cftp_admin');
        $checkboxes = array(
            'clients_can_register',
            'clients_auto_approve',
            'clients_can_upload',
            'clients_can_delete_own_files',
            'clients_can_set_expiration_date',
            'clients_new_default_can_set_public',
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
            'svg_show_as_thumbnail',
            'pass_require_upper',
            'pass_require_lower',
            'pass_require_number',
            'pass_require_special',
            'recaptcha_enabled',
            'authentication_require_email_code',
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
    case 'cron':
        $section_title = __('Scheduled tasks (cron)', 'cftp_admin');
        $checkboxes = array(
            'cron_enable',
            'cron_command_line_only',
            'cron_send_emails',
            'cron_delete_expired_files',
            'cron_delete_orphan_files',
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

/** Form sent */
if ($_POST) {
    /**
     * Escape all the posted values on a single function.
     * Defined on functions.php
     */
    /** Values that can be empty */
    $allowed_empty_values = [
        'footer_custom_content',
        'mail_copy_addresses',
        'mail_smtp_host',
        'mail_smtp_port',
        'mail_smtp_user',
        'mail_smtp_pass',
        'recaptcha_site_key',
        'recaptcha_secret_key',
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
            if (empty($_POST[$keys[$i]]) && $_POST[$keys[$i]] != '0') {
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
    $new_record_action = $logger->addEntry([
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

include_once VIEWS_PARTS_DIR.DS.'header.php';
?>
<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">

                <form action="options.php" name="options" id="options" method="post" enctype="multipart/form-data" class="form-horizontal">
                    <?php \ProjectSend\Classes\Csrf::addCsrf(); ?>
                    <input type="hidden" name="section" value="<?php echo $section; ?>">

                    <?php
                    $form_file = FORMS_DIR . DS . 'options' . DS . $section . '.php';
                    if (file_exists($form_file)) {
                        include_once $form_file;
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
include_once VIEWS_PARTS_DIR.DS.'footer.php';
