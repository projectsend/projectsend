<?php

/**

 * Class that handles all the e-mails that the system can send.

 *

 * Currently there are emails defined for the following actions:

 * - A new file has been uploaded by a system user.

 * - A new file has been uploaded by a client.

 * - A new client has been created by a system user.

 * - A new client has self-registered.

 * - A new system user has been created.

 *

 * @package		ProjectSend

 * @subpackage	Classes

 */



/**

 * Call the file that has the markup for the header and footer

 * of the e-mails.

 */

include_once(ROOT_DIR.'/includes/email-template.php');



/** Define the messages texts */



/** Strings for the "New file uploaded" BY A SYSTEM USER e-mail */

$email_strings_file_by_user = array(

									'subject'	=> __('New files uploaded for you','cftp_admin'),

									'body'		=> __('The following files are now available for you to download.','cftp_admin'),

									'body2'		=> __("If you prefer not to be notified about new files, please go to My Account and deactivate the notifications checkbox.",'cftp_admin'),

									'body3'		=> __('You can access a list of all your files or upload your own','cftp_admin'),

									'body4'		=> __('by logging in here','cftp_admin')

								);



/** Strings for the "New file uploaded" BY A CLIENT e-mail */

$email_strings_file_by_client = array(

									'subject'	=> __('New files uploaded by clients','cftp_admin'),

									'body'		=> __('New files has been uploaded by the following clients','cftp_admin'),

									'body2'		=> __("You can manage these files",'cftp_admin'),

									'body3'		=> __('by logging in here','cftp_admin')

								);





/** Strings for the "New client created" e-mail */

$email_strings_new_client = array(

									'subject'		=> __('Welcome to '.BRAND_NAME.'','cftp_admin'),

									'body'			=> __('A new account was created for you. From now on, you can access the files that have been uploaded under your account using the following credentials:','cftp_admin'),

									'body2'			=> __('You can log in following this link','cftp_admin'),

									'body3'			=> __('Please contact the administrator if you need further assistance.','cftp_admin'),

									'label_user'	=> __('Your username','cftp_admin'),

									'label_pass'	=> __('Your password','cftp_admin')

								);
$email_strings_invite_client = array(

									'subject'		=> __('Invitation to Download','cftp_admin'),

									'body'			=> __('A new account was created for you. From now on, you can access the files that have been uploaded under your account using the following credentials:','cftp_admin'),

									'body2'			=> __('You can log in following this link','cftp_admin'),

									'body3'			=> __('After Signing in click "My account" link and update your account Information.','cftp_admin'),

									'body4'			=> __('Please contact the administrator if you need further assistance.','cftp_admin'),

									'label_user'	=> __('Your username','cftp_admin'),

									'label_pass'	=> __('Your password','cftp_admin')

								);
$email_strings_drop_off_guest = array(
								'subject'		=> __('Drop-off guest upload','cftp_admin'),
								'body'			=> __('You have asked us to send you this message so that you can drop-off some files for someone'),
								'label_name'			=> __('Name','cftp_admin'),
								'label_organization'			=> __('Organization','cftp_admin'),
								'label_email'			=> __('Email','cftp_admin'),

);



/**

 * Strings for the "New client" e-mail to the admin

 * on self registration.

 */

$email_strings_new_client_self = array(

									'subject'		=> __('A new client has registered.','cftp_admin'),

									'body'			=> __('A new account was created using the self registration form on your site. Registration information:','cftp_admin'),

									'label_name'	=> __('Full name','cftp_admin'),

									'label_user'	=> __('Username','cftp_admin')

								);

if (CLIENTS_AUTO_APPROVE == '0') {

	$email_strings_new_client_self['body2'] = __('Please log in to activate it.','cftp_admin');

	$email_strings_new_client_self['body3'] = __('Remember, your new client will not be able to log in until an administrator has approved their account.','cftp_admin');

}

else {

	$email_strings_new_client_self['body2'] = __('Auto-approvals of new accounts are currently enabled.','cftp_admin');

	$email_strings_new_client_self['body3'] = __('You can log in to manually deactivate it.','cftp_admin');

}






/** Strings for the "New system user created" e-mail */

$email_strings_new_user = array(

									'subject'		=> __('Welcome to '.BRAND_NAME.'','cftp_admin'),

									'body'			=> __('A new account was created for you. From now on, you can access the system administrator using the following credentials:','cftp_admin'),

									'body2'			=> __('Access the system panel here','cftp_admin'),

									'body3'			=> __('Thank you for using ProjectSend.','cftp_admin'),

									'label_user'	=> __('Your username','cftp_admin'),

									'label_pass'	=> __('Your password','cftp_admin')

								);





