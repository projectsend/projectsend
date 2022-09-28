<?php
/**
 * Class that handles all the e-mails that the system can send.
 * @todo Separate each Email type into classes that extend this one
 */

namespace ProjectSend\Classes;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Emails
{
    private $header;
    private $footer;
    private $email_successful;

    function __construct()
    {
        $this->header = (get_option('email_header_footer_customize') == '1') ? get_option('email_header_text') : file_get_contents(EMAIL_TEMPLATES_DIR . DS . EMAIL_TEMPLATE_HEADER);
        $this->footer = (get_option('email_header_footer_customize') == '1') ? get_option('email_footer_text') : file_get_contents(EMAIL_TEMPLATES_DIR . DS . EMAIL_TEMPLATE_FOOTER);
    }

    private function getEmailTypes()
    {
        return [
            'header_footer',
            'new_files_by_user',
            'new_files_by_client',
            'new_client',
            'new_client_self',
            'account_approve',
            'account_deny',
            'new_user',
            'password_reset',
            'client_edited',
            '2fa_code',
        ];
    }

    public function emailTypeExists($email_type)
    {
        return in_array($email_type, $this->getEmailTypes());
    }

    private function getEmailTypeData($email_type)
    {
        $uri_note = __('The login link (to be used as href on a link tag)', 'cftp_admin');

        switch ($email_type) {
            case 'header_footer':
                $strings = [];
                $options = [
                    'title' => __('Header / footer', 'cftp_admin'),
                    'checkboxes' => [
                        'email_header_footer_customize',
                    ],
                    'items' => [],
                ];
                break;
            case 'new_files_by_user':
                // Strings for the "New file uploaded" BY A SYSTEM USER e-mail
                $strings = array(
                    'subject' => (get_option('email_new_file_by_user_subject_customize') == 1 && !empty(get_option('email_new_file_by_user_subject'))) ? get_option('email_new_file_by_user_subject') : __('New files uploaded for you', 'cftp_admin'),
                    'body' => __('The following files are now available for you to download.', 'cftp_admin'),
                    'body2' => __("If you prefer not to be notified about new files, please go to My Account and deactivate the notifications checkbox.", 'cftp_admin'),
                    'body3' => __('You can access a list of all your files or upload your own', 'cftp_admin'),
                    'body4' => __('by logging in here', 'cftp_admin')
                );
                $options = [
                    'title' => __('New file by user', 'cftp_admin'),
                    'checkboxes' => [
                        'email_new_file_by_user_customize',
                        'email_new_file_by_user_subject_customize',
                    ],
                    'items' => [
                        'subject_checkbox' => 'email_new_file_by_user_subject_customize',
                        'subject' => 'email_new_file_by_user_subject',
                        'body_checkbox' => 'email_new_file_by_user_customize',
                        'body_textarea' => 'email_new_file_by_user_text',
                        'description' => __('This email will be sent to a client whenever a new file has been assigned to their account.', 'cftp_admin'),
                        'subject_check' => get_option('email_new_file_by_user_subject_customize'),
                        'subject_text' => get_option('email_new_file_by_user_subject'),
                        'body_check' => get_option('email_new_file_by_user_customize'),
                        'body_text' => get_option('email_new_file_by_user_text'),
                        'default_text' => EMAIL_TEMPLATE_NEW_FILE_BY_USER,
                        'tags' => [
                            '%FILES%' => __('Shows the list of files', 'cftp_admin'),
                            '%URI%' => $uri_note,
                        ],
                    ],
                ];
                break;
            case 'new_files_by_client':
                // Strings for the "New file uploaded" BY A CLIENT e-mail
                $strings = array(
                    'subject' => (get_option('email_new_file_by_client_subject_customize') == 1 && !empty(get_option('email_new_file_by_client_subject'))) ? get_option('email_new_file_by_client_subject') : __('New files uploaded by clients', 'cftp_admin'),
                    'body' => __('New files has been uploaded by the following clients', 'cftp_admin'),
                    'body2' => __("You can manage these files", 'cftp_admin'),
                    'body3' => __('by logging in here', 'cftp_admin')
                );
                $options = [
                    'title' => __('New file by client', 'cftp_admin'),
                    'checkboxes' => [
                        'email_new_file_by_client_customize',
                        'email_new_file_by_client_subject_customize',
                    ],
                    'items' => [
                        'subject_checkbox' => 'email_new_file_by_client_subject_customize',
                        'subject' => 'email_new_file_by_client_subject',
                        'body_checkbox' => 'email_new_file_by_client_customize',
                        'body_textarea' => 'email_new_file_by_client_text',
                        'description' => __('This email will be sent to the system administrator whenever a client uploads a new file.', 'cftp_admin'),
                        'subject_check' => get_option('email_new_file_by_client_subject_customize'),
                        'subject_text' => get_option('email_new_file_by_client_subject'),
                        'body_check' => get_option('email_new_file_by_client_customize'),
                        'body_text' => get_option('email_new_file_by_client_text'),
                        'default_text' => EMAIL_TEMPLATE_NEW_FILE_BY_CLIENT,
                        'tags' => [
                            '%FILES%' => __('Shows the list of files', 'cftp_admin'),
                            '%URI%' => $uri_note,
                        ],
                    ],
                ];
                break;
            case 'new_client':
                // Strings for the "New client created" e-mail
                $strings = array(
                    'subject' => (get_option('email_new_client_by_user_subject_customize') == 1 && !empty(get_option('email_new_client_by_user_subject'))) ? get_option('email_new_client_by_user_subject') : __('Welcome to ProjectSend', 'cftp_admin'),
                    'body' => __('A new account was created for you. From now on, you can access the files that have been uploaded under your account using the following credentials:', 'cftp_admin'),
                    'body2' => __('You can log in following this link', 'cftp_admin'),
                    'body3' => __('Please contact the administrator if you need further assistance.', 'cftp_admin'),
                    'label_user' => __('Your username', 'cftp_admin'),
                    'label_pass' => __('Your password', 'cftp_admin')
                );
                $options = [
                    'title' => __('New client (welcome)', 'cftp_admin'),
                    'checkboxes' => [
                        'email_new_client_by_user_customize',
                        'email_new_client_by_user_subject_customize',
                    ],
                    'items' => [
                        'subject_checkbox' => 'email_new_client_by_user_subject_customize',
                        'subject' => 'email_new_client_by_user_subject',
                        'body_checkbox' => 'email_new_client_by_user_customize',
                        'body_textarea' => 'email_new_client_by_user_text',
                        'description' => __('This email will be sent to the new client after an administrator has created their new account. It would be best to include the log in details (username and password).', 'cftp_admin'),
                        'subject_check' => get_option('email_new_client_by_user_subject_customize'),
                        'subject_text' => get_option('email_new_client_by_user_subject'),
                        'body_check' => get_option('email_new_client_by_user_customize'),
                        'body_text' => get_option('email_new_client_by_user_text'),
                        'default_text' => EMAIL_TEMPLATE_NEW_CLIENT,
                        'tags' => [
                            '%USERNAME%' => __('The new username for this account', 'cftp_admin'),
                            '%PASSWORD%' => __('The new password for this account', 'cftp_admin'),
                            '%URI%' => $uri_note,
                        ],
                    ],
                ];
                break;
            case  'new_client_self':
                // Strings for the "New client" e-mail to the admin on self registration.
                $strings = array(
                    'subject' => (get_option('email_new_client_by_self_subject_customize') == 1 && !empty(get_option('email_new_client_by_self_subject'))) ? get_option('email_new_client_by_self_subject') : __('A new client has registered', 'cftp_admin'),
                    'body' => __('A new account was created using the self registration form on your site. Registration information:', 'cftp_admin'),
                    'label_name' => __('Full name', 'cftp_admin'),
                    'label_user' => __('Username', 'cftp_admin'),
                    'label_request' => __('Additionally, the client requests access to the following group(s)', 'cftp_admin'),
                    'body2' => __('Auto-approvals of new accounts are currently enabled.', 'cftp_admin'),
                    'body3' => __('You can log in to manually deactivate it.', 'cftp_admin'),
                );
                if (get_option('clients_auto_approve') == '0') {
                    $strings['body2'] = __('Please log in to process the request.', 'cftp_admin');
                    $strings['body3'] = __('Remember, your new client will not be able to log in until an administrator has approved their account.', 'cftp_admin');
                }
                $options = [
                    'title' => __('New client (self-registered)', 'cftp_admin'),
                    'checkboxes' => [
                        'email_new_client_by_self_customize',
                        'email_new_client_by_self_subject_customize',
                    ],
                    'items' => [
                        'subject_checkbox' => 'email_new_client_by_self_subject_customize',
                        'subject' => 'email_new_client_by_self_subject',
                        'body_checkbox' => 'email_new_client_by_self_customize',
                        'body_textarea' => 'email_new_client_by_self_text',
                        'description' => __('This email will be sent to the system administrator after a new client has created a new account for himself.', 'cftp_admin'),
                        'subject_check' => get_option('email_new_client_by_self_subject_customize'),
                        'subject_text' => get_option('email_new_client_by_self_subject'),
                        'body_check' => get_option('email_new_client_by_self_customize'),
                        'body_text' => get_option('email_new_client_by_self_text'),
                        'default_text' => EMAIL_TEMPLATE_NEW_CLIENT_SELF,
                        'tags' => [
                            '%FULLNAME%' => __('The full name the client registered with', 'cftp_admin'),
                            '%USERNAME%' => __('The new username for this account', 'cftp_admin'),
                            '%URI%' => $uri_note,
                            '%GROUPS_REQUESTS%' => __('List of groups that the client requests membership to', 'cftp_admin'),
                        ],
                    ],
                ];
                break;
            case 'account_approve':
                // Strings for the "Account approved" e-mail
                $strings = array(
                    'subject' => (get_option('email_account_approve_subject_customize') == 1 && !empty(get_option('email_account_approve_subject'))) ? get_option('email_account_approve_subject') : __('You account has been approved', 'cftp_admin'),
                    'body' => __('Your account has been approved.', 'cftp_admin'),
                    'title_memberships' => __('Additionally, your group membership requests have been processed.', 'cftp_admin'),
                    'title_approved' => __('Approved requests:', 'cftp_admin'),
                    'title_denied' => __('Denied requests:', 'cftp_admin'),
                    'body2' => __('You can log in following this link', 'cftp_admin'),
                    'body3' => __('Please contact the administrator if you need further assistance.', 'cftp_admin')
                );
                $options = [
                    'title' => __('Approve client account', 'cftp_admin'),
                    'checkboxes' => [
                        'email_account_approve_subject_customize',
                        'email_account_approve_customize',
                    ],
                    'items' => [
                        'subject_checkbox' => 'email_account_approve_subject_customize',
                        'subject' => 'email_account_approve_subject',
                        'body_checkbox' => 'email_account_approve_customize',
                        'body_textarea' => 'email_account_approve_text',
                        'description' => __('This email will be sent to a client that requested an account if it gets approved. Group membership requests are also mentioned on the email, separated by their approval status.', 'cftp_admin'),
                        'subject_check' => get_option('email_account_approve_subject_customize'),
                        'subject_text' => get_option('email_account_approve_subject'),
                        'body_check' => get_option('email_account_approve_customize'),
                        'body_text' => get_option('email_account_approve_text'),
                        'default_text' => EMAIL_TEMPLATE_ACCOUNT_APPROVE,
                        'tags' => [
                            '%GROUPS_APPROVED%' => __('List of approved group memberships', 'cftp_admin'),
                            '%GROUPS_DENIED%' => __('List of denied group memberships', 'cftp_admin'),
                            '%URI%' => $uri_note,
                        ],
                    ],
                ];
                break;
            case 'account_deny':
                // Strings for the "Account denied" e-mail
                $strings = array(
                    'subject' => (get_option('email_account_deny_subject_customize') == 1 && !empty(get_option('email_account_deny_subject'))) ? get_option('email_account_deny_subject') : __('You account has been denied', 'cftp_admin'),
                    'body' => __('Your account request has been denied.', 'cftp_admin'),
                    'body2' => __('Please contact the administrator if you need further assistance.', 'cftp_admin')
                );
                $options = [
                    'title' => __('Deny client account', 'cftp_admin'),
                    'checkboxes' => [
                        'email_account_deny_subject_customize',
                        'email_account_deny_customize',
                    ],
                    'items' => [
                        'subject_checkbox' => 'email_account_deny_subject_customize',
                        'subject' => 'email_account_deny_subject',
                        'body_checkbox' => 'email_account_deny_customize',
                        'body_textarea' => 'email_account_deny_text',
                        'description' => __('This email will be sent to a client that requested an account if it gets denied. All group membership requests for this account are denied automatically.', 'cftp_admin'),
                        'subject_check' => get_option('email_account_deny_subject_customize'),
                        'subject_text' => get_option('email_account_deny_subject'),
                        'body_check' => get_option('email_account_deny_customize'),
                        'body_text' => get_option('email_account_deny_text'),
                        'default_text' => EMAIL_TEMPLATE_ACCOUNT_DENY,
                        'tags' => [],
                    ],
                ];
                break;
            case 'new_user':
                // Strings for the "New system user created" e-mail
                $strings = array(
                    'subject' => (get_option('email_new_user_subject_customize') == 1 && !empty(get_option('email_new_user_subject'))) ? get_option('email_new_user_subject') : __('Welcome to ProjectSend', 'cftp_admin'),
                    'body' => __('A new account was created for you. From now on, you can access the system administrator using the following credentials:', 'cftp_admin'),
                    'body2' => __('Access the system panel here', 'cftp_admin'),
                    'body3' => __('Thank you for using ProjectSend.', 'cftp_admin'),
                    'label_user' => __('Your username', 'cftp_admin'),
                    'label_pass' => __('Your password', 'cftp_admin')
                );
                $options = [
                    'title' => __('New user (welcome)', 'cftp_admin'),
                    'checkboxes' => [
                        'email_new_user_customize',
                        'email_new_user_subject_customize',
                    ],
                    'items' => [
                        'subject_checkbox'    => 'email_new_user_subject_customize',
                        'subject' => 'email_new_user_subject',
                        'body_checkbox' => 'email_new_user_customize',
                        'body_textarea' => 'email_new_user_text',
                        'description' => __('This email will be sent to the new system user after an administrator has created their new account. It would be best to include the log in details (username and password).', 'cftp_admin'),
                        'subject_check' => get_option('email_new_user_subject_customize'),
                        'subject_text' => get_option('email_new_user_subject'),
                        'body_check' => get_option('email_new_user_customize'),
                        'body_text' => get_option('email_new_user_text'),
                        'default_text' => EMAIL_TEMPLATE_NEW_USER,
                        'tags' => [
                            '%USERNAME%' => __('The new username for this account', 'cftp_admin'),
                            '%PASSWORD%' => __('The new password for this account', 'cftp_admin'),
                            '%URI%' => $uri_note,
                        ],
                    ],
                ];
                break;
            case 'password_reset':
                // Strings for the "Reset password" e-mail
                $strings = array(
                    'subject' => (get_option('email_pass_reset_subject_customize') == 1 && !empty(get_option('email_pass_reset_subject'))) ? get_option('email_pass_reset_subject') : __('Password reset instructions', 'cftp_admin'),
                    'body' => __('A request has been received to reset the password for the following account:', 'cftp_admin'),
                    'body2' => __('To continue, please visit the following link', 'cftp_admin'),
                    'body3' => __('The request is valid only for 24 hours.', 'cftp_admin'),
                    'body4' => __('If you did not make this request, simply ignore this email.', 'cftp_admin'),
                    'label_user' => __('Username', 'cftp_admin'),
                );
                $options = [
                    'title' => __('Password reset', 'cftp_admin'),
                    'checkboxes' => [
                        'email_pass_reset_customize',
                        'email_pass_reset_subject_customize',
                    ],
                    'items' => [
                        'subject_checkbox'    => 'email_pass_reset_subject_customize',
                        'subject' => 'email_pass_reset_subject',
                        'body_checkbox' => 'email_pass_reset_customize',
                        'body_textarea' => 'email_pass_reset_text',
                        'description' => __('This email will be sent to a user or client when they try to reset their password.', 'cftp_admin'),
                        'subject_check' => get_option('email_pass_reset_subject_customize'),
                        'subject_text' => get_option('email_pass_reset_subject'),
                        'body_check' => get_option('email_pass_reset_customize'),
                        'body_text' => get_option('email_pass_reset_text'),
                        'default_text' => EMAIL_TEMPLATE_PASSWORD_RESET,
                        'tags' => [
                            '%USERNAME%' => __('The username for this account', 'cftp_admin'),
                            '%TOKEN%' => __('The text string unique to this request. Must be included somewhere.', 'cftp_admin'),
                            '%URI%' => __('The link to continue the process (to be used as href on a link tag)', 'cftp_admin'),
                        ],
                    ],
                ];
                break;
            case 'client_edited':
                // Strings for the "Review client group requests" e-mail to the admin
                $strings = array(
                    'subject' => (get_option('email_client_edited_subject_customize') == 1 && !empty(get_option('email_client_edited_subject'))) ? get_option('email_client_edited_subject') : __('A client has changed memberships requests', 'cftp_admin'),
                    'body' => __('A client on your site has just changed their groups membership requests and needs your approval.', 'cftp_admin'),
                    'label_name' => __('Full name', 'cftp_admin'),
                    'label_user' => __('Username', 'cftp_admin'),
                    'label_request' => __('The client requests access to the following group(s)', 'cftp_admin'),
                    'body2' => __('Please log in to process the request.', 'cftp_admin')
                );
                $options = [
                    'title' => __('Client updated memberships', 'cftp_admin'),
                    'checkboxes' => [
                        'email_client_edited_subject_customize',
                        'email_client_edited_customize',
                    ],
                    'items' => [
                        'subject_checkbox'    => 'email_client_edited_subject_customize',
                        'subject' => 'email_client_edited_subject',
                        'body_checkbox' => 'email_client_edited_customize',
                        'body_textarea' => 'email_client_edited_text',
                        'description' => __('This email will be sent to the system administrator when a client edits their account and changes the public groups membership requests.', 'cftp_admin'),
                        'subject_check' => get_option('email_client_edited_subject_customize'),
                        'subject_text' => get_option('email_client_edited_subject'),
                        'body_check' => get_option('email_client_edited_customize'),
                        'body_text' => get_option('email_client_edited_text'),
                        'default_text' => EMAIL_TEMPLATE_CLIENT_EDITED,
                        'tags' => [
                            '%FULLNAME%' => __('The full name the client registered with', 'cftp_admin'),
                            '%USERNAME%' => __('The new username for this account', 'cftp_admin'),
                            '%URI%' => $uri_note,
                            '%GROUPS_REQUESTS%' => __('List of groups that the client requests membership to', 'cftp_admin'),
                        ],
                    ],
                ];
                break;
            case '2fa_code':
                // Send 2FA (OTP) code
                $strings = [
                    'subject' => (get_option('email_2fa_code_subject_customize') == 1 && !empty(get_option('email_2fa_code_subject'))) ? get_option('email_2fa_code_subject') : __('Your login verification code', 'cftp_admin'),
                    'body' => __('Someone has attempted to log in to your account.', 'cftp_admin'),
                    'body2' => __('To continue the process, you need to enter the following code:', 'cftp_admin'),
                    // 'label_location' => __('Location','cftp_admin'),
                    // 'label_device' => __('Device','cftp_admin'),
                    // 'label_browser' => __('Browser','cftp_admin'),
                    'label_expiry' => __('This code will expire on:', 'cftp_admin'),
                    'body3' => __('If you did not initiate the log in process, please change your password as soon as possible.', 'cftp_admin'),
                ];
                $options = [
                    'title' => __('Login authorization code', 'cftp_admin'),
                    'checkboxes' => [
                        'email_2fa_code_subject_customize',
                        'email_2fa_code_customize',
                    ],
                    'items' => [
                        'subject_checkbox' => 'email_2fa_code_subject_customize',
                        'subject' => 'email_2fa_code_subject',
                        'body_checkbox' => 'email_2fa_code_customize',
                        'body_textarea' => 'email_2fa_code_text',
                        'description' => __('If the corresponding option is enabled, this email with a one-time security code will be sent to a user when they attempt to log in.', 'cftp_admin'),
                        'subject_check' => get_option('email_2fa_code_subject_customize'),
                        'subject_text' => get_option('email_2fa_code_subject'),
                        'body_check' => get_option('email_2fa_code_customize'),
                        'body_text' => get_option('email_2fa_code_text'),
                        'default_text' => EMAIL_TEMPLATE_2FA_CODE,
                        'tags' => [
                            '%CODE%' => __('The 6-digits security code', 'cftp_admin'),
                            '%LOCATION%' => __('Geographical location from where the user logged in', 'cftp_admin'),
                            '%DEVICE%' => __('Device type', 'cftp_admin'),
                            '%BROWSER%' => __('Browser used to log in', 'cftp_admin'),
                            '%EXPIRY_DATE%' => __('Date and time when the code expires', 'cftp_admin'),
                        ],
                    ],
                ];
                break;
        }

        return [
            'strings' => $strings,
            'options' => $options,
        ];
    }

