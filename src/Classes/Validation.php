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

class Validation extends Base
{
    // Array of error messages
    private $errors = [];

     // Source array. Could be set up manually or $_POST for example.
    private $values = [];

    // Simply true or false with the end state of the whole validation
    private $passed;

    // Error message strings
    private $messages;

	public function __construct($values_to_test, $messages_source) {
		$this->allowed_upper	= range('A','Z');
		$this->allowed_lower	= range('a','z');
		$this->allowed_numbers	= array('0','1','2','3','4','5','6','7','8','9');
        $this->allowed_symbols	= array('`','!','"','?','$','%','^','&','*','(',')','_','-','+','=','{','[','}',']',':',';','@','~','#','|','<',',','>','.',"'","/",'\\');

        $this->values = $values_to_test;
        $this->messages = $messages_source;

        $errors = [];
    }

    /**
     * Apply all validations defined on the $fields argument.
     * 
     * Each field contains a list of validations to do.
     * In turn, each validation has a text message to return as the error
     * and optional arguments required by certain validation types.
     * 
     * Argument $values could be a manually set up array or just $_POST, for example.
     * 
     * Argument $validations is an array of what to check, and should look like this:
     * $fields = [
     *     'field_name' => [
     *          'validation_type' => [
     *              'message', 'arguments',
     *          ]
     *      ]
     * ]
     * 
     * Each method would use $field_name to get the value from the $values array
     * 
     * @return void
     */
    public function make( $validations )
    {
        if ( empty( $this->values ) ) {
            return;
        }

        if ( !is_array($validations) ) {
            return;
        }

        foreach ( $validations as $field_name => $validations ) {
            // Extract arguments
            if ( !is_array( $validations ) )
                continue;

            foreach ( $validations as $type => $arguments ) {
                // $validation type should exists as a method
                if ( method_exists( $this, $type ) ) {
                    $this->validate_field( $type, $field_name, $arguments );
                }
            }
        }

        return $this;
    }

    /**
     * Does some checks and redirects to the corresponding method
     */
    public function validate_field( $type, $field_name, $arguments )
    {
        if ( !$type || !$field_name ) {
            return;
        }
            
        if ( !method_exists( $this, $type ) ) {
            return;
        }

        if ( isset( $arguments['message'] ) ) {
            $this->message = html_output( $arguments['message'] );
        }
        else {
            $this->message = sprintf( $this->messages['default'], $type, $field_name );
        }

        // Do validation
        $this->value = $this->values[$field_name];
        $this->arguments = ( !empty( $arguments ) ) ? $arguments : array();

        // If validation result == false:
        if ( !$this->$type( $this->value, $this->arguments ) ) {
            $this->add_error( $field_name, $this->message );
        }
    }

    /**
     * Called after each validation fail. For now it just adds to the array.
     *
     * @param string $error
     * @return void
     */
    protected function add_error($field_name, $error)
    {
        $this->errors[$field_name][] = $error;
    }

	/**
	 * If errors were found, concatenate the container div (defined above) and the
	 * returned errors.
	 */
	public function errors_formatted()
	{
        /**
         * Prepare the error message mark up and content
         */
        $error_format = '<div class="alert alert-danger alert-block">
                            <p class="alert-title">%s:</p>
                            <ol>
                                %s
                            </ol>
                        </div>';

        if (!empty($this->errors)) {
            $this->error_list = '';
            foreach ( $this->errors as $this->field => $this->error_messages ) {
                foreach ( $this->error_messages as $this->error_message ) {
                    $this->error_list .= '<li>' . $this->error_message . '</li>';
                }
            }

            /** Create the message to be returned */
			$this->error_list_formatted = sprintf( $error_format, $this->messages['errors_found_title'], $this->error_list );

            return $this->error_list_formatted;
		}

        return;
    }
    
    public function errors_json()
    {
        $this->error_list_json = json_encode( $this->errors );
        return $this->error_list_json;
    }


    public function validation_passed()
    {
        if ( !empty( $this->errors ) ) {
            $this->passed = false;
        }
        else {
            $this->passed = true;
        }

        return $this->passed;
    }