/** Strings for the "Reset password" e-mail */

$email_strings_pass_reset = array(

									'subject'		=> __('Password reset instructions','cftp_admin'),

									'body'			=> __('A request has been received to reset the password for the following account:','cftp_admin'),

									'body2'			=> __('To continue, please visit the following link','cftp_admin'),

									'body3'			=> __('The request is valid only for 24 hours.','cftp_admin'),

									'body4'			=> __('If you did not make this request, simply ignore this email.','cftp_admin'),

									'label_user'	=> __('Username','cftp_admin'),

								);





class PSend_Email

{

	/**

	 * The body of the e-mails is gotten from the html templates

	 * found on the /emails folder.

	 */

	function email_prepare_body($type)

	{

		global $email_template_header;

		global $email_template_footer;

		

		switch ($type) {

			case 'new_client':

					$filename	= 'new-client.html';

					$body_check	= (!defined('EMAILS_CLIENT_BY_USER_USE_CUSTOM') || EMAILS_CLIENT_BY_USER_USE_CUSTOM == '0') ? '0' : EMAILS_CLIENT_BY_USER_USE_CUSTOM;

					$body_text	= EMAILS_CLIENT_BY_USER_TEXT;

				break;

			case 'new_client_self':

					$filename	= 'new-client-self.html';

					$body_check	= (!defined('EMAILS_CLIENT_BY_SELF_USE_CUSTOM') || EMAILS_CLIENT_BY_SELF_USE_CUSTOM == '0') ? '0' : EMAILS_CLIENT_BY_SELF_USE_CUSTOM;

					$body_text	= EMAILS_CLIENT_BY_SELF_TEXT;

				break;

			case 'new_user':

					$filename	= 'new-user.html';

					$body_check	= (!defined('EMAILS_NEW_USER_USE_CUSTOM') || EMAILS_NEW_USER_USE_CUSTOM == '0') ? '0' : EMAILS_NEW_USER_USE_CUSTOM;

					$body_text	= EMAILS_NEW_USER_TEXT;

				break;

			case 'new_file_by_user':

					$filename	= 'new-file-for-client.html';

					$body_check	= (!defined('EMAILS_FILE_BY_USER_USE_CUSTOM') || EMAILS_FILE_BY_USER_USE_CUSTOM == '0') ? '0' : EMAILS_FILE_BY_USER_USE_CUSTOM;

					$body_text	= EMAILS_FILE_BY_USER_TEXT;

				break;

			case 'new_file_by_client':

					$filename	= 'new-file-by-client.html';

					$body_check	= (!defined('EMAILS_FILE_BY_CLIENT_USE_CUSTOM') || EMAILS_FILE_BY_CLIENT_USE_CUSTOM == '0') ? '0' : EMAILS_FILE_BY_CLIENT_USE_CUSTOM;

					$body_text	= EMAILS_FILE_BY_CLIENT_TEXT;

				break;

			case 'password_reset':

					$filename	= 'password-reset.html';

					$body_check	= (!defined('EMAILS_PASS_RESET_USE_CUSTOM') || EMAILS_PASS_RESET_USE_CUSTOM == '0') ? '0' : EMAILS_PASS_RESET_USE_CUSTOM;

					$body_text	= EMAILS_PASS_RESET_TEXT;

				break;
			case 'mail_upload':
					$filename	= 'mail-upload.html';
					$body_check	= (!defined('EMAILS_PASS_RESET_USE_CUSTOM') || EMAILS_PASS_RESET_USE_CUSTOM == '0') ? '0' : EMAILS_PASS_RESET_USE_CUSTOM;
					$body_text	= EMAILS_PASS_RESET_TEXT;
				break;

		}



		if ($body_check == '0') {

			$this->get_body = file_get_contents(ROOT_DIR.'/emails/'.$filename);

		}

		else {

			$this->get_body = $body_text;

		}

		

		/**

		 * Header

		 */

		if (!defined('EMAILS_HEADER_FOOTER_CUSTOM') || EMAILS_HEADER_FOOTER_CUSTOM == '0') {

			$this->make_body = $email_template_header;

		}

		else {

			$this->make_body = EMAILS_HEADER_TEXT;

		}



		/**

		 * Body

		 */

		$this->make_body .= $this->get_body;



		/**

		 * Footer

		 */

		if (!defined('EMAILS_HEADER_FOOTER_CUSTOM') || EMAILS_HEADER_FOOTER_CUSTOM == '0') {

			$this->make_body .= $email_template_footer;

		}

		else {

			$this->make_body .= EMAILS_FOOTER_TEXT;

		}





		return $this->make_body;

	}



