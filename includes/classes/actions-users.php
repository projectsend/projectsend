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

	function __construct() {
		global $dbh, $json_strings;
		$this->dbh = $dbh;
	}

	/**
	 * Validate the information from the form.
	 */
	function validate_user($arguments)
	{
		global $valid_me, $json_strings;
		$this->state = array();

		$this->id = $arguments['id'];
		$this->name = $arguments['name'];
		$this->email = $arguments['email'];
		$this->password = $arguments['password'];
		//$this->password_repeat = $arguments['password_repeat'];
		$this->role = $arguments['role'];
		$this->notify_account = $arguments['notify_account'];
		$this->max_file_size = ( !empty( $arguments['max_file_size'] ) ) ? $arguments['max_file_size'] : 0;
		$this->type = $arguments['type'];

		/**
		 * These validations are done both when creating a new user and
		 * when editing an existing one.
		 */
		$valid_me->validate('completed',$this->name,$json_strings['validation']['no_name']);
		$valid_me->validate('completed',$this->email,$json_strings['validation']['no_email']);
		$valid_me->validate('completed',$this->role,$json_strings['validation']['no_role']);
		$valid_me->validate('email',$this->email,$json_strings['validation']['invalid_email']);
		$valid_me->validate('number',$this->max_file_size,$json_strings['validation']['file_size']);

		/**
		 * Validations for NEW USER submission only.
		 */
		if ($this->type == 'new_user') {
			$this->username = $arguments['username'];

			$valid_me->validate('email_exists',$this->email,$json_strings['validation']['email_exists']);
			/** Username checks */
			$valid_me->validate('user_exists',$this->username,$json_strings['validation']['user_exists']);
			$valid_me->validate('completed',$this->username,$json_strings['validation']['no_user']);
			$valid_me->validate('alpha_dot',$this->username,$json_strings['validation']['alpha_user']);
			$valid_me->validate('length',$this->username,$json_strings['validation']['length_user'],MIN_USER_CHARS,MAX_USER_CHARS);

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
			$valid_me->validate('email_exists',$this->email,$json_strings['validation']['email_exists'],'','','','','',$this->id);
		}

		/** Password checks */
		if (isset($this->validate_password) && $this->validate_password === true) {
			$valid_me->validate('completed',$this->password,$json_strings['validation']['no_pass']);
			$valid_me->validate('password',$this->password,$json_strings['validation']['valid_pass'] . " " . addslashes($json_strings['validation']['valid_chars']));
			$valid_me->validate('pass_rules',$this->password,$json_strings['validation']['rules_pass']);
			$valid_me->validate('length',$this->password,$json_strings['validation']['length_pass'],MIN_PASS_CHARS,MAX_PASS_CHARS);
			//$valid_me->validate('pass_match','',$json_strings['validation']['match_pass'],'','',$this->password,$this->password_repeat);
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
		$this->state = array();

		/** Define the account information */
		$this->username			= encode_html($arguments['username']);
		$this->password			= $arguments['password'];
		$this->name					= encode_html($arguments['name']);
		$this->email				= encode_html($arguments['email']);
		$this->role					= $arguments['role'];
		$this->active				= $arguments['active'];
		$this->notify_account	= ( $arguments['notify_account'] == '1' ) ? 1 : 0;
		$this->max_file_size		= ( !empty( $arguments['max_file_size'] ) ) ? $arguments['max_file_size'] : 0;
		$this->enc_password		= password_hash($this->password, PASSWORD_DEFAULT, [ 'cost' => HASH_COST_LOG2 ]);
		//$this->enc_password		= $hasher->HashPassword();

		if (strlen($this->enc_password) >= 20) {

			$this->state['hash'] = 1;

			$this->timestamp = time();
			$this->sql_query = $this->dbh->prepare("INSERT INTO " . TABLE_USERS . " (user,password,name,email,level,active,max_file_size)"
												." VALUES (:username, :password, :name, :email, :role, :active, :max_file_size)");
			$this->sql_query->bindParam(':username', $this->username);
			$this->sql_query->bindParam(':password', $this->enc_password);
			$this->sql_query->bindParam(':name', $this->name);
			$this->sql_query->bindParam(':email', $this->email);
			$this->sql_query->bindParam(':role', $this->role);
			$this->sql_query->bindParam(':active', $this->active, PDO::PARAM_INT);
			$this->sql_query->bindParam(':max_file_size', $this->max_file_size, PDO::PARAM_INT);

			$this->sql_query->execute();

			if ($this->sql_query) {
				$this->state['query'] = 1;
				$this->state['new_id'] = $this->dbh->lastInsertId();

				/** Send account data by email */
				$this->notify_user = new PSend_Email();
				$this->email_arguments = array(
												'type'		=> 'new_user',
												'address'	=> $this->email,
												'username'	=> $this->username,
												'password'	=> $this->password
											);
				if ($this->notify_account == 1) {
					$this->notify_send = $this->notify_user->psend_send_email($this->email_arguments);

					if ($this->notify_send == 1){
						$this->state['email'] = 1;
					}
					else {
						$this->state['email'] = 0;
					}
				}
				else {
					$this->state['email'] = 2;
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
		$this->state = array();

		/** Define the account information */
		$this->id				= $arguments['id'];
		$this->name				= encode_html($arguments['name']);
		$this->email			= encode_html($arguments['email']);
		$this->role				= $arguments['role'];
		$this->active			= ( $arguments['active'] == '1' ) ? 1 : 0;
		$this->password		= $arguments['password'];
		$this->max_file_size	= ( !empty( $arguments['max_file_size'] ) ) ? $arguments['max_file_size'] : 0;
		$this->enc_password	= password_hash($this->password, PASSWORD_DEFAULT, [ 'cost' => HASH_COST_LOG2 ]);
		//$this->enc_password 	= $hasher->HashPassword($this->password);

		if (strlen($this->enc_password) >= 20) {

			$this->state['hash'] = 1;

			/** SQL query */
			$this->edit_user_query = "UPDATE " . TABLE_USERS . " SET
									name = :name,
									email = :email,
									level = :level,
									active = :active,
									max_file_size = :max_file_size
									";

			/** Add the password to the query if it's not the dummy value '' */
			if (!empty($arguments['password'])) {
				$this->edit_user_query .= ", password = :password";
			}

			$this->edit_user_query .= " WHERE id = :id";

			$this->sql_query = $this->dbh->prepare( $this->edit_user_query );
			$this->sql_query->bindParam(':name', $this->name);
			$this->sql_query->bindParam(':email', $this->email);
			$this->sql_query->bindParam(':level', $this->role);
			$this->sql_query->bindParam(':active', $this->active, PDO::PARAM_INT);
			$this->sql_query->bindParam(':max_file_size', $this->max_file_size, PDO::PARAM_INT);
			$this->sql_query->bindParam(':id', $this->id, PDO::PARAM_INT);
			if (!empty($arguments['password'])) {
				$this->sql_query->bindParam(':password', $this->enc_password);
			}

			$this->sql_query->execute();


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
	function delete_user($user_id)
	{
		$this->check_level = array(9);
		if (isset($user_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_USERS . ' WHERE id=:id');
				$this->sql->bindParam(':id', $user_id, PDO::PARAM_INT);
				$this->sql->execute();
			}
		}
	}

	/**
	 * Mark the user as active or inactive.
	 */
	function change_user_active_status($user_id,$change_to)
	{
		$this->check_level = array(9);
		if (isset($user_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $this->dbh->prepare('UPDATE ' . TABLE_USERS . ' SET active=:active_state WHERE id=:id');
				$this->sql->bindParam(':active_state', $change_to, PDO::PARAM_INT);
				$this->sql->bindParam(':id', $user_id, PDO::PARAM_INT);
				$this->sql->execute();
			}
		}
	}

}