    public function getDataForOptions($email_type)
    {
        $options = $this->getEmailTypeData($email_type);

        return $options['options'];
    }

    /**
     * The body of the e-mails is gotten from the html templates
     * found on the /emails folder.
     */
    private function prepareBody($type)
    {
        switch ($type) {
            case 'new_client':
                $filename = EMAIL_TEMPLATE_NEW_CLIENT;
                $customize_body = get_option('email_new_client_by_user_customize');
                $body_text_option = 'email_new_client_by_user_text';
                break;
            case 'new_client_self':
                $filename = EMAIL_TEMPLATE_NEW_CLIENT_SELF;
                $customize_body = get_option('email_new_client_by_self_customize');
                $body_text_option = 'email_new_client_by_self_text';
                break;
            case 'account_approve':
                $filename = EMAIL_TEMPLATE_ACCOUNT_APPROVE;
                $customize_body = get_option('email_account_approve_customize');
                $body_text_option = 'email_account_approve_text';
                break;
            case 'account_deny':
                $filename = EMAIL_TEMPLATE_ACCOUNT_DENY;
                $customize_body = get_option('email_account_deny_customize');
                $body_text_option = 'email_account_deny_text';
                break;
            case 'new_user':
                $filename = EMAIL_TEMPLATE_NEW_USER;
                $customize_body = get_option('email_new_user_customize');
                $body_text_option = 'email_new_user_text';
                break;
            case 'new_files_by_user':
                $filename = EMAIL_TEMPLATE_NEW_FILE_BY_USER;
                $customize_body = get_option('email_new_file_by_user_customize');
                $body_text_option = 'email_new_file_by_user_text';
                break;
            case 'new_files_by_client':
                $filename = EMAIL_TEMPLATE_NEW_FILE_BY_CLIENT;
                $customize_body = get_option('email_new_file_by_client_customize');
                $body_text_option = 'email_new_file_by_client_text';
                break;
            case 'password_reset':
                $filename = EMAIL_TEMPLATE_PASSWORD_RESET;
                $customize_body = get_option('email_pass_reset_customize');
                $body_text_option = 'email_pass_reset_text';
                break;
            case 'client_edited':
                $filename = EMAIL_TEMPLATE_CLIENT_EDITED;
                $customize_body = get_option('email_client_edited_customize');
                $body_text_option = 'email_client_edited_text';
                break;
            case '2fa_code':
                $filename = EMAIL_TEMPLATE_2FA_CODE;
                $customize_body = get_option('email_2fa_code_customize');
                $body_text_option = 'email_2fa_code_text';
                break;
            case 'test_settings':
                $filename = 'test_settings.html';
                $customize_body = 0;
                $body_text_option = null;
                break;
            case 'generic':
                $filename = 'generic.html';
                $customize_body = 0;
                $body_text_option = null;
                break;
        }

        // Content
        $content = ($customize_body == '1' && !empty(get_option($body_text_option))) ? get_option($body_text_option) : file_get_contents(EMAIL_TEMPLATES_DIR . DS . $filename);

        // Full body
        $body = $this->header . $content . $this->footer;

        return $body;
    }