	/**

	 * Prepare the body for the "New Client" e-mail.

	 * The new username and password are also sent.

	 */

	function invite_user_to_download_file($username,$password)

	{
		
		global $email_strings_invite_client;
		//global $email_strings_new_client;
		$this->email_body = $this->email_prepare_body('new_client');
		$body3 = $email_strings_invite_client['body3'].'<br>'.$email_strings_invite_client['body4'];

		$this->email_body = str_replace(

									array('%SUBJECT%','%BODY1%','%BODY2%','%BODY3%','%LBLUSER%','%LBLPASS%','%USERNAME%','%PASSWORD%','%URI%'),

									array(

										$email_strings_invite_client['subject'],

										$email_strings_invite_client['body'],

										$email_strings_invite_client['body2'],

										$body3,

										$email_strings_invite_client['label_user'],

										$email_strings_invite_client['label_pass'],

										$username,$password,BASE_URI

										),

									$this->email_body

								);

		return array(

					'subject' => $email_strings_invite_client['subject'],

					'body' => $this->email_body

				);

	
	}
	function email_new_client($username,$password)

	{

		global $email_strings_new_client;

		$this->email_body = $this->email_prepare_body('new_client');

		$this->email_body = str_replace(

									array('%SUBJECT%','%BODY1%','%BODY2%','%BODY3%','%LBLUSER%','%LBLPASS%','%USERNAME%','%PASSWORD%','%URI%'),

									array(

										$email_strings_new_client['subject'],

										$email_strings_new_client['body'],

										$email_strings_new_client['body2'],

										$email_strings_new_client['body3'],

										$email_strings_new_client['label_user'],

										$email_strings_new_client['label_pass'],

										$username,$password,BASE_URI

										),

									$this->email_body

								);

		return array(

					'subject' => $email_strings_new_client['subject'],

					'body' => $this->email_body

				);

	}



	/**

	 * Prepare the body for the "New Client" self registration e-mail.

	 * The name of the client and username are also sent.

	 */

	function email_new_client_self($username,$fullname)

	{

		global $email_strings_new_client_self;

		$this->email_body = $this->email_prepare_body('new_client_self');

		$this->email_body = str_replace(

									array('%SUBJECT%','%BODY1%','%BODY2%','%BODY3%','%LBLNAME%','%LBLUSER%','%FULLNAME%','%USERNAME%','%URI%'),

									array(

										$email_strings_new_client_self['subject'],

										$email_strings_new_client_self['body'],

										$email_strings_new_client_self['body2'],

										$email_strings_new_client_self['body3'],

										$email_strings_new_client_self['label_name'],

										$email_strings_new_client_self['label_user'],

										$fullname,$username,BASE_URI

										),

									$this->email_body

								);

		return array(

					'subject' => $email_strings_new_client_self['subject'],

					'body' => $this->email_body

				);

	}



	/**

	 * Prepare the body for the "New User" e-mail.

	 * The new username and password are also sent.

	 */

	function email_new_user($username,$password)

	{

		global $email_strings_new_user;

		$this->email_body = $this->email_prepare_body('new_user');

		$this->email_body = str_replace(

									array('%SUBJECT%','%BODY1%','%BODY2%','%BODY3%','%LBLUSER%','%LBLPASS%','%USERNAME%','%PASSWORD%','%URI%'),

									array(

										$email_strings_new_user['subject'],

										$email_strings_new_user['body'],

										$email_strings_new_user['body2'],

										$email_strings_new_user['body3'],

										$email_strings_new_user['label_user'],

										$email_strings_new_user['label_pass'],

										$username,

										$password,

										BASE_URI

									),

									$this->email_body

								);

		return array(

					'subject' => $email_strings_new_user['subject'],

					'body' => $this->email_body

				);

	}



	/**

	 * Prepare the body for the "New files for client" e-mail and replace the

	 * tags with the strings values set at the top of this file and the

	 * link to the log in page.

	 */

