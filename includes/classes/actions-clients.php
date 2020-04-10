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

	function __construct() {
		global $dbh;
		$this->dbh = $dbh;
	}

	public function generateUsername($string, $i = 1) {
		$string = preg_replace('/[^A-Za-z0-9]/', "", $string);
		$username = $string;
		while(!$this->isUniqueUsername($username)) {
			$username = $string . $i;
			$i++;
		}
		return $username;
	}

	private function isUniqueUsername($string) {
		$statement = $this->dbh->prepare( "SELECT * FROM " . TABLE_USERS . " WHERE user = :user" );
		$statement->execute(array(':user'	=> $string));
		if($statement->rowCount() > 0) {
			return false;
		}
		return true;
	}

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
		if(isset($arguments['recaptcha'])){
			$this->recaptcha = $arguments['recaptcha'];
		}

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
			// $valid_me->validate('alpha_dot',$this->username,$validation_alpha_user);
			$valid_me->validate('length',$this->username,$validation_length_user,MIN_USER_CHARS,MAX_USER_CHARS);
			
			$this->validate_password = true;
		}
		/**
		 * Validations for CLIENT EDITING only.
		 */
		else if ($this->type == 'edit_client') {
			$this->username = $arguments['username'];
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

		if ( isset($this->recaptcha) ) {
			$valid_me->validate('recaptcha',$this->recaptcha,$validation_recaptcha);
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
		$this->state = array();
		/** Define the account information */
		$this->id			= $arguments['id'];
		$this->name			= $arguments['name'];
		$this->email		= $arguments['email'];
		$this->username		= $arguments['username'];
		$this->password		= $arguments['password'];
		//$this->password_repeat = $arguments['password_repeat'];
		$this->address		= $arguments['address'];
		$this->address2		= $arguments['address2'];
		$this->city			= $arguments['city'];
		$this->state_c		= $arguments['state'];
		$this->zipcode		= $arguments['zipcode'];
		$this->phone		= $arguments['phone'];
		$this->level		= $arguments['level'];
		$this->groupid		= (isset($arguments['groupid'])) ? $arguments['groupid'] : '';
		$this->contact		= $arguments['contact'];
		$this->notify		= ( $arguments['notify'] == '1' ) ? 1 : 0;
		$this->active		= ( $arguments['active'] == '1' ) ? 1 : 0;
		$this->enc_password	= $hasher->HashPassword($this->password);
		if (strlen($this->enc_password) >= 20) {

			$this->state['hash'] = 1;

			/** Who is creating the client? */
			$this->this_admin = get_current_user_username();
	
			/** Insert the client information into the database */
			$this->timestamp = time();
			$query = "INSERT INTO " . TABLE_USERS . " (name,user,password,address,address2,city,state,zipcode,phone,level,email,notify,contact,created_by,active)"
												."VALUES (:name, :username, :password, :address, :address2, :city, :state, :zipcode, :phone, :level, :email, :notify, :contact, :admin, :active)";
			//echo $query;exit;
			$this->sql_query = $this->dbh->prepare($query);
							
			$this->sql_query->bindParam(':name', $this->name);
			$this->sql_query->bindParam(':username', $this->username);
			$this->sql_query->bindParam(':password', $this->enc_password);
			$this->sql_query->bindParam(':address', $this->address);
			$this->sql_query->bindParam(':address2', $this->address2);
			$this->sql_query->bindParam(':city', $this->city);
			$this->sql_query->bindParam(':state', $this->state_c);
			$this->sql_query->bindParam(':zipcode', $this->zipcode);
			$this->sql_query->bindParam(':phone', $this->phone);
			$this->sql_query->bindParam(':level', $this->level);
			$this->sql_query->bindParam(':email', $this->email);
			$this->sql_query->bindParam(':notify', $this->notify, PDO::PARAM_INT);
			$this->sql_query->bindParam(':contact', $this->contact);
			$this->sql_query->bindParam(':admin', $this->this_admin);
			$this->sql_query->bindParam(':active', $this->active, PDO::PARAM_INT);
			$this->sql_query->execute();
			if ($this->sql_query) {
				$this->state['actions']	= 1;
				$this->state['new_id']	= $this->dbh->lastInsertId();
// Signature feature start

				if($this->id!='' && $this->id!=null){
					$old_name = UPLOADED_FILES_FOLDER.'../../img/avatars/tempsignature/'.$this->state['new_id'].'.png' ;

					$fl_name = $this->state['new_id'].'.png';
					$isexit = $this->dbh->prepare("SELECT * FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id=:user_id AND name = :name");
					$isexit->bindParam(':user_id', $this->state['new_id'], PDO::PARAM_INT);
					$isexit->bindValue(':name', 'signature_pic');
					$isexit->execute();
					$isexit->setFetchMode(PDO::FETCH_ASSOC);
					$data = $isexit->fetch();
					if(!empty($data)){
						$statement = $this->dbh->prepare("DELETE FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id =".$this->state['new_id']." AND name='signature_pic'");
					$statement->execute();
					$statement1 = $this->dbh->prepare( "INSERT INTO " . TABLE_USER_EXTRA_PROFILE . " (user_id, name, value,sig_type) VALUES (".$this->state['new_id'].",'signature_pic','".$fl_name."',2 ) ");
					$statement1=$statement1->execute();
					}else{
					    $statement1 = $this->dbh->prepare( "INSERT INTO " . TABLE_USER_EXTRA_PROFILE . " (user_id, name, value,sig_type) VALUES (".$this->state['new_id'].",'signature_pic','".$fl_name."',2 ) ");
						$statement1=$statement1->execute();

					}
				}

// Signature feature end

	
				/** Send account data by email */
						
				$this->notify_client = new PSend_Email();
				$this->email_arguments = array(
												'type'		=> 'new_client',
												'address'	=> $this->email,
												'username'	=> $this->username,
												'password'	=> $this->password
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
				//echo "TWO";
				/** Query couldn't be executed */
				$this->state['actions'] = 0;
			}
		}
		else {
			$this->state['hash'] = 0;
		}
	//print_r($this->state);exit;
		return $this->state;
	}

	

	/**
	 * Edit an existing client.
	 */
	function edit_client($arguments)
	{
		global $hasher;
		global $dbh;

		/** Define the account information */
		$this->id			= $arguments['id'];
		$this->name			= $arguments['name'];
		$this->username			= $arguments['username'];
		$this->email		= $arguments['email'];
		$this->password		= $arguments['password'];
		$this->address		= $arguments['address'];
		$this->address2		= $arguments['address2'];
		$this->city			= $arguments['city'];
		$this->state		= $arguments['state'];
		$this->phone		= $arguments['phone'];
		$this->zipcode		= $arguments['zipcode'];
		$this->contact		= $arguments['contact'];
		$this->notify		= ( $arguments['notify'] == '1' ) ? 1 : 0;
		$this->active		= ( $arguments['active'] == '1' ) ? 1 : 0;
		$this->enc_password	= $hasher->HashPassword($arguments['password']);

		if (strlen($this->enc_password) >= 20) {

			/** SQL query */
			$this->edit_client_query = "UPDATE " . TABLE_USERS . " SET 
										name = :name,
										address = :address,
										address2 = :address2,
										city 	= :city,
										state 	= :state,
										zipcode 	= :zipcode,
										phone = :phone,
										email = :email,
										contact = :contact,
										notify = :notify,
										active = :active
										";
	
			/** Add the password to the query if it's not the dummy value '' */
			if (!empty($arguments['password'])) {
				$this->edit_client_query .= ", password = :password";
			}
	
			$this->edit_client_query .= " WHERE id = :id";


			$this->sql_query = $this->dbh->prepare( $this->edit_client_query );
			$this->sql_query->bindParam(':name', $this->name);
			// $this->sql_query->bindParam(':username', $this->username);
			$this->sql_query->bindParam(':address', $this->address);
			$this->sql_query->bindParam(':address2', $this->address2);
			$this->sql_query->bindParam(':city', $this->city);
			$this->sql_query->bindParam(':state', $this->state);
			$this->sql_query->bindParam(':zipcode', $this->zipcode);
			$this->sql_query->bindParam(':phone', $this->phone);
			$this->sql_query->bindParam(':email', $this->email);
			$this->sql_query->bindParam(':contact', $this->contact);
			$this->sql_query->bindParam(':notify', $this->notify, PDO::PARAM_INT);
			$this->sql_query->bindParam(':active', $this->active, PDO::PARAM_INT);
			$this->sql_query->bindParam(':id', $this->id, PDO::PARAM_INT);
			if (!empty($arguments['password'])) {
				$this->sql_query->bindParam(':password', $this->enc_password);
			}
			$this->sql_query->execute();

			if ($this->sql_query) {
				if($this->id!='' && $this->id!=null){
					$old_name = UPLOADED_FILES_FOLDER.'../../img/avatars/tempsignature/'.$this->id.'.png' ;

					$fl_name = $this->id.'.png';
					$isexit = $this->dbh->prepare("SELECT * FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id=:user_id AND name = :name");
					$isexit->bindParam(':user_id', $this->id, PDO::PARAM_INT);
					$isexit->bindValue(':name', 'signature_pic');
					$isexit->execute();
					$isexit->setFetchMode(PDO::FETCH_ASSOC);
					$data = $isexit->fetch();
					if(!empty($data)){
						$statement = $this->dbh->prepare("DELETE FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id =".$this->id." AND name='signature_pic'");
					$statement->execute();
					$statement1 = $this->dbh->prepare( "INSERT INTO " . TABLE_USER_EXTRA_PROFILE . " (user_id, name, value,sig_type) VALUES (".$this->id.",'signature_pic','".$fl_name."',2 ) ");
					$statement1=$statement1->execute();
					}else{
					    $statement1 = $this->dbh->prepare( "INSERT INTO " . TABLE_USER_EXTRA_PROFILE . " (user_id, name, value,sig_type) VALUES (".$this->id.",'signature_pic','".$fl_name."',2 ) ");
						$statement1=$statement1->execute();

					}
				}
				$this->state['query'] = 1;
			}
			else {
				$this->state['query'] = 0;
			}
	
			// if ($this->sql_query) {
			// 	$this->state['query'] = 1;
			// }
			// else {
			// 	$this->state['query'] = 0;
			// }
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
				
				//Deleting inbox files
				$this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_FILES_RELATIONS . ' WHERE client_id=:cl_id');
				$this->sql->bindParam(':cl_id', $client_id, PDO::PARAM_INT);
				$this->sql->execute();

				// Delete draft
				$this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_FILES .' WHERE tbl_files.id NOT IN(SELECT tbl_files_relations.file_id FROM tbl_files_relations WHERE tbl_files_relations.from_id = '.$client_id.')');
				$this->sql->execute();

				//Collecting user data
				$this->sql = $this->dbh->prepare("SELECT * FROM " . TABLE_USERS ." WHERE id=:id");
				$this->sql->bindParam(':id', $client_id, PDO::PARAM_INT);
				$this->sql->execute();
				$this->sql->setFetchMode(PDO::FETCH_ASSOC);
				$userEmail = $this->sql->fetchAll();

				//Deleting Outbox files
				if(!empty($userEmail['0']['user'])){
					$useName =$userEmail['0']['user'];
					$this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_FILES .' WHERE (uploader=:uploader AND DATE(tbl_files.future_send_date) > DATE(NOW()))');
					$this->sql->bindParam(':uploader', $useName);
					$this->sql->execute();
				}
				if(!empty($userEmail['0']['email'])){
				  $usemail =$userEmail['0']['email'];
					$this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_DROPOFF . ' WHERE (from_id=:cl_id OR to_email ="'.$usemail.'")');
					$this->sql->bindParam(':cl_id', $client_id);
					$this->sql->execute();
				}else {
					$this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_DROPOFF . ' WHERE from_id=:cl_id');
					$this->sql->bindParam(':cl_id', $client_id, PDO::PARAM_INT);
					$this->sql->execute();
				}
				$this->sql = $this->dbh->prepare('DELETE FROM ' . TABLE_USERS . ' WHERE id=:id');
				$this->sql->bindParam(':id', $client_id, PDO::PARAM_INT);
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
				$this->sql->bindParam(':active_state', $change_to, PDO::PARAM_INT);
				$this->sql->bindParam(':id', $client_id, PDO::PARAM_INT);
				$this->sql->execute();
			}
		}
	}

}

?>
