<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * users accounts.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

class UserActions
{

	var $user = '';
	
	/**
	 * Validate the information from the form.
	 */
	function validate_user($arguments)
	{
		require(ROOT_DIR.'/includes/vars.php');

		global $valid_me;
		$this->state = array();

		$this->id = $arguments['id'];
		$this->name = $arguments['name'];
		$this->email = $arguments['email'];
		$this->password = $arguments['password'];
		//$this->password_repeat = $arguments['password_repeat'];
		$this->role = $arguments['role'];
		$this->type = $arguments['type'];

		/**
		 * These validations are done both when creating a new user and
		 * when editing an existing one.
		 */
		$valid_me->validate('completed',$this->name,$validation_no_name);
		$valid_me->validate('completed',$this->email,$validation_no_email);
		$valid_me->validate('completed',$this->role,$validation_no_level);
		$valid_me->validate('email',$this->email,$validation_invalid_mail);
		
		/**
		 * Validations for NEW USER submission only.
		 */
		if ($this->type == 'new_user') {
			$this->username = $arguments['username'];

			$valid_me->validate('email_exists',$this->email,$add_user_mail_exists);
			/** Username checks */
			$valid_me->validate('user_exists',$this->username,$add_user_exists);
			$valid_me->validate('completed',$this->username,$validation_no_user);
			$valid_me->validate('alpha',$this->username,$validation_alpha_user);
			$valid_me->validate('length',$this->username,$validation_length_user,MIN_USER_CHARS,MAX_USER_CHARS);
			
			$this->validate_password = true;
		}
		/**
		 * Validations for USER EDITING only.
		 */
		else if ($this->type == 'edit_user') {
			/**
			 * Changing password is optional.
			 * Proceed only if any of the 2 fields is completed.
			 */
			if($arguments['password'] != '' /* || $arguments['password_repeat'] != '' */) {
				$this->validate_password = true;
			}
			/**
			 * Check if the email is currently assigned to this users's id.
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
	 * Create a new user.
	 */
	function create_user($arguments)
	{
		global $hasher;
		global $database;
		$this->state = array();

		/** Define the account information */
		$this->username = $arguments['username'];
		$this->password = $arguments['password'];
		$this->name = $arguments['name'];
		$this->email = $arguments['email'];
		$this->role = $arguments['role'];
		$this->active = $arguments['active'];
		//$this->enc_password = md5(mysql_real_escape_string($this->password));
		$this->enc_password = $hasher->HashPassword($this->password);

		if (strlen($this->enc_password) >= 20) {

			$this->state['hash'] = 1;

			$this->timestamp = time();
			$this->sql_query = $database->query("INSERT INTO tbl_users (user,password,name,email,level,active)"
												."VALUES ('$this->username', '$this->enc_password', '$this->name', '$this->email','$this->role', '$this->active')");
	
			if ($this->sql_query) {
				$this->state['query'] = 1;
				$this->state['new_id'] = mysql_insert_id();
	
				/** Send account data by email */
				$this->notify_user = new PSend_Email();
				$this->email_arguments = array(
												'type' => 'new_user',
												'address' => $this->email,
												'username' => $this->username,
												'password' => $this->password
											);
				$this->notify_send = $this->notify_user->psend_send_email($this->email_arguments);
	
				if ($this->notify_send == 1){
					$this->state['email'] = 1;
				}
				else {
					$this->state['email'] = 0;
				}
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
	 * Edit an existing user.
	 */
	function edit_user($arguments)
	{
		global $hasher;
		global $database;
		$this->state = array();

		/** Define the account information */
		$this->id = $arguments['id'];
		$this->name = $arguments['name'];
		$this->email = $arguments['email'];
		$this->role = $arguments['role'];
		$this->active = $arguments['active'];

		$this->password = $arguments['password'];
		//$this->enc_password = md5(mysql_real_escape_string($this->password));
		$this->enc_password = $hasher->HashPassword($this->password);

		if (strlen($this->enc_password) >= 20) {

			$this->state['hash'] = 1;

			/** SQL query */
			$this->edit_user_query = "UPDATE tbl_users SET 
									name = '$this->name',
									email = '$this->email',
									level = '$this->role',
									active = '";
	
			/** Add the active status value to the query '' */
			$this->edit_user_query .= ($this->active == '1') ? "1'" : "0'";
	
			/** Add the password to the query if it's not the dummy value '' */
			if (!empty($arguments['password'])) {
				$this->edit_user_query .= ", password = '$this->enc_password'";
			}
	
			$this->edit_user_query .= " WHERE id = $this->id";
			$this->sql_query = $database->query($this->edit_user_query);
	
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
	 * Delete an existing user.
	 */
	function delete_user($user)
	{
		global $database;
		$this->check_level = array(9);
		if (isset($user)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $database->query('DELETE FROM tbl_users WHERE id="' . $user .'"');
			}
		}
	}

	/**
	 * Mark the user as active or inactive.
	 */
	function change_user_active_status($user_id,$change_to)
	{
		global $database;
		$this->check_level = array(9);
		if (isset($user_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $database->query('UPDATE tbl_users SET active='.$change_to.' WHERE id="' . $user_id . '"');
			}
		}
	}

}

?>