<?php
/**
 * Class that handles all the server-side form validations.
 *
 * Every generated error is added as an element to a list that will be returned
 * if any error was found.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

namespace ProjectSend\Classes;

class Validation
{
    private $dbh;
    private $errors = [];

	public function __construct() {
		global $dbh;
		$this->dbh = $dbh;
		$this->allowed_upper	= range('A','Z');
		$this->allowed_lower	= range('a','z');
		$this->allowed_numbers	= array('0','1','2','3','4','5','6','7','8','9');
        $this->allowed_symbols	= array('`','!','"','?','$','%','^','&','*','(',')','_','-','+','=','{','[','}',']',':',';','@','~','#','|','<',',','>','.',"'","/",'\\');
    }
    
    private function addError($error)
    {
        $this->errors[] = $error;
    }

	/** Check if the field is complete */
	private function is_complete($field, $err)
	{
		if (strlen(trim($field)) == 0) {
			$this->addError($err);
		}
	}

	/** Check if the field value is a valid e-mail address */
	private function is_email($field, $err)
	{
		if (!filter_var($field, FILTER_VALIDATE_EMAIL)) {
			$this->addError($err);
		}
	}

	/** Check if the field value is alphanumeric */
	private function is_alpha($field, $err)
	{
		if(preg_match('/[^0-9A-Za-z]/', $field)) {
			$this->addError($err);
		}
	}

	/** Check if the field value is a number */
	private function is_number($field, $err)
	{
		if(preg_match('/[^0-9]/', $field)) {
			$this->addError($err);
		}
	}

	/** Check if the field value is alphanumeric */
	private function is_alpha_or_dot($field, $err)
	{
		if(preg_match('/[^0-9A-Za-z.]/', $field)) {
			$this->addError($err);
		}
	}

	/** Check if the password field value contains invalid characters */
	private function is_password($field, $err)
	{
		$allowed_characters = array_merge($this->allowed_numbers,$this->allowed_lower,$this->allowed_upper,$this->allowed_symbols);

		$passw = str_split($field);
		$char_errors = 0;
		foreach ($passw as $p) {
			if(!in_array($p,$allowed_characters, TRUE)) {
				$char_errors++;
			}
		}
		if ($char_errors > 0) {
			$this->addError($err);
		}
	}

	/** Check if the password meets the characters requirements */
	private function is_password_rules($field, $err)
	{
		$rules	= array(
						'lower'		=> array(
											'value'	=> PASS_REQUIRE_UPPER,
											'chars'	=> $this->allowed_lower,
										),
						'upper'		=> array(
											'value'	=> PASS_REQUIRE_LOWER,
											'chars'	=> $this->allowed_upper,
										),
						'number'	=> array(
											'value'	=> PASS_REQUIRE_NUMBER,
											'chars'	=> $this->allowed_numbers,
										),
						'special'	=> array(
											'value'	=> PASS_REQUIRE_SPECIAL,
											'chars'	=> $this->allowed_symbols,
										),
					);
	
		$rules_active = array();
		foreach ( $rules as $rule => $data ) {
			if ( $data['value'] == '1' ) {
				$rules_active[$rule] = $data['chars'];
			}
		}
		
		if ( count( $rules_active ) > 0 ) {
			$passw = str_split($field);
			$char_errors = 0;

			foreach ( $rules_active as $rule => $characters ) {
				$found = 0;
				foreach ( $characters as $character ) {
					if ( strpos( $field, $character ) !== false) {
						$found++;
					}
				}
				if ( $found === 0 ) {
					$char_errors++;
				}
			}

			if ($char_errors > 0) {
				$this->addError($err);
			}
		}
	}

	/** Check if the character count is within range */
	private function is_length($field, $err, $min, $max)
	{
		if(strlen($field) < $min || strlen($field) > $max){
			$this->addError($err);
		}
	}

	/** Check if both password fields match */
	function is_pass_match($err, $pass1, $pass2)
	{
		if($pass1 != $pass2) {
			$this->addError($err);
		}
	}

	/**
	 * Check if the supplied username already exists on either a client or
	 * a system user.
	 */
	private function is_user_exists($field, $err)
	{
		$this->statement = $this->dbh->prepare( "SELECT * FROM " . TABLE_USERS . " WHERE user = :user" );
		$this->statement->execute(
							array(
								':user'	=> $field,
							)
						);

		if ( $this->statement->rowCount() > 0 ) {
			$this->addError($err);
		}
	}

	/**
	 * Check if the supplied e-mail address already is already assigned to 
	 * either a client or a system user.
	 */
	private function is_email_exists($field, $err, $current_id)
	{
		$this->sql_users = "SELECT id, email FROM " . TABLE_USERS . " WHERE email = :email";
		$this->params = array(
							':email'	=> $field
						);
		/**
		 * If the ID parameter is set, the validation is used when editing
		 * a client or user, and prevents an error if the current user is
		 * the owner of that e-mail address.
		 */
		if (!empty($current_id)) {
			$this->sql_not_this	= " AND id != :id";
			$this->sql_users	.= $this->sql_not_this;
			$this->params[':id'] = $current_id;
		}

		$this->statement = $this->dbh->prepare( $this->sql_users );
		$this->statement->execute( $this->params );
		if ( $this->statement->rowCount() > 0 ) {
			$this->addError($err);
		}
	}

	/** Check if the recaptcha response is ok */
	private function recaptcha_verify($field, $err)
	{
		if( !strstr($field, "true" ) ) {
			$this->addError($err);
		}
	}


	/** Call the requested method and pass the corresponding values */
	function validate($val_type, $field, $err='', $min='', $max='', $pass1='', $pass2='', $row='', $current_id='')
	{
		switch($val_type) {
			case 'completed':
				$this->is_complete($field, $err);
			break;
			case 'email':
				$this->is_email($field, $err);
			break;
			case 'alpha':
				$this->is_alpha($field, $err);
			break;
			case 'number':
				$this->is_number($field, $err);
			break;
			case 'alpha_dot':
				$this->is_alpha_or_dot($field, $err);
			break;
			case 'password':
				$this->is_password($field, $err);
			break;
			case 'pass_rules':
				$this->is_password_rules($field, $err);
			break;
			case 'length':
				$this->is_length($field, $err, $min, $max);
			break;
			case 'pass_match':
				$this->is_pass_match($err, $pass1, $pass2);
			break;
			case 'user_exists':
				$this->is_user_exists($field, $err);
			break;
			case 'email_exists':
				$this->is_email_exists($field, $err, $current_id);
			break;
			case 'recaptcha':
				$this->recaptcha_verify($field, $err);
			break;
		}
    }
    
    public function passed()
    {
        if (!empty($this->errors)) {
            return false;
        }

        return true;
    }

	/**
	 * If errors were found, concatenate the container div (defined above) and the
	 * returned errors.
	 */
	function list_errors()
	{
        $this->validation_errors_title = __('The following errors were found','cftp_admin');
        $this->before_error = '<div class="alert alert-danger alert-block">
                            <a href="#" class="close" data-dismiss="alert">&times;</a>
                            <p class="alert-title">'.$this->validation_errors_title.':</p>
                            <ol>';
        $this->after_error = '</ol>
                        </div>';

		if (!empty($this->errors)) {
            $this->return = $this->before_error;
            foreach ($this->errors as $error) {
                $this->return .= "<li>".$error."</li>";
            }
            $this->return .= $this->after_error;

            $this->errors = [];
            
            return $this->return;
		}
	}
}