    /**
     * Prepare the body for the "New Client" e-mail.
     * The new username and password are also sent.
     */
    private function email_new_client($username, $password)
    {
        $email_data = $this->getEmailTypeData('new_client');
        $strings = $email_data['strings'];
        $this->email_body = $this->prepareBody('new_client');
        $this->email_body = str_replace(
            array('%SUBJECT%', '%BODY1%', '%BODY2%', '%BODY3%', '%LBLUSER%', '%LBLPASS%', '%USERNAME%', '%PASSWORD%', '%URI%'),
            array(
                $strings['subject'],
                $strings['body'],
                $strings['body2'],
                $strings['body3'],
                $strings['label_user'],
                $strings['label_pass'],
                $username,
                $password,
                BASE_URI
            ),
            $this->email_body
        );
        return array(
            'subject' => $strings['subject'],
            'body' => $this->email_body
        );
    }

    /**
     * Prepare the body for the "New Client" self registration e-mail.
     * The name of the client and username are also sent.
     */
    private function email_new_client_self($username, $fullname, $memberships_requests)
    {
        $email_data = $this->getEmailTypeData('new_client_self');
        $strings = $email_data['strings'];
        $this->email_body = $this->prepareBody('new_client_self');
        $this->email_body = str_replace(
            array('%SUBJECT%', '%BODY1%', '%BODY2%', '%BODY3%', '%LBLNAME%', '%LBLUSER%', '%FULLNAME%', '%USERNAME%', '%URI%'),
            array(
                $strings['subject'],
                $strings['body'],
                $strings['body2'],
                $strings['body3'],
                $strings['label_name'],
                $strings['label_user'],
                $fullname,
                $username,
                BASE_URI
            ),
            $this->email_body
        );
        if (!empty($memberships_requests)) {
            $this->get_groups = get_groups([
                'group_ids' => $memberships_requests
            ]);

            $this->groups_list = '<ul>';
            foreach ($this->get_groups as $group) {
                $this->groups_list .= '<li>' . $group['name'] . '</li>';
            }
            $this->groups_list .= '</ul>';

            $memberships_requests = implode(',', $memberships_requests);
            $this->email_body = str_replace(
                array('%LABEL_REQUESTS%', '%GROUPS_REQUESTS%'),
                array(
                    $strings['label_request'],
                    $this->groups_list
                ),
                $this->email_body
            );
        } else {
            $this->email_body = str_replace(
                array('%LABEL_REQUESTS%', '%GROUPS_REQUESTS%'),
                array(
                    __('No group requests made', 'cftp_admin'),
                    null,
                ),
                $this->email_body
            );
        }
        return array(
            'subject' => $strings['subject'],
            'body' => $this->email_body
        );
    }