    // AVAILABLE VALIDATION METHODS
	/** Check if the field is complete */
	protected function is_complete($value)
	{
		if (strlen(trim($value)) == 0) {
			return false;
        }
        
        return true;
	}

	/** Check if the field value is a valid e-mail address */
	protected function is_email($value)
	{
		if (!preg_match("/^[^@]+@[a-zA-Z0-9._-]+\.[a-zA-Z]+$/", $value)) {
			return false;
		}
        
        return true;
	}

	/** Check if the field value is alphanumeric */
	protected function is_alpha($value)
	{
		if (is_string($value) && preg_match('/[^0-9A-Za-z]/', $value)) {
			return false;
		}
        
        return true;
	}

	/** Check if the field value is a number */
	protected function is_number($value)
	{
        (int)$value;

		if (!is_integer($value) && !preg_match('/[^0-9]/', $value)) {
			return false;
		}
        
        return true;
	}

	/** Check if the field value is alphanumeric */
	protected function is_alpha_or_dot($value)
	{
		if (preg_match('/[^0-9A-Za-z.]/', $value)) {
			return false;
		}
        
        return true;
	}

	/** Check if the password field value contains invalid characters */
	protected function is_password($value)
	{
		$allowed_characters = array_merge( $this->allowed_numbers, $this->allowed_lower, $this->allowed_upper, $this->allowed_symbols );

		$passw = str_split($value);
		$char_errors = 0;
		foreach ($passw as $p) {
			if ( !in_array( $p,$allowed_characters, true ) ) {
				$char_errors++;
			}
		}

        if ($char_errors > 0) {
			return false;
		}
        
        return true;
	}

	/** Check if the password meets the characters requirements */
	protected function password_meets_rules($value)
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
			$passw = str_split($value);
			$char_errors = 0;

			foreach ( $rules_active as $rule => $characters ) {
				$found = 0;
				foreach ( $characters as $character ) {
					if ( strpos( $value, $character ) !== false) {
						$found++;
					}
				}
				if ( $found === 0 ) {
					$char_errors++;
				}
			}

			if ($char_errors > 0) {
				return false;
			}
		}
        
        return true;
	}

    /** Check if the character count is within range */
    /**
     * @todo min and max values should be reflected on the error message
     */
	protected function is_length($value, $arguments)
	{
        $min = ( isset( $arguments['min'] ) ) ? $arguments['min'] : 0;
        $max = ( isset( $arguments['max'] ) ) ? $arguments['max'] : 0;

        if ( $min > 0 && strlen($value) < $min ) {
            return false;
        }
        if ( $max > 0 && strlen($value) > $max ) {
            return false;
        }
        
        return true;
	}

    /** Check if both password fields match */
    /*
	protected function pass_match($err, $pass1, $pass2)
	{
        $min = ( isset( $arguments['min'] ) ) ? $arguments['min'] : 0;
        $max = ( isset( $arguments['max'] ) ) ? $arguments['max'] : 0;

        if ($pass1 != $pass2) {
			$this->add_error($err);
			return false;
		}
        
        return true;
    }
    */

	/**
	 * Check if the supplied username already exists on either a client or
	 * a system user.
     * @todo use get_user_by()
	 */
	protected function user_exists($value)
	{
		$this->statement = $this->dbh->prepare( "SELECT * FROM " . TABLE_USERS . " WHERE username = :user" );
		$this->statement->execute(
							array(
								':user'	=> $value,
							)
						);

		if ( $this->statement->rowCount() > 0 ) {
			return false;
		}
        
        return true;
	}

	/**
	 * Check if the supplied e-mail address already is already assigned to 
	 * either a client or a system user.
     * @todo use get_user_by()
	 */
	protected function email_exists($value, $arguments)
	{
        $current_id = ( isset( $arguments['id'] ) ) ? $arguments['id'] : 0;

        $this->sql_users = "SELECT id, email FROM " . TABLE_USERS . " WHERE email = :email";
		$this->params = array(
							':email'	=> $value
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
			return false;
		}
        
        return true;
	}

	/** Check if the recaptcha response is ok */
	protected function recaptcha_verify($value)
	{
		if ( !strstr($value, "true" ) ) {
			return false;
		}
        
        return true;
    }
}