<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * clients accounts.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

namespace ProjectSend;
use PDO;

class ClientActions
{

	var $client = '';

	function __construct() {
		global $dbh;
		$this->dbh = $dbh;
	}

	/**
	 * Validate the information from the form.
	 */
	function validate_client($arguments)
	{
		global $validation, $json_strings;
		$this->state = array();

		$this->id = $arguments['id'];
		$this->name = $arguments['name'];
		$this->email = $arguments['email'];
		$this->password = $arguments['password'];
		//$this->password_repeat = $arguments['password_repeat'];
		$this->address = $arguments['address'];
		$this->phone = $arguments['phone'];
		$this->contact = $arguments['contact'];
		$this->notify_account = $arguments['notify_account'];
		$this->notify_upload = $arguments['notify_upload'];
		$this->max_file_size = ( !empty( $arguments['max_file_size'] ) ) ? $arguments['max_file_size'] : 0;
		$this->type = $arguments['type'];
		$this->recaptcha = ( isset( $arguments['recaptcha'] ) ) ? $arguments['recaptcha'] : '';

		/**
		 * These validations are done both when creating a new client and
		 * when editing an existing one.
		 */
		$validation->validate('completed',$this->name,$json_strings['validation']['no_name']);
		$validation->validate('completed',$this->email,$json_strings['validation']['no_email']);
		$validation->validate('email',$this->email,$json_strings['validation']['invalid_email']);
		$validation->validate('number',$this->max_file_size,$json_strings['validation']['file_size']);

		/**
		 * Validations for NEW CLIENT submission only.
		 */
		if ($this->type == 'new_client') {
			$this->username = $arguments['username'];

			$validation->validate('email_exists',$this->email,$json_strings['validation']['email_exists']);
			/** Username checks */
			$validation->validate('user_exists',$this->username,$json_strings['validation']['user_exists']);
			$validation->validate('completed',$this->username,$json_strings['validation']['no_user']);
			$validation->validate('alpha_dot',$this->username,$json_strings['validation']['alpha_user']);
			$validation->validate('length',$this->username,$json_strings['validation']['length_user'],MIN_USER_CHARS,MAX_USER_CHARS);

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
			$validation->validate('email_exists',$this->email,$json_strings['validation']['email_exists'],'','','','','',$this->id);
		}

		/** Password checks */
		if (isset($this->validate_password) && $this->validate_password === true) {
			$validation->validate('completed',$this->password,$json_strings['validation']['no_pass']);
			$validation->validate('password',$this->password,$json_strings['validation']['valid_pass'] . " " . addslashes($json_strings['validation']['valid_chars']));
			$validation->validate('pass_rules',$this->password,$json_strings['validation']['rules_pass']);
			$validation->validate('length',$this->password,$json_strings['validation']['length_pass'],MIN_PASS_CHARS,MAX_PASS_CHARS);
			//$validation->validate('pass_match','',$json_strings['validation']['match_pass'],'','',$this->password,$this->password_repeat);
		}

		if ( !empty($this->recaptcha) ) {
			$validation->validate('recaptcha',$this->recaptcha,$json_strings['validation']['recaptcha']);
		}

		if ($validation->return_val) {
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
		$this->state = array();

		/** Define the account information */
		$this->id					= $arguments['id'];
		$this->name					= encode_html($arguments['name']);
		$this->email				= encode_html($arguments['email']);
		$this->username			= encode_html($arguments['username']);
		$this->password			= $arguments['password'];
		//$this->password_repeat = $arguments['password_repeat'];
		$this->address				= encode_html($arguments['address']);
		$this->phone				= encode_html($arguments['phone']);
		$this->contact				= encode_html($arguments['contact']);
		$this->notify_upload   	= ( $arguments['notify_upload'] == '1' ) ? 1 : 0;
		$this->notify_account  	= ( $arguments['notify_account'] == '1' ) ? 1 : 0;
		$this->max_file_size		= ( !empty( $arguments['max_file_size'] ) ) ? $arguments['max_file_size'] : 0;
		$this->request				= ( !empty( $arguments['account_requested'] ) ) ? $arguments['account_requested'] : 0;
		$this->active				= ( $arguments['active'] );
		$this->enc_password		= password_hash($this->password, PASSWORD_DEFAULT, [ 'cost' => HASH_COST_LOG2 ]);
		//$this->enc_password		= $hasher->HashPassword($this->password);

		if (strlen($this->enc_password) >= 20) {

			$this->state['hash'] = 1;

			/** Who is creating the client? */
			$this->this_admin = get_current_user_username();

			/** Insert the client information into the database */
			$this->timestamp = time();
			$this->sql_query = $this->dbh->prepare("INSERT INTO " . TABLE_USERS . " (name,user,password,address,phone,email,notify,contact,created_by,active,account_requested,max_file_size)"
												."VALUES (:name, :username, :password, :address, :phone, :email, :notify, :contact, :admin, :active, :request, :max_file_size)");
			$this->sql_query->bindParam(':name', $this->name);
			$this->sql_query->bindParam(':username', $this->username);
			$this->sql_query->bindParam(':password', $this->enc_password);
			$this->sql_query->bindParam(':address', $this->address);
			$this->sql_query->bindParam(':phone', $this->phone);
			$this->sql_query->bindParam(':email', $this->email);
			$this->sql_query->bindParam(':notify', $this->notify_upload, PDO::PARAM_INT);
			$this->sql_query->bindParam(':contact', $this->contact);
			$this->sql_query->bindParam(':admin', $this->this_admin);
			$this->sql_query->bindParam(':active', $this->active, PDO::PARAM_INT);
			$this->sql_query->bindParam(':request', $this->request, PDO::PARAM_INT);
			$this->sql_query->bindParam(':max_file_size', $this->max_file_size, PDO::PARAM_INT);

			$this->sql_query->execute();

			if ($this->sql_query) {
				$this->state['actions']	= 1;
				$this->state['new_id']	= $this->dbh->lastInsertId();

				/** Send account data by email */
				$this->notify_client = new EmailsPrepare();
				$this->email_arguments = array(
												'type'		=> 'new_client',
												'address'	=> $this->email,
												'username'	=> $this->username,
												'password'	=> $this->password
											);
				if ($this->notify_account == 1) {
					$this->notify_send = $this->notify_client->send($this->email_arguments);

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
		global $dbh;
		$this->state = array();

		/** Define the account information */
		$this->id					= $arguments['id'];
		$this->name					= encode_html($arguments['name']);
		$this->password			= $arguments['password'];
		$this->email				= encode_html($arguments['email']);
		$this->address				= encode_html($arguments['address']);
		$this->phone				= encode_html($arguments['phone']);
		$this->contact				= encode_html($arguments['contact']);
		$this->notify_upload		= ( $arguments['notify_upload'] == '1' ) ? 1 : 0;
		$this->active				= ( $arguments['active'] == '1' ) ? 1 : 0;
		$this->max_file_size		= ( !empty( $arguments['max_file_size'] ) ) ? $arguments['max_file_size'] : 0;
		$this->enc_password		= password_hash($this->password, PASSWORD_DEFAULT, [ 'cost' => HASH_COST_LOG2 ]);
		//$this->enc_password		= $hasher->HashPassword($arguments['password']);

		if (strlen($this->enc_password) >= 20) {

			$this->state['hash'] = 1;

			/** SQL query */
			$this->edit_client_query = "UPDATE " . TABLE_USERS . " SET
										name = :name,
										address = :address,
										phone = :phone,
										email = :email,
										contact = :contact,
										notify = :notify,
										active = :active,
										max_file_size = :max_file_size
										";

			/** Add the password to the query if it's not the dummy value '' */
			if (!empty($arguments['password'])) {
				$this->edit_client_query .= ", password = :password";
			}

			$this->edit_client_query .= " WHERE id = :id";


			$this->sql_query = $this->dbh->prepare( $this->edit_client_query );
			$this->sql_query->bindParam(':name', $this->name);
			$this->sql_query->bindParam(':address', $this->address);
			$this->sql_query->bindParam(':phone', $this->phone);
			$this->sql_query->bindParam(':email', $this->email);
			$this->sql_query->bindParam(':contact', $this->contact);
			$this->sql_query->bindParam(':notify', $this->notify_upload, PDO::PARAM_INT);
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
	 * Delete an existing client.
	 */
	function delete_client($client_id) {
		$this->check_level = array(9,8);
		if (isset($client_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_USERS . ' WHERE id=:id');
				$this->sql->bindValue(':id', $client_id, PDO::PARAM_INT);
				$this->sql->execute();
			}
		}
	}

	/**
	 * Mark the client as active or inactive.
	 */
	function change_client_active_status($client_id,$change_to)
	{
		$this->check_level = array(9,8);
		if (isset($client_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $this->dbh->prepare('UPDATE ' . TABLE_USERS . ' SET active=:active_state WHERE id=:id');
				$this->sql->bindValue(':active_state', $change_to, PDO::PARAM_INT);
				$this->sql->bindValue(':id', $client_id, PDO::PARAM_INT);
				$this->status = $this->sql->execute();
			}
		}
		else {
			$this->status = false;
		}

		return $this->status;
	}

	/**
	 * Approve account
	 */
	function client_account_approve($client_id)
	{
		$this->check_level = array(9,8);
		if (isset($client_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $this->dbh->prepare('UPDATE ' . TABLE_USERS . ' SET active=:active, account_requested=:requested, account_denied=:denied WHERE id=:id');
				$this->sql->bindValue(':active', 1, PDO::PARAM_INT);
				$this->sql->bindValue(':requested', 0, PDO::PARAM_INT);
				$this->sql->bindValue(':denied', 0, PDO::PARAM_INT);
				$this->sql->bindValue(':id', $client_id, PDO::PARAM_INT);
				$this->status = $this->sql->execute();
			}
		}
		else {
			$this->status = false;
		}

		return $this->status;
	}

	/**
	 * Deny account
	 */
	function client_account_deny($client_id)
	{
		$this->check_level = array(9,8);
		if (isset($client_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $this->dbh->prepare('UPDATE ' . TABLE_USERS . ' SET active=:active, account_requested=:account_requested, account_denied=:account_denied WHERE id=:id');
				$this->sql->bindValue(':active', 0, PDO::PARAM_INT);
				$this->sql->bindValue(':account_requested', 1, PDO::PARAM_INT);
				$this->sql->bindValue(':account_denied', 1, PDO::PARAM_INT);
				$this->sql->bindValue(':id', $client_id, PDO::PARAM_INT);
				$this->status = $this->sql->execute();
			}
		}
		else {
			$this->status = false;
		}

		return $this->status;
	}

    /**
     * Used on social login account creation
     */
	public function generateUsername($string, $i = 1) {
		$string = preg_replace('/[^A-Za-z0-9]/', "", $string);
		$username = $string;
		while(!$this->isUniqueUsername($username)) {
			$username = $string . $i;
			$i++;
		}
		return $username;
	}

    /**
     * Check if a username already exists. Complement for the previous function.
     */
    private function isUniqueUsername($string) {
		$statement = $this->dbh->prepare( "SELECT * FROM " . TABLE_USERS . " WHERE user = :user" );
		$statement->execute(array(':user'	=> $string));
		if($statement->rowCount() > 0) {
			return false;
		}
		return true;
	}

}