    /**
     * Prepare the body for the "Account approved" e-mail.
     * Also sends the memberships requests approval status.
     */
    private function email_account_approve($username, $name, $memberships_requests)
    {
        $email_data = $this->getEmailTypeData('account_approve');
        $strings = $email_data['strings'];
        $requests_title_replace = false;

        $this->get_groups = get_groups([]);

        if (!empty($memberships_requests['approved'])) {
            $requests_title_replace = true;
            $approved_title = '<p>' . $strings['title_approved'] . '</p>';
            // Make the list
            $approved_list = '<ul>';
            foreach ($memberships_requests['approved'] as $group_id) {
                $approved_list .= '<li style="list-style:disc;">' . $this->get_groups[$group_id]['name'] . '</li>';
            }
            $approved_list .= '</ul><hr>';
        } else {
            $approved_list =  '';
            $approved_title = '';
        }
        if (!empty($memberships_requests['denied'])) {
            $requests_title_replace = true;
            $denied_title = '<p>' . $strings['title_denied'] . '</p>';
            // Make the list
            $denied_list = '<ul>';
            foreach ($memberships_requests['denied'] as $group_id) {
                $denied_list .= '<li style="list-style:disc;">' . $this->get_groups[$group_id]['name'] . '</li>';
            }
            $denied_list .= '</ul><hr>';
        } else {
            $denied_list =  '';
            $denied_title = '';
        }

        $requests_title = ($requests_title_replace == true) ? '<p>' . $strings['title_approved'] . '</p>' : '';

        $this->email_body = $this->prepareBody('account_approve');
        $this->email_body = str_replace(
            array('%SUBJECT%', '%BODY1%', '%REQUESTS_TITLE%', '%APPROVED_TITLE%', '%GROUPS_APPROVED%', '%DENIED_TITLE%', '%GROUPS_DENIED%', '%BODY2%', '%BODY3%', '%URI%'),
            array(
                $strings['subject'],
                $strings['body'],
                '<p>' . $strings['title_memberships'] . '</p>',
                $approved_title,
                $approved_list,
                $denied_title,
                $denied_list,
                $strings['body2'],
                $strings['body3'],
                BASE_URI
            ),
            $this->email_body
        );
        return array(
            'subject' => $strings['subject'],
            'body' => $this->email_body
        );
    }

