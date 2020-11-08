<?php
/**
 * Class that handles all the e-mails that the system can send.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

namespace ProjectSend\Classes;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Emails
{
    private $header;
    private $footer;
    private $strings_file_by_user;
    private $strings_file_by_client;
    private $strings_new_client;
    private $strings_new_client_self;
    private $strings_account_approved;
    private $strings_account_denied;
    private $strings_new_user;
    private $strings_pass_reset;
    private $strings_client_edited;
    private $email_successful;

    function __construct()
    {
		/** Define the messages texts */
		$this->header = file_get_contents(EMAIL_TEMPLATES_DIR . DS . EMAIL_TEMPLATE_HEADER);
        $this->footer = file_get_contents(EMAIL_TEMPLATES_DIR . DS . EMAIL_TEMPLATE_FOOTER);
        
		/** Strings for the "New file uploaded" BY A SYSTEM USER e-mail */
		$this->strings_file_by_user = array(
			'subject' => (get_option('email_new_file_by_user_subject_customize') == 1 && !empty(get_option('email_new_file_by_user_subject'))) ? get_option('email_new_file_by_user_subject') : __('New files uploaded for you','cftp_admin'),
			'body' => __('The following files are now available for you to download.','cftp_admin'),
			'body2' => __("If you prefer not to be notified about new files, please go to My Account and deactivate the notifications checkbox.",'cftp_admin'),
			'body3' => __('You can access a list of all your files or upload your own','cftp_admin'),
			'body4' => __('by logging in here','cftp_admin')
		);

		/** Strings for the "New file uploaded" BY A CLIENT e-mail */
		$this->strings_file_by_client = array(
			'subject' => (get_option('email_new_file_by_client_subject_customize') == 1 && !empty(get_option('email_new_file_by_client_subject'))) ? get_option('email_new_file_by_client_subject') : __('New files uploaded by clients','cftp_admin'),
			'body' => __('New files has been uploaded by the following clients','cftp_admin'),
			'body2' => __("You can manage these files",'cftp_admin'),
			'body3' => __('by logging in here','cftp_admin')
		);

		/** Strings for the "New client created" e-mail */
		$this->strings_new_client = array(
			'subject' => (get_option('email_new_client_by_user_subject_customize') == 1 && !empty(get_option('email_new_client_by_user_subject'))) ? get_option('email_new_client_by_user_subject') : __('Welcome to ProjectSend','cftp_admin'),
			'body' => __('A new account was created for you. From now on, you can access the files that have been uploaded under your account using the following credentials:','cftp_admin'),
			'body2' => __('You can log in following this link','cftp_admin'),
			'body3' => __('Please contact the administrator if you need further assistance.','cftp_admin'),
			'label_user' => __('Your username','cftp_admin'),
			'label_pass' => __('Your password','cftp_admin')
		);

		/**
		* Strings for the "New client" e-mail to the admin
		* on self registration.
		*/
		$this->strings_new_client_self = array(
			'subject' => (get_option('email_new_client_by_self_subject_customize') == 1 && !empty(get_option('email_new_client_by_self_subject'))) ? get_option('email_new_client_by_self_subject') : __('A new client has registered','cftp_admin'),
			'body' => __('A new account was created using the self registration form on your site. Registration information:','cftp_admin'),
			'label_name' => __('Full name','cftp_admin'),
			'label_user' => __('Username','cftp_admin'),
			'label_request' => __('Additionally, the client requests access to the following group(s)','cftp_admin')
		);
		if ( get_option('clients_auto_approve') == '0') {
			$this->strings_new_client_self['body2'] = __('Please log in to process the request.','cftp_admin');
			$this->strings_new_client_self['body3'] = __('Remember, your new client will not be able to log in until an administrator has approved their account.','cftp_admin');
		}
		else {
			$this->strings_new_client_self['body2'] = __('Auto-approvals of new accounts are currently enabled.','cftp_admin');
			$this->strings_new_client_self['body3'] = __('You can log in to manually deactivate it.','cftp_admin');
		}


		/** Strings for the "Account approved" e-mail */
		$this->strings_account_approved = array(
            'subject' => (get_option('email_account_approve_subject_customize') == 1 && !empty(get_option('email_account_approve_subject'))) ? get_option('email_account_approve_subject') : __('You account has been approved','cftp_admin'),
			'body' => __('Your account has been approved.','cftp_admin'),
			'title_memberships' => __('Additionally, your group membership requests have been processed.','cftp_admin'),
			'title_approved' => __('Approved requests:','cftp_admin'),
			'title_denied' => __('Denied requests:','cftp_admin'),
			'body2' => __('You can log in following this link','cftp_admin'),
			'body3' => __('Please contact the administrator if you need further assistance.','cftp_admin')
		);

		/** Strings for the "Account denied" e-mail */
		$this->strings_account_denied = array(
            'subject' => (get_option('email_account_deny_subject_customize') == 1 && !empty(get_option('email_account_deny_subject'))) ? get_option('email_account_deny_subject') : __('You account has been denied','cftp_admin'),
			'body' => __('Your account request has been denied.','cftp_admin'),
			'body2' => __('Please contact the administrator if you need further assistance.','cftp_admin')
		);

		/** Strings for the "New system user created" e-mail */
		$this->strings_new_user = array(
            'subject' => (get_option('email_new_user_subject_customize') == 1 && !empty(get_option('email_new_user_subject'))) ? get_option('email_new_user_subject') : __('Welcome to ProjectSend','cftp_admin'),
			'body' => __('A new account was created for you. From now on, you can access the system administrator using the following credentials:','cftp_admin'),
			'body2' => __('Access the system panel here','cftp_admin'),
			'body3' => __('Thank you for using ProjectSend.','cftp_admin'),
			'label_user' => __('Your username','cftp_admin'),
			'label_pass' => __('Your password','cftp_admin')
		);


		/** Strings for the "Reset password" e-mail */
		$this->strings_pass_reset = array(
            'subject' => (get_option('email_pass_reset_subject_customize') == 1 && !empty(get_option('email_pass_reset_subject'))) ? get_option('email_pass_reset_subject') : __('Password reset instructions','cftp_admin'),
			'body' => __('A request has been received to reset the password for the following account:','cftp_admin'),
			'body2' => __('To continue, please visit the following link','cftp_admin'),
			'body3' => __('The request is valid only for 24 hours.','cftp_admin'),
			'body4' => __('If you did not make this request, simply ignore this email.','cftp_admin'),
			'label_user' => __('Username','cftp_admin'),
		);

		/**
		* Strings for the "Review client group requests" e-mail to the admin
		*/
		$this->strings_client_edited = array(
            'subject' => (get_option('email_client_edited_subject_customize') == 1 && !empty(get_option('email_client_edited_subject'))) ? get_option('email_client_edited_subject') : __('A client has changed memberships requests','cftp_admin'),
			'body' => __('A client on you site has just changed his groups membership requests and needs your approval.','cftp_admin'),
			'label_name' => __('Full name','cftp_admin'),
			'label_user' => __('Username','cftp_admin'),
			'label_request' => __('The client requests access to the following group(s)','cftp_admin'),
			'body2' => __('Please log in to process the request.','cftp_admin')
		);
    }

	/**
	 * The body of the e-mails is gotten from the html templates
	 * found on the /emails folder.
	 */
	private function email_prepare_body($type)
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
			case 'new_file_by_user':
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
            case 'test_settings':
                    $filename = 'test_settings.html';
                    $customize_body = 0;
                    $body_text_option	= null;
                break;
		}

		// Header
		$header = (get_option('email_header_footer_customize') == '1') ? get_option('email_header_text') : $this->header;

		// Content
		$content = ($customize_body == '1' && !empty(get_option($body_text_option))) ? get_option($body_text_option) : file_get_contents(EMAIL_TEMPLATES_DIR . DS . $filename);

		// Footer
        $footer = (get_option('email_header_footer_customize') == '1') ? get_option('email_footer_text') : $this->footer;
        
        // Full body
        $body = $header . $content . $footer;

		return $body;
	}

	/**
	 * Prepare the body for the "New Client" e-mail.
	 * The new username and password are also sent.
	 */
	private function email_new_client($username,$password)
	{
        $this->email_body = $this->email_prepare_body('new_client');
		$this->email_body = str_replace(
									array('%SUBJECT%','%BODY1%','%BODY2%','%BODY3%','%LBLUSER%','%LBLPASS%','%USERNAME%','%PASSWORD%','%URI%'),
									array(
											$this->strings_new_client['subject'],
											$this->strings_new_client['body'],
											$this->strings_new_client['body2'],
											$this->strings_new_client['body3'],
											$this->strings_new_client['label_user'],
											$this->strings_new_client['label_pass'],
											$username,
											$password,
											BASE_URI
										),
									$this->email_body
								);
		return array(
					'subject' => $this->strings_new_client['subject'],
					'body' => $this->email_body
				);
	}

	/**
	 * Prepare the body for the "New Client" self registration e-mail.
	 * The name of the client and username are also sent.
	 */
	private function email_new_client_self($username,$fullname,$memberships_requests)
	{
		$this->email_body = $this->email_prepare_body('new_client_self');
		$this->email_body = str_replace(
									array('%SUBJECT%','%BODY1%','%BODY2%','%BODY3%','%LBLNAME%','%LBLUSER%','%FULLNAME%','%USERNAME%','%URI%'),
									array(
										$this->strings_new_client_self['subject'],
										$this->strings_new_client_self['body'],
										$this->strings_new_client_self['body2'],
										$this->strings_new_client_self['body3'],
										$this->strings_new_client_self['label_name'],
										$this->strings_new_client_self['label_user'],
                                        $fullname,
                                        $username,
                                        BASE_URI
									),
									$this->email_body
								);
		if ( !empty( $memberships_requests ) ) {
			$this->get_groups = get_groups([
                'group_ids' => $memberships_requests
            ]);

			$this->groups_list = '<ul>';
			foreach ( $this->get_groups as $group ) {
				$this->groups_list .= '<li>' . $group['name'] . '</li>';
			}
			$this->groups_list .= '</ul>';

			$memberships_requests = implode(',',$memberships_requests);
			$this->email_body = str_replace(
									array('%LABEL_REQUESTS%', '%GROUPS_REQUESTS%'),
									array(
										$this->strings_new_client_self['label_request'],
										$this->groups_list
									),
								$this->email_body
							);
        }
        else {
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
					'subject' => $this->strings_new_client_self['subject'],
					'body' => $this->email_body
				);
	}

	/**
	 * Prepare the body for the "Account approved" e-mail.
	 * Also sends the memberships requests approval status.
	 */
	private function email_account_approve($username,$name,$memberships_requests)
	{
		$requests_title_replace = false;

		$this->get_groups = get_groups([]);

		if ( !empty( $memberships_requests['approved'] ) ) {
			$requests_title_replace = true;
			$approved_title = '<p>'.$this->strings_account_approved['title_approved'].'</p>';
			// Make the list
			$approved_list = '<ul>';
			foreach ( $memberships_requests['approved'] as $group_id ) {
				$approved_list .= '<li style="list-style:disc;">' . $this->get_groups[$group_id]['name'] . '</li>';
			}
			$approved_list .= '</ul><hr>';
		}
		else {
			$approved_list =  '';
			$approved_title = '';
		}
		if ( !empty( $memberships_requests['denied'] ) ) {
			$requests_title_replace = true;
			$denied_title = '<p>'.$this->strings_account_approved['title_denied'].'</p>';
			// Make the list
			$denied_list = '<ul>';
			foreach ( $memberships_requests['denied'] as $group_id ) {
				$denied_list .= '<li style="list-style:disc;">' . $this->get_groups[$group_id]['name'] . '</li>';
			}
			$denied_list .= '</ul><hr>';
		}
		else {
			$denied_list =  '';
			$denied_title = '';
		}

		$requests_title = ( $requests_title_replace == true ) ? '<p>'.$this->strings_account_approved['title_approved'].'</p>' : '';

		$this->email_body = $this->email_prepare_body('account_approve');
		$this->email_body = str_replace(
									array('%SUBJECT%','%BODY1%', '%REQUESTS_TITLE%', '%APPROVED_TITLE%','%GROUPS_APPROVED%','%DENIED_TITLE%','%GROUPS_DENIED%','%BODY2%','%BODY3%','%URI%'),
									array(
										$this->strings_account_approved['subject'],
										$this->strings_account_approved['body'],
										'<p>'.$this->strings_account_approved['title_memberships'].'</p>',
										$approved_title,
										$approved_list,
										$denied_title,
										$denied_list,
										$this->strings_account_approved['body2'],
										$this->strings_account_approved['body3'],
										BASE_URI
									),
									$this->email_body
								);
		return array(
					'subject' => $this->strings_account_approved['subject'],
					'body' => $this->email_body
				);
	}

	/**
	 * Prepare the body for the "Account denied" e-mail.
	 */
	private function email_account_deny($username,$name)
	{
		$this->email_body = $this->email_prepare_body('account_deny');
		$this->email_body = str_replace(
									array('%SUBJECT%','%BODY1%','%BODY2%'),
									array(
										$this->strings_account_denied['subject'],
										$this->strings_account_denied['body'],
										$this->strings_account_denied['body2'],
									),
									$this->email_body
								);
		return array(
					'subject' => $this->strings_account_denied['subject'],
					'body' => $this->email_body
				);
	}

	/**
	 * Prepare the body for the "New User" e-mail.
	 * The new username and password are also sent.
	 */
	private function email_new_user($username,$password)
	{
		$this->email_body = $this->email_prepare_body('new_user');
		$this->email_body = str_replace(
									array('%SUBJECT%','%BODY1%','%BODY2%','%BODY3%','%LBLUSER%','%LBLPASS%','%USERNAME%','%PASSWORD%','%URI%'),
									array(
										$this->strings_new_user['subject'],
										$this->strings_new_user['body'],
										$this->strings_new_user['body2'],
										$this->strings_new_user['body3'],
										$this->strings_new_user['label_user'],
										$this->strings_new_user['label_pass'],
										$username,
										$password,
										BASE_URI
									),
									$this->email_body
								);
		return array(
					'subject' => $this->strings_new_user['subject'],
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
		$this->email_body = $this->email_prepare_body('new_file_by_user');
		$this->email_body = str_replace(
									array('%SUBJECT%','%BODY1%','%FILES%','%BODY2%','%BODY3%','%BODY4%','%URI%'),
									array(
										$this->strings_file_by_user['subject'],
										$this->strings_file_by_user['body'],
										$files_list,
										$this->strings_file_by_user['body2'],
										$this->strings_file_by_user['body3'],
										$this->strings_file_by_user['body4'],
										BASE_URI
									),
									$this->email_body
								);
		return array(
					'subject' => $this->strings_file_by_user['subject'],
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
		$this->email_body = $this->email_prepare_body('new_files_by_client');
		$this->email_body = str_replace(
									array('%SUBJECT%','%BODY1%','%FILES%','%BODY2%','%BODY3%','%URI%'),
									array(
										$this->strings_file_by_client['subject'],
										$this->strings_file_by_client['body'],
										$files_list,
										$this->strings_file_by_client['body2'],
										$this->strings_file_by_client['body3'],
										BASE_URI
									),
									$this->email_body
								);
		return array(
					'subject' => $this->strings_file_by_client['subject'],
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
		$this->email_body = $this->email_prepare_body('password_reset');
		$this->email_body = str_replace(
									array('%SUBJECT%','%BODY1%','%BODY2%','%BODY3%','%BODY4%','%LBLUSER%','%USERNAME%','%URI%'),
									array(
										$this->strings_pass_reset['subject'],
										$this->strings_pass_reset['body'],
										$this->strings_pass_reset['body2'],
										$this->strings_pass_reset['body3'],
										$this->strings_pass_reset['body4'],
										$this->strings_pass_reset['label_user'],
										$username,
										BASE_URI.'reset-password.php?token=' . $token . '&user=' . $username,
									),
									$this->email_body
								);
		return array(
					'subject' => $this->strings_pass_reset['subject'],
					'body' => $this->email_body
				);
	}

	/**
	 * Prepare the body for the e-mail sent when a client changes group
	 *  membeship requests.
	 */
	private function email_client_edited($username,$fullname,$memberships_requests)
	{
		$this->email_body = $this->email_prepare_body('client_edited');
		$this->email_body = str_replace(
									array('%SUBJECT%','%BODY1%','%BODY2%','%LBLNAME%','%LBLUSER%','%FULLNAME%','%USERNAME%','%URI%'),
									array(
										$this->strings_client_edited['subject'],
										$this->strings_client_edited['body'],
										$this->strings_client_edited['body2'],
										$this->strings_client_edited['label_name'],
										$this->strings_client_edited['label_user'],
										$fullname,$username,BASE_URI
										),
									$this->email_body
								);
		if ( !empty( $memberships_requests ) ) {
			$this->get_groups = get_groups([
                'group_ids' => $memberships_requests
            ]);

			$this->groups_list = '<ul>';
			foreach ( $this->get_groups as $group ) {
				$this->groups_list .= '<li>' . $group['name'] . '</li>';
			}
			$this->groups_list .= '</ul>';

			$memberships_requests = implode(',',$memberships_requests);
			$this->email_body = str_replace(
									array('%LABEL_REQUESTS%', '%GROUPS_REQUESTS%'),
									array(
										$this->strings_client_edited['label_request'],
										$this->groups_list
									),
								$this->email_body
							);
		}
        else {
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
					'subject' => $this->strings_client_edited['subject'],
					'body' => $this->email_body
				);
	}


	/**
	 * Prepare the body for the e-mail sent when a client changes group
	 *  membeship requests.
	 */
	private function email_test_settings($message)
	{
        $subject = __('Email configuration test', 'cftp_admin');
		$this->email_body = $this->email_prepare_body('test_settings');
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
		$this->preview		= (!empty($arguments['preview'])) ? $arguments['preview'] : false;
		$this->type			= $arguments['type'];
		$this->addresses	= (!empty($arguments['address'])) ? $arguments['address'] : '';
		$this->username		= (!empty($arguments['username'])) ? $arguments['username'] : '';
		$this->password		= (!empty($arguments['password'])) ? $arguments['password'] : '';
		$this->client_id	= (!empty($arguments['client_id'])) ? $arguments['client_id'] : '';
		$this->name			= (!empty($arguments['name'])) ? $arguments['name'] : '';
		$this->files_list	= (!empty($arguments['files_list'])) ? $arguments['files_list'] : '';
		$this->token		= (!empty($arguments['token'])) ? $arguments['token'] : '';
        $this->memberships	= (!empty($arguments['memberships'])) ? $arguments['memberships'] : '';
        
        $test_message = (!empty($arguments['message'])) ? filter_var($arguments['message'], FILTER_SANITIZE_STRING) : __('This is a test message', 'cftp_admin');

        $this->try_bcc = false;
        $this->email_successful = false;

        $debug = false;

        switch($this->type) {
            case 'test_settings':
                $this->body_variables = [ $test_message ];
                $this->addresses = $arguments['to'];
                $debug = true;
			break;
            case 'new_files_by_user':
                $this->body_variables = [ $this->files_list, ];
				if (get_option('mail_copy_user_upload') == '1') {
					$this->try_bcc = true;
				}
			break;
            case 'new_files_by_client':
                $this->body_variables = [ $this->files_list, ];
				if (get_option('mail_copy_client_upload') == '1') {
					$this->try_bcc = true;
				}
			break;
            case 'new_client':
                $this->body_variables = [ $this->username, $this->password, ];
			break;
            case 'new_client_self':
                $this->body_variables = [ $this->username, $this->name, $this->memberships ];
			break;
			case 'account_approve':
                $this->body_variables = [ $this->username, $this->name, $this->memberships, ];
			break;
			case 'account_deny':
                $this->body_variables = [ $this->username, $this->name, ];
			break;
			case 'new_user':
                $this->body_variables = [ $this->username, $this->password, ];
			break;
			case 'password_reset':
                $this->body_variables = [ $this->username, $this->token, ];
			break;
			case 'client_edited':
                $this->body_variables = [ $this->username, $this->name, $this->memberships, ];
			break;
        }

        /** Generates the subject and body contents */
        $this->method = 'email_' . $this->type;
        $this->mail_info = call_user_func_array([$this, $this->method], $this->body_variables );

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
		if ( $this->preview == true ) {
			return $this->mail_info['body'];
		}
		else {
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
                $email->Debugoutput = function($str, $level) {
                    $_SESSION['email_debug_message'] .= $str."\n";
                };
            }

            switch (get_option('mail_system_use')) {
				case 'smtp':
						$email->IsSMTP();
						$email->Host = get_option('mail_smtp_host');
						$email->Port = get_option('mail_smtp_port');
						$email->Username = get_option('mail_smtp_user');
						$email->Password = get_option('mail_smtp_pass');

						if ( get_option('mail_smtp_auth') != 'none' ) {
							$email->SMTPAuth = true;
							$email->SMTPSecure = get_option('mail_smtp_auth');
						}
						else {
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
			$email->AltBody = __('This email contains HTML formatting and cannot be displayed right now. Please use an HTML compatible reader.','cftp_admin');

			$email->SetFrom(get_option('admin_email_address'), get_option('mail_from_name'));
			$email->AddReplyTo(get_option('admin_email_address'), get_option('mail_from_name'));

            if ( !empty( $this->name ) ) {
                $email->AddAddress($this->addresses, $this->name);
            }
            else {
                $email->AddAddress($this->addresses);
            }

			/**
			 * Check if BCC is enabled and get the list of
			 * addresses to add, based on the email type.
			 */
			if ($this->try_bcc === true) {
				$this->add_bcc_to = array();
				if (get_option('mail_copy_main_user') == '1') {
					$this->add_bcc_to[] = get_option('admin_email_address');
				}
				$more_addresses = get_option('mail_copy_addresses');
				if (!empty($more_addresses)) {
					$more_addresses = explode(',',$more_addresses);
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