	function email_new_files_for_client($files_list)
	{
		global $email_strings_file_by_user;
		if(is_array($files_list)) {
			$repairArray = array();
			foreach($files_list as $files) {
				$repairArray[] = $files;
			}
			$files_list = implode("<br />", $repairArray);
		}	
		$this->email_body = $this->email_prepare_body('new_file_by_user');

		$this->email_body = str_replace(

									array('%SUBJECT%','%BODY1%','%FILES%','%BODY2%','%BODY3%','%BODY4%','%URI%'),

									array(

										$email_strings_file_by_user['subject'],

										$email_strings_file_by_user['body'],

										$files_list,

										$email_strings_file_by_user['body2'],

										$email_strings_file_by_user['body3'],

										$email_strings_file_by_user['body4'],

										BASE_URI

									),

									$this->email_body

								);

		return array(

					'subject' => $email_strings_file_by_user['subject'],

					'body' => $this->email_body

				);

	}



	/**

	 * Prepare the body for the "New files by client" e-mail and replace the

	 * tags with the strings values set at the top of this file and the

	 * link to the log in page.

	 */

	function email_new_files_by_client($files_list)

	{

		global $email_strings_file_by_client;

		$this->email_body = $this->email_prepare_body('new_file_by_client');

		$this->email_body = str_replace(

									array('%SUBJECT%','%BODY1%','%FILES%','%BODY2%','%BODY3%','%URI%'),

									array(

										$email_strings_file_by_client['subject'],

										$email_strings_file_by_client['body'],

										$files_list,

										$email_strings_file_by_client['body2'],

										$email_strings_file_by_client['body3'],

										BASE_URI

									),

									$this->email_body

								);

		return array(

					'subject' => $email_strings_file_by_client['subject'],

					'body' => $this->email_body

				);

	}



	/**

	 * Prepare the body for the "Password reset" e-mail and replace the

	 * tags with the strings values set at the top of this file and the

	 * link to the log in page.

	 */

	function email_password_reset($username, $token)

	{

		global $email_strings_pass_reset;

		$this->email_body = $this->email_prepare_body('password_reset');

		$this->email_body = str_replace(

									array('%SUBJECT%','%BODY1%','%BODY2%','%BODY3%','%BODY4%','%LBLUSER%','%USERNAME%','%URI%'),

									array(

										$email_strings_pass_reset['subject'],

										$email_strings_pass_reset['body'],

										$email_strings_pass_reset['body2'],

										$email_strings_pass_reset['body3'],

										$email_strings_pass_reset['body4'],

										$email_strings_pass_reset['label_user'],

										$username,

										BASE_URI.'reset-password.php?token=' . $token . '&user=' . $username,

									),

									$this->email_body

								);

		return array(

					'subject' => $email_strings_pass_reset['subject'],

					'body' => $this->email_body

				);

	}



	/**

	 * Finally, try to send the e-mail and return a status, where

	 * 1 = Message sent OK

	 * 2 = Error sending the e-mail

	 *

	 * Returns custom values instead of a boolean value to allow more

	 * codes in the future, on new validations and functions.

	 */

	function psend_send_email($arguments)