    /**
     * Prepare the body for the "Account denied" e-mail.
     */
    private function email_account_deny($username, $name)
    {
        $email_data = $this->getEmailTypeData('account_deny');
        $strings = $email_data['strings'];
        $this->email_body = $this->prepareBody('account_deny');
        $this->email_body = str_replace(
            array('%SUBJECT%', '%BODY1%', '%BODY2%'),
            array(
                $strings['subject'],
                $strings['body'],
                $strings['body2'],
            ),
            $this->email_body
        );
        return array(
            'subject' => $strings['subject'],
            'body' => $this->email_body
        );
    }

    /**
     * Prepare the body for the "New User" e-mail.
     * The new username and password are also sent.
     */
    private function email_new_user($username, $password)
    {
        $email_data = $this->getEmailTypeData('new_user');
        $strings = $email_data['strings'];
        $this->email_body = $this->prepareBody('new_user');
        $this->email_body = str_replace(
            array('%SUBJECT%', '%BODY1%', '%BODY2%', '%BODY3%', '%LBLUSER%', '%LBLPASS%', '%USERNAME%', '%PASSWORD%', '%URI%'),
            array(
                $strings['subject'],
                $strings['body'],
                $strings['body2'],
                $strings['body3'],
                $strings['label_user'],
                $strings['label_pass'],
                $username,
                $password,
                BASE_URI
            ),
            $this->email_body
        );
        return array(
            'subject' => $strings['subject'],
            'body' => $this->email_body
        );
    }

