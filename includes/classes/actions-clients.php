<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * clients accounts.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

class ClientActions
{

	var $client = '';

	/**
	 * Validate the information from the form.
	 */
	function validate_client($arguments)
	{
		require(ROOT_DIR.'/includes/vars.php');

		global $valid_me;
		$this->state = array();

		$this->id = $arguments['id'];
		$this->name = $arguments['name'];
		$this->email = $arguments['email'];
		$this->password = $arguments['password'];
		//$this->password_repeat = $arguments['password_repeat'];
		$this->address = $arguments['address'];
		$this->phone = $arguments['phone'];
		$this->contact = $arguments['contact'];
		$this->notify = $arguments['notify'];
		$this->type = $arguments['type'];

		/**
		 * These validations are done both when creating a new client and
		 * when editing an existing one.
		 */
		$valid_me->validate('completed',$this->name,$validation_no_name);
		$valid_me->validate('completed',$this->email,$validation_no_email);
		$valid_me->validate('email',$this->email,$validation_invalid_mail);
		
		/**
		 * Validations for NEW CLIENT submission only.
		 */
		if ($this->type == 'new_client') {
			$this->username = $arguments['username'];

			$valid_me->validate('email_exists',$this->email,$add_user_mail_exists);
			/** Username checks */
			$valid_me->validate('user_exists',$this->username,$add_user_exists);
			$valid_me->validate('completed',$this->username,$validation_no_user);
			$valid_me->validate('alpha_dot',$this->username,$validation_alpha_user);
			$valid_me->validate('length',$this->username,$validation_length_user,MIN_USER_CHARS,MAX_USER_CHARS);
			
			$this->validate_password = true;
		}
		/**
		 * Validations for CLIENT EDITING only.
		 */
		else if ($this->type == 'edit_client') {
			/**
			 * Changing password is optional.
			 * Proceed only if any of the 2 fields is completed.
			 */
			if($arguments['password'] != ''/* || $arguments['password_repeat'] != ''*/) {
				$this->validate_password = true;
			}
			/**
			 * Check if the email is currently assigned to this clients's id.
			 * If not, then check if it exists.
			 */
			$valid_me->validate('email_exists',$this->email,$add_user_mail_exists,'','','','','',$this->id);
		}

		/** Password checks */
		if (isset($this->validate_password) && $this->validate_password === true) {
			$valid_me->validate('completed',$this->password,$validation_no_pass);
			$valid_me->validate('password',$this->password,$validation_valid_pass.' '.$validation_valid_chars);
			$valid_me->validate('pass_rules',$this->password,$validation_rules_pass);
			$valid_me->validate('length',$this->password,$validation_length_pass,MIN_PASS_CHARS,MAX_PASS_CHARS);
			//$valid_me->validate('pass_match','',$validation_match_pass,'','',$this->password,$this->password_repeat);
		}

		if ($valid_me->return_val) {
			return 1;
		}
		else {
			return 0;
		}
	}


	/**
	 * Create a new client.
	 */
	function create_client($arguments)
	{
		global $hasher;
		global $database;
		$this->state = array();

		/** Define the account information */
		$this->id = $arguments['id'];
		$this->name = $arguments['name'];
		$this->email = $arguments['email'];
		$this->username = $arguments['username'];
		$this->password = $arguments['password'];
		//$this->password_repeat = $arguments['password_repeat'];
		$this->address = $arguments['address'];
		$this->phone = $arguments['phone'];
		$this->contact = $arguments['contact'];
		$this->notify = $arguments['notify'];
		$this->active = $arguments['active'];
		//$this->enc_password = md5(mysql_real_escape_string($this->password));
		$this->enc_password = $hasher->HashPassword($this->password);

		if (strlen($this->enc_password) >= 20) {

			$this->state['hash'] = 1;

			/** Who is creating the client? */
			$this->this_admin = get_current_user_username();
	
			/** Insert the client information into the database */
			$this->timestamp = time();
			$this->sql_query = $database->query("INSERT INTO tbl_users (name,user,password,address,phone,email,notify,contact,created_by,active)"
												."VALUES ('$this->name', '$this->username', '$this->enc_password', '$this->address', '$this->phone', '$this->email', '$this->notify', '$this->contact','$this->this_admin', '$this->active')");
	
			if ($this->sql_query) {
				$this->state['actions'] = 1;
				$this->state['new_id'] = mysql_insert_id();
	
				/** Send account data by email */
				$this->notify_client = new PSend_Email();
				$this->email_arguments = array(
												'type' => 'new_client',
												'address' => $this->email,
												'username' => $this->username,
												'password' => $this->password
											);
				$this->notify_send = $this->notify_client->psend_send_email($this->email_arguments);
	
				if ($this->notify_send == 1){
					$this->state['email'] = 1;
				}
				else {
					$this->state['email'] = 0;
				}
			}
			else {
				/** Query couldn't be executed */
				$this->state['actions'] = 0;
			}
		}
		else {
			$this->state['hash'] = 0;
		}

		return $this->state;
	}

	/**
	 * Edit an existing client.
	 */
	function edit_client($arguments)
	{
		global $hasher;
		global $database;
		$this->state = array();

		/** Define the account information */
		$this->id = $arguments['id'];
		$this->name = $arguments['name'];
		$this->email = $arguments['email'];
		$this->password = $arguments['password'];
		$this->address = $arguments['address'];
		$this->phone = $arguments['phone'];
		$this->contact = $arguments['contact'];
		$this->notify = $arguments['notify'];
		$this->active = $arguments['active'];
		//$this->enc_password = md5(mysql_real_escape_string($this->password));
		$this->enc_password = $hasher->HashPassword($this->password);

		if (strlen($this->enc_password) >= 20) {

			$this->state['hash'] = 1;

			/** SQL query */
			$this->edit_client_query = "UPDATE tbl_users SET 
										name = '$this->name',
										address = '$this->address',
										phone = '$this->phone',
										email = '$this->email',
										contact = '$this->contact',
										notify = '";
	
	
			/** Add the notify value to the query '' */
			$this->edit_client_query .= ($this->notify == '1') ? "1'" : "0'";
	
			/** Add the active status value to the query '' */
			$this->edit_client_query .= ", active = '";
			$this->edit_client_query .= ($this->active == '1') ? "1'" : "0'";
	
			/** Add the password to the query if it's not the dummy value '' */
			if (!empty($arguments['password'])) {
				$this->edit_client_query .= ", password = '$this->enc_password'";
			}
	
	
			$this->edit_client_query .= " WHERE id = $this->id";
			$this->sql_query = $database->query($this->edit_client_query);
	
			if ($this->sql_query) {
				$this->state['query'] = 1;
			}
			else {
				$this->state['query'] = 0;
			}
		}
		else {
			$this->state['hash'] = 0;
		}
		
		return $this->state;
	}

	/**
	 * Delete an existing client.
	 */
	function delete_client($client) {
		global $database;
		$this->check_level = array(9,8);
		if (isset($client)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $database->query('DELETE FROM tbl_users WHERE id="' . $client .'"');
			}
		}
	}

	/**
	 * Mark the client as active or inactive.
	 */
	function change_client_active_status($client_id,$change_to)
	{
		global $database;
		$this->check_level = array(9,8);
		if (isset($client_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $database->query('UPDATE tbl_users SET active='.$change_to.' WHERE id="' . $client_id . '"');
			}
		}
	}

}

?>