	{

		/** Generate the values from the arguments */

		$this->type			= $arguments['type'];

		$this->addresses	= $arguments['address'];

		$this->username		= (!empty($arguments['username'])) ? $arguments['username'] : '';

		$this->password		= (!empty($arguments['password'])) ? $arguments['password'] : '';

		$this->client_id	= (!empty($arguments['client_id'])) ? $arguments['client_id'] : '';

		$this->name			= (!empty($arguments['name'])) ? $arguments['name'] : '';

		$this->files_list	= (!empty($arguments['files_list'])) ? $arguments['files_list'] : '';

		$this->token		= (!empty($arguments['token'])) ? $arguments['token'] : '';

		

		require_once(ROOT_DIR.'/includes/phpmailer/class.phpmailer.php');



		if (!spl_autoload_functions() OR (!in_array('PHPMailerAutoload', spl_autoload_functions()))) {

			require_once(ROOT_DIR.'/includes/phpmailer/PHPMailerAutoload.php');

		}



		$this->try_bcc = false;

		switch($this->type) { 
			case 'invite_user_to_download_file':
				$this->mail_info = $this->invite_user_to_download_file($this->username,$this->password);
			break;
			

			case 'new_files_for_client':

				$this->mail_info = $this->email_new_files_for_client($this->files_list);

				if ((defined('COPY_MAIL_ON_USER_UPLOADS')) && COPY_MAIL_ON_USER_UPLOADS == '1') {

					$this->try_bcc = true;

				}

			break;

			case 'new_file_by_client':

				$this->mail_info = $this->email_new_files_by_client($this->files_list);

				if (COPY_MAIL_ON_CLIENT_UPLOADS == '1') {

					$this->try_bcc = true;

				}

			break;

			case 'new_client':

				$this->mail_info = $this->email_new_client($this->username,$this->password);

			break;

			case 'new_client_self':

				$this->mail_info = $this->email_new_client_self($this->username,$this->name);

			break;

			case 'new_user':

				$this->mail_info = $this->email_new_user($this->username,$this->password);

			break;

			case 'password_reset':

				$this->mail_info = $this->email_password_reset($this->username,$this->token);

			break;

		}

		

		/**

		 * phpMailer

		 */
		$this->send_mail = new PHPMailer();

		switch (MAIL_SYSTEM) {

			case 'smtp':

					$this->send_mail->IsSMTP();

					$this->send_mail->SMTPAuth = true;

					$this->send_mail->Host = SMTP_HOST;

					$this->send_mail->Port = SMTP_PORT;

					$this->send_mail->Username = SMTP_USER;

					$this->send_mail->Password = SMTP_PASS;

					

					if ( defined('SMTP_AUTH') && SMTP_AUTH != 'none' ) {

						$this->send_mail->SMTPSecure = SMTP_AUTH;

					}

				break;

			case 'gmail':

					$this->send_mail->IsSMTP();

					$this->send_mail->SMTPAuth = true;

					$this->send_mail->SMTPSecure = "tls";

					$this->send_mail->Host = 'smtp.gmail.com';

					$this->send_mail->Port = 587;

					$this->send_mail->Username = SMTP_USER;

					$this->send_mail->Password = SMTP_PASS;

				break;

			case 'sendmail':

					$this->send_mail->IsSendmail();

				break;

		}

		

		$this->send_mail->CharSet = EMAIL_ENCODING;



		$this->send_mail->Subject = $this->mail_info['subject'];

		$this->send_mail->MsgHTML($this->mail_info['body']);

		$this->send_mail->AltBody = __('This email contains HTML formatting and cannot be displayed right now. Please use an HTML compatible reader.','cftp_admin');



		$this->send_mail->SetFrom(ADMIN_EMAIL_ADDRESS, MAIL_FROM_NAME);

		$this->send_mail->AddReplyTo(ADMIN_EMAIL_ADDRESS, MAIL_FROM_NAME);



		$this->send_mail->AddAddress($this->addresses);

		/**

		Fetch alternate emails and add as CC.

		**/

		/*

		$alternate_email = $dbh->prepare("SELECT value FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id=:user_id AND name = :name");

		$alternate_email->bindParam(':user_id', $user_id, PDO::PARAM_INT);

		$alternate_email->bindValue(':name', 'alternate_email');

		$alternate_email->execute();

		$test = $alternate_email->setFetchMode(PDO::FETCH_ASSOC);

		//print_r($test);

		$alternate_email_list = array();

		while ( $data = $alternate_email->fetch() ) {

				$alternate_email_list[] = $data['value'];

		}



					

		*/



		/**

		end

		**/

		

		/**

		 * Check if BCC is enabled and get the list of

		 * addresses to add, based on the email type.

		 */

		if ($this->try_bcc === true) {

			$this->add_bcc_to = array();

			if (COPY_MAIL_MAIN_USER == '1') {

				$this->add_bcc_to[] = ADMIN_EMAIL_ADDRESS;

			}

			$this->more_addresses = COPY_MAIL_ADDRESSES;

			if (!empty($this->more_addresses)) {

				$this->more_addresses = explode(',',$this->more_addresses);

				foreach ($this->more_addresses as $this->add_bcc) {

					$this->add_bcc_to[] = $this->add_bcc;

				}

			}

/**

Fetched alternate emails adding to CC.

**/

		if(!empty($alternate_email_list)){

			if (!empty($this->add_bcc_to)) {

						$this->add_bcc_to = array_unique($this->add_bcc_to);

						foreach ($alternate_email_list as $aml) {

							$this->send_mail->AddBCC($aml);

						}

			}

		}

/**

Fetched alternate emails adding to CC. --END--

**/



			/**

			 * Add the BCCs with the compiled array.

			 * First, clean the array to make sure the admin

			 * address is not written twice.

			 */

			if (!empty($this->add_bcc_to)) {

				$this->add_bcc_to = array_unique($this->add_bcc_to);

				foreach ($this->add_bcc_to as $this->set_bcc) {

					$this->send_mail->AddBCC($this->set_bcc);

				}

			}

			 

		}

		

		/**

		 * Finally, send the e-mail.

		 */

		if($this->send_mail->Send()) {

			return 1;

		}

		else {

			return 2;

		}

	}
	/* custom code added by rj */	
	function psend_send_drop_off_email($arguments)	
	{				
		$this->type			= (!empty($arguments['type'])) ? $arguments['type'] : '';		
		$this->name			= (!empty($arguments['name'])) ? $arguments['name'] : '';		
		$this->organization		= (!empty($arguments['your_organization'])) ? $arguments['your_organization'] : '';		
		$this->email		= (!empty($arguments['your_email'])) ? $arguments['your_email'] : '';	
		$this->token		= (!empty($arguments['token'])) ? $arguments['token'] : '';
		require_once(ROOT_DIR.'/includes/phpmailer/class.phpmailer.php');		
		if (!spl_autoload_functions() OR (!in_array('PHPMailerAutoload', spl_autoload_functions()))) {			
			require_once(ROOT_DIR.'/includes/phpmailer/PHPMailerAutoload.php');		
		}		
		$this->try_bcc = false;		
		switch($this->type) {
			case 'dropoff_guest_request':
				$this->mail_info = $this->invite_user_to_upload_file($this->name,$this->organization,$this->email,$this->token);
			break;
		}
		/*** phpMailer		 */	
		$this->send_mail = new PHPMailer();
		switch (MAIL_SYSTEM) {
			case 'smtp':
				$this->send_mail->IsSMTP();
				$this->send_mail->SMTPAuth = true;
				$this->send_mail->Host = SMTP_HOST;
				$this->send_mail->Port = SMTP_PORT;
				$this->send_mail->Username = SMTP_USER;
				$this->send_mail->Password = SMTP_PASS;
				if ( defined('SMTP_AUTH') && SMTP_AUTH != 'none' ) {
					$this->send_mail->SMTPSecure = SMTP_AUTH;
				}
			break;
			case 'gmail':
				$this->send_mail->IsSMTP();
				$this->send_mail->SMTPAuth = true;
				$this->send_mail->SMTPSecure = "tls";
				$this->send_mail->Host = 'smtp.gmail.com';
				$this->send_mail->Port = 587;
				$this->send_mail->Username = SMTP_USER;
				$this->send_mail->Password = SMTP_PASS;
			break;
			case 'sendmail':
				$this->send_mail->IsSendmail();
			break;
		}
		$this->send_mail->CharSet = EMAIL_ENCODING;		
		$this->send_mail->Subject = $this->mail_info['subject'];
		$this->send_mail->MsgHTML($this->mail_info['body']);
		$this->send_mail->AltBody = __('This email contains HTML formatting and cannot be displayed right now. Please use an HTML compatible reader.','cftp_admin');
		$this->send_mail->SetFrom(ADMIN_EMAIL_ADDRESS, MAIL_FROM_NAME);
		$this->send_mail->AddReplyTo(ADMIN_EMAIL_ADDRESS, MAIL_FROM_NAME);
		$this->send_mail->AddAddress($this->email);
		/**		 * Check if BCC is enabled and get the list of * addresses to add, based on the email type.		 */	
		
		/* Send Mail  */
		if($this->send_mail->Send()) { return 1; 
		}
		else {
			echo $this->send_mail->ErrorInfo;
			return 2;		
		}	
	}	
	function invite_user_to_upload_file($name,$organization,$email,$token)
	{
		/*global $email_strings_invite_client;*/
		global $email_strings_drop_off_guest;
		
		$this->email_body = $this->email_prepare_body('mail_upload');
		$this->email_body = str_replace(
			array('%SUBJECT%','%BODY1%','%LNAME%','%LORGNAME%','%LEMAIL%','%LNAMEVAL%','%LORGNAMEVAL%','%LEMAILVAL%','%URI%'),
				array(
					$email_strings_drop_off_guest['subject'],
					$email_strings_drop_off_guest['body'],
					$email_strings_drop_off_guest['label_name'],
					$email_strings_drop_off_guest['label_organization'],
					$email_strings_drop_off_guest['label_email'],
					$name,$organization,$email,
					BASE_URI.'dropoff_guest_process.php?auth=' . $token,
				),
				$this->email_body								
			);
			return array(
				'subject' => $email_strings_drop_off_guest['subject'],
				'body' => $this->email_body	
			);
	}
}
?>