    /**
     * Prepare the body for the "New files for client" e-mail and replace the
     * tags with the strings values set at the top of this file and the
     * link to the log in page.
     */
    private function email_new_files_by_user($files_list)
    {
        $email_data = $this->getEmailTypeData('new_files_by_user');
        $strings = $email_data['strings'];
        $this->email_body = $this->prepareBody('new_files_by_user');
        $this->email_body = str_replace(
            array('%SUBJECT%', '%BODY1%', '%FILES%', '%BODY2%', '%BODY3%', '%BODY4%', '%URI%'),
            array(
                $strings['subject'],
                $strings['body'],
                $files_list,
                $strings['body2'],
                $strings['body3'],
                $strings['body4'],
                BASE_URI
            ),
            $this->email_body
        );
        return array(
            'subject' => $strings['subject'],
            'body' => $this->email_body
        );
    }

    /**
     * Prepare the body for the "New files by client" e-mail and replace the
     * tags with the strings values set at the top of this file and the
     * link to the log in page.
     */
    private function email_new_files_by_client($files_list)
    {
        $email_data = $this->getEmailTypeData('new_files_by_client');
        $strings = $email_data['strings'];
        $this->email_body = $this->prepareBody('new_files_by_client');
        $this->email_body = str_replace(
            array('%SUBJECT%', '%BODY1%', '%FILES%', '%BODY2%', '%BODY3%', '%URI%'),
            array(
                $strings['subject'],
                $strings['body'],
                $files_list,
                $strings['body2'],
                $strings['body3'],
                BASE_URI
            ),
            $this->email_body
        );
        return array(
            'subject' => $strings['subject'],
            'body' => $this->email_body
        );
    }

    /**
     * Prepare the body for the "Password reset" e-mail and replace the
     * tags with the strings values set at the top of this file and the
     * link to the log in page.
     */
    private function email_password_reset($username, $token)
    {
        $email_data = $this->getEmailTypeData('password_reset');
        $strings = $email_data['strings'];
        $this->email_body = $this->prepareBody('password_reset');
        $this->email_body = str_replace(
            array('%SUBJECT%', '%BODY1%', '%BODY2%', '%BODY3%', '%BODY4%', '%LBLUSER%', '%USERNAME%', '%URI%'),
            array(
                $strings['subject'],
                $strings['body'],
                $strings['body2'],
                $strings['body3'],
                $strings['body4'],
                $strings['label_user'],
                $username,
                BASE_URI . 'reset-password.php?token=' . $token . '&user=' . $username,
            ),
            $this->email_body
        );
        return array(
            'subject' => $strings['subject'],
            'body' => $this->email_body
        );
    }

