<?php
/**
 * Define the language strings that are used on several parts of
 * the system, to avoid repetition.
 *
 * @package        ProjectSend
 * @subpackage    Core
 */

/**
 * System User Roles names
 */
define('USER_ROLE_LVL_9', __('System Administrator','cftp_admin'));
define('USER_ROLE_LVL_8', __('Account Manager','cftp_admin'));
define('USER_ROLE_LVL_7', __('Uploader','cftp_admin'));
define('USER_ROLE_LVL_0', __('Client','cftp_admin'));

/**
 * Strings served as a json array to use in JS
 */
global $json_strings;
$json_strings = [
    'uri' => [
        'base' => BASE_URI,
        'public_group' => PUBLIC_GROUP_URL,
        'public_download' => PUBLIC_DOWNLOAD_URL,
        'assets_img' => ASSETS_IMG_URL,
        'widgets' => WIDGETS_URL,
    ],
    'login' => [
        'button_text' => __('Log in','cftp_admin'),
        'logging_in' => __('Logging in','cftp_admin'),
        'redirecting' => __('Redirecting','cftp_admin'),
    ],
    'translations' => [
        'public_group_note' => __('Send this URL to someone to view the allowed group contents according to your privacy settings.','cftp_admin'),
        'public_file_note' => __('Send this URL to someone to download the file without registering or logging in.','cftp_admin'),
        'copy_click_select' => __('Click to select and copy','cftp_admin'),
        'copy_ok' => __('Successfully copied to clipboard','cftp_admin'),
        'copy_error' => __('Content could not be copied to clipboard','cftp_admin'),
        'public_url' => __('Public URL','cftp_admin'),
        'select_one_or_more' => __('Please select at least one item to proceed.','cftp_admin'),
        'confirm_delete' => __('You are about to delete %d items. Are you sure you want to continue?','cftp_admin'),
        'confirm_delete_log' => __('You are about to delete all activities from the log. Only those used for statistics will remain. Are you sure you want to continue?','cftp_admin'),
        'download_wait' => __('Please wait while your download is prepared.','cftp_admin'),
        'download_long_wait' => __('This operation could take a few minutes, depending on the size of the files.','cftp_admin'),
        'confirm_unassign' => __('You are about to unassign %d files from this account. Are you sure you want to continue?','cftp_admin'),
        'no_results' => __('No results were found.','cftp_admin'),
        'email_templates' => [
            'confirm_replace' => __('Please confirm: replace the custom template text with the default one?','cftp_admin'),
            'loading_error' => __('Error: the content could not be loaded','cftp_admin'),
        ],
        'upload_form' => [
            'no_files' => __("You must select at least one file to upload.",'cftp_admin'),
            'leave_confirm' => __("Are you sure? Files currently being uploaded will be discarded if you leave this page.",'cftp_admin'),
            'copy_selection' => __("Copy selection to all files?",'cftp_admin'),
            'copy_expiration' => __("Copy expiration settings to all files?",'cftp_admin'),
            'copy_public' => __("Copy public settings to all files?",'cftp_admin'),
            'copy_hidden' => __("Copy setting to all files?",'cftp_admin'),
            'some_files_had_errors' => __("Some of your files uploaded correctly, but others could not be uploaded.",'cftp_admin'),
            'continue_to_editor' => __("Go to the file editor",'cftp_admin'),
        ],
        'confirm_generic' => __('Confirm this action?', 'cftp_admin'),
        'preview_failed' => __('Failed to load file preview', 'cftp_admin'),
        'failed_loading_resource' => __('Failed to load resource', 'cftp_admin'),
    ],
    'validation' => [
        'errors_found_title' => __('The following errors were found','cftp_admin'),
        'default' => __('Validation "%s" failed for field "%s"','cftp_admin'),
        'recaptcha' => __('reCAPTCHA verification failed','cftp_admin'),
        'no_name' => __('Name was not completed','cftp_admin'),
        'no_client' => __('No client was selected','cftp_admin'),
        'no_user' => __('Username was not completed','cftp_admin'),
        'no_pass' => __('Password was not completed','cftp_admin'),
        'no_pass2' => __('Password verification was not completed','cftp_admin'),
        'no_email' => __('E-mail was not completed','cftp_admin'),
        'no_title' => __('Title was not completed','cftp_admin'),
        'invalid_email' => __('E-mail address is not valid','cftp_admin'),
        'alpha_user' => __('Username must be alphanumeric and may contain dot (a-z,A-Z,0-9 and . allowed)','cftp_admin'),
        'alpha_pass' => __('Password must be alphanumeric (a-z,A-Z,0-9 allowed)','cftp_admin'),
        'match_pass' => __('Passwords do not match','cftp_admin'),
        'rules_pass' => __('Password does not meet the required characters rules','cftp_admin'),
        'file_size' => __('File size value must be a whole number','cftp_admin'),
        'no_role' => __('User role was not specified','cftp_admin'),
        'user_exists' => __('An account with this username already exists.','cftp_admin'),
        'email_exists' => __('An account with this e-mail address already exists.','cftp_admin'),
        'valid_pass' => __('Your password can only contain letters, numbers and the following characters:','cftp_admin'),
        'valid_chars' => ('` ! " ? $ ? % ^ & * ( ) _ - + = { [ } ] : ; @ ~ # | < , > . ? \' / \ '),
        'complete_all_options' => __('Please complete all the fields.','cftp_admin'),
        
        /* Validation strings for the length of usernames and passwords. */
        'length_user' => sprintf(__('Length should be between %d and %d characters long', 'cftp_admin'), MIN_USER_CHARS, MAX_USER_CHARS),
        'length_pass' => sprintf(__('Length should be between %d and %d characters long', 'cftp_admin'), MIN_PASS_CHARS, MAX_PASS_CHARS),

        /* Password requirements */
        'req_upper' => __('1 uppercase character','cftp_admin'),
        'req_lower' => __('1 lowercase character','cftp_admin'),
        'req_number' => __('1 number','cftp_admin'),
        'req_special' => __('1 special character','cftp_admin'),
        
        /* Installation strings */
        'install_no_sitename' => __('Sitename was not completed.','cftp_admin'),
        'install_no_baseuri' => __('ProjectSend URI was not completed.','cftp_admin'),
    ],
    'character_limits' => [
        'user_min' => MIN_USER_CHARS,
        'user_max' => MAX_USER_CHARS,
        'password_min' => MIN_PASS_CHARS,
        'password_max' => MAX_PASS_CHARS,
    ]
];