    /**
     * Prepare the body for the e-mail sent when a client changes group
     *  membership requests.
     */
    private function email_client_edited($username, $fullname, $memberships_requests)
    {
        $email_data = $this->getEmailTypeData('client_edited');
        $strings = $email_data['strings'];
        $this->email_body = $this->prepareBody('client_edited');
        $this->email_body = str_replace(
            array('%SUBJECT%', '%BODY1%', '%BODY2%', '%LBLNAME%', '%LBLUSER%', '%FULLNAME%', '%USERNAME%', '%URI%'),
            array(
                $strings['subject'],
                $strings['body'],
                $strings['body2'],
                $strings['label_name'],
                $strings['label_user'],
                $fullname, $username, BASE_URI
            ),
            $this->email_body
        );
        if (!empty($memberships_requests)) {
            $this->get_groups = get_groups([
                'group_ids' => $memberships_requests
            ]);

            $this->groups_list = '<ul>';
            foreach ($this->get_groups as $group) {
                $this->groups_list .= '<li>' . $group['name'] . '</li>';
            }
            $this->groups_list .= '</ul>';

            $memberships_requests = implode(',', $memberships_requests);
            $this->email_body = str_replace(
                array('%LABEL_REQUESTS%', '%GROUPS_REQUESTS%'),
                array(
                    $strings['label_request'],
                    $this->groups_list
                ),
                $this->email_body
            );
        } else {
            $this->email_body = str_replace(
                array('%LABEL_REQUESTS%', '%GROUPS_REQUESTS%'),
                array(
                    __('No group requests made', 'cftp_admin'),
                    null,
                ),
                $this->email_body
            );
        }
        return array(
            'subject' => $strings['subject'],
            'body' => $this->email_body
        );
    }

    /**
     * Prepare the body for the authentication code sent to the user during log in.
     */
    // private function email_2fa_code($code,$location,$device,$browser,$expiry_date)
    private function email_2fa_code($code, $expiry_date)
    {
        $email_data = $this->getEmailTypeData('2fa_code');
        $strings = $email_data['strings'];
        $this->email_body = $this->prepareBody('2fa_code');
        $this->email_body = str_replace(
            // array('%SUBJECT%','%BODY1%','%BODY2%','%BODY3%', '%CODE%', '%LBLLOCAITON%','%LOCATION%','%LBLDEVICE%','%DEVICE%','%LBLBROWSER%', '%BROWSER%', '%LABEL_EXPIRY%', '%EXPIRY_DATE%', ),
            array('%SUBJECT%', '%BODY1%', '%BODY2%', '%BODY3%', '%CODE%', '%LABEL_EXPIRY%', '%EXPIRY_DATE%'),
            array(
                $strings['subject'],
                $strings['body'],
                $strings['body2'],
                $strings['body3'],
                $code,
                // $strings['label_location'],
                // $location,
                // $strings['label_device'],
                // $device,
                // $strings['label_browser'],
                // $browser,
                $strings['label_expiry'],
                $expiry_date,
            ),
            $this->email_body
        );
        return array(
            'subject' => $strings['subject'],
            'body' => $this->email_body
        );
    }

    /**
     * E-mail sent when testing email settings.
     */
    private function email_test_settings($message)
    {
        $subject = __('Email configuration test', 'cftp_admin');
        $this->email_body = $this->prepareBody('test_settings');
        $this->email_body = str_replace(
            array('%BODY%', '%SUBJECT%'),
            array(
                $message,
                $subject,
            ),
            $this->email_body
        );
        return array(
            'subject' => $subject,
            'body' => $this->email_body
        );
    }

    /**
     * Generic w-mail with custom content only
     */
    private function email_generic($subject, $message = null)
    {
        $this->email_body = $this->prepareBody('generic');
        $this->email_body = str_replace(
            array('%BODY%', '%SUBJECT%'),
            array(
                $message,
                $subject,
            ),
            $this->email_body
        );
        return array(
            'subject' => $subject,
            'body' => $this->email_body
        );
    }

    public function getDebugResult()
    {
        return $this->debug_result;
    }

    public function emailWasSuccessful()
    {
        if (!empty($this->email_successful) && $this->email_successful == true) {
            return true;
        }

        return false;
    }

    /**
     * Finally, try to send the e-mail and return a status, where
     * 1 = Message sent OK
     * 2 = Error sending the e-mail
     *
     * Returns custom values instead of a boolean value to allow more
     * codes in the future, on new validations and functions.
     */
    public function send($arguments)
    {
        /** Generate the values from the arguments */
        $this->preview = (!empty($arguments['preview'])) ? $arguments['preview'] : false;
        $this->type = $arguments['type'];
        $this->addresses = (!empty($arguments['address'])) ? $arguments['address'] : '';
        $this->username = (!empty($arguments['username'])) ? $arguments['username'] : '';
        $this->password = (!empty($arguments['password'])) ? $arguments['password'] : '';
        $this->client_id = (!empty($arguments['client_id'])) ? $arguments['client_id'] : '';
        $this->name = (!empty($arguments['name'])) ? $arguments['name'] : '';
        $this->files_list = (!empty($arguments['files_list'])) ? $arguments['files_list'] : '';
        $this->token = (!empty($arguments['token'])) ? $arguments['token'] : '';
        $this->memberships = (!empty($arguments['memberships'])) ? $arguments['memberships'] : '';

        $test_message = (!empty($arguments['message'])) ? filter_var($arguments['message'], FILTER_SANITIZE_STRING) : __('This is a test message', 'cftp_admin');
        $generic_message = (!empty($arguments['message'])) ? $arguments['message'] : null;

        $this->try_bcc = false;
        $this->email_successful = false;

        $debug = false;

        switch ($this->type) {
            case 'test_settings':
                $body_variables = [$test_message];
                $this->addresses = $arguments['to'];
                $debug = true;
                break;
            case 'generic':
                $subject = (!empty($arguments['subject'])) ? $arguments['subject'] : sprintf(__('Sent from %s', 'cftp_admin'), get_option('this_install_title'));
                $body_variables = [$subject, $generic_message];
                $this->addresses = $arguments['to'];
                break;
            case 'new_files_by_user':
                $body_variables = [$this->files_list,];
                if (get_option('mail_copy_user_upload') == '1') {
                    $this->try_bcc = true;
                }
                break;
            case 'new_files_by_client':
                $body_variables = [$this->files_list,];
                if (get_option('mail_copy_client_upload') == '1') {
                    $this->try_bcc = true;
                }
                break;
            case 'new_client':
                $body_variables = [$this->username, $this->password,];
                break;
            case 'new_client_self':
                $body_variables = [$this->username, $this->name, $this->memberships];
                break;
            case 'account_approve':
                $body_variables = [$this->username, $this->name, $this->memberships,];
                break;
            case 'account_deny':
                $body_variables = [$this->username, $this->name,];
                break;
            case 'new_user':
                $body_variables = [$this->username, $this->password,];
                break;
            case 'password_reset':
                $body_variables = [$this->username, $this->token,];
                break;
            case 'client_edited':
                $body_variables = [$this->username, $this->name, $this->memberships,];
                break;
            case '2fa_code':
                // $items = ['code', 'location', 'device', 'browser', 'expiry_date'];
                $items = ['code', 'expiry_date'];
                $body_variables = [];
                foreach ($items as $item) {
                    $body_variables[] = (!empty($arguments[$item])) ? $arguments[$item] : '';
                }
                break;
        }

        /** Generates the subject and body contents */
        $this->method = 'email_' . $this->type;
        $this->mail_info = call_user_func_array([$this, $this->method], $body_variables);

        /**
         * Replace the default info on the footer
         */
        $this->mail_info['body'] = str_replace(
            array(
                '%FOOTER_SYSTEM_URI%',
                '%FOOTER_URI%'
            ),
            array(
                SYSTEM_URI,
                BASE_URI
            ),
            $this->mail_info['body']
        );

        /**
         * If we are generating a preview, just return the html content
         */
        if ($this->preview == true) {
            return $this->mail_info['body'];
        } else {
            /**
             * phpMailer
             */
            $email = new PHPMailer();
            $email->SMTPDebug = 0;
            $email->CharSet = EMAIL_ENCODING;

            $email->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => (!empty(get_option('mail_ssl_verify_peer')) && get_option('mail_ssl_verify_peer') == 1) ? true : false,
                    'verify_peer_name' => (!empty(get_option('mail_ssl_verify_peer_name')) && get_option('mail_ssl_verify_peer_name') == 1) ? true : false,
                    'allow_self_signed' => (!empty(get_option('mail_ssl_allow_self_signed')) && get_option('mail_ssl_allow_self_signed') == 1) ? true : false
                )
            );

            if ($debug == true) {
                $email->SMTPDebug = 3;
                $_SESSION['email_debug_message'] = null;
                $email->Debugoutput = function ($str, $level) {
                    $_SESSION['email_debug_message'] .= $str . "\n";
                };
            }

            switch (get_option('mail_system_use')) {
                case 'smtp':
                    $email->IsSMTP();
                    $email->Host = get_option('mail_smtp_host');
                    $email->Port = get_option('mail_smtp_port');
                    $email->Username = get_option('mail_smtp_user');
                    $email->Password = get_option('mail_smtp_pass');

                    if (get_option('mail_smtp_auth') != 'none') {
                        $email->SMTPAuth = true;
                        $email->SMTPSecure = get_option('mail_smtp_auth');
                    } else {
                        $email->SMTPAuth = false;
                    }
                    break;
                case 'gmail':
                    $email->IsSMTP();
                    $email->SMTPAuth = true;
                    $email->SMTPSecure = "tls";
                    $email->Host = 'smtp.gmail.com';
                    $email->Port = 587;
                    $email->Username = get_option('mail_smtp_user');
                    $email->Password = get_option('mail_smtp_pass');
                    break;
                case 'sendmail':
                    $email->IsSendmail();
                    break;
            }

            $email->Subject = $this->mail_info['subject'];
            $email->MsgHTML($this->mail_info['body']);
            $email->AltBody = __('This email contains HTML formatting and cannot be displayed right now. Please use an HTML compatible reader.', 'cftp_admin');

            $email->SetFrom(get_option('admin_email_address'), get_option('mail_from_name'));
            $email->AddReplyTo(get_option('admin_email_address'), get_option('mail_from_name'));

            if (!empty($this->name)) {
                $email->AddAddress($this->addresses, $this->name);
            } else {
                $email->AddAddress($this->addresses);
            }

            /**
             * Check if BCC is enabled and get the list of
             * addresses to add, based on the email type.
             */
            if ($this->try_bcc === true) {
                $this->add_bcc_to = [];
                if (get_option('mail_copy_main_user') == '1') {
                    $this->add_bcc_to[] = get_option('admin_email_address');
                }
                $more_addresses = get_option('mail_copy_addresses');
                if (!empty($more_addresses)) {
                    $more_addresses = explode(',', $more_addresses);
                    foreach ($more_addresses as $this->add_bcc) {
                        $this->add_bcc_to[] = $this->add_bcc;
                    }
                }
                /**
                 * Add the BCCs with the compiled array.
                 * First, clean the array to make sure the admin
                 * address is not written twice.
                 */
                if (!empty($this->add_bcc_to)) {
                    $this->add_bcc_to = array_unique($this->add_bcc_to);
                    foreach ($this->add_bcc_to as $this->set_bcc) {
                        $email->AddBCC($this->set_bcc);
                    }
                }
            }

            // Finally, send the e-mail.
            $send = $email->Send();
            $this->debug_result = (!empty($_SESSION['email_debug_message'])) ? $_SESSION['email_debug_message'] : null;
            unset($_SESSION['email_debug_message']);

            $this->email_successful = $send;
            return $send;
        }
    }
}
