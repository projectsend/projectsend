<?php
/**
 * Class that handles all the server-side form validations.
 *
 * Every generated error is added as an element to a list that will be returned
 * if any error was found.
 */

namespace ProjectSend\Classes;

class Validation
{
    private $dbh;
    private $errors = [];

    protected $allowed_upper;
    protected $allowed_lower;
    protected $allowed_numbers;
    protected $allowed_symbols;

    public function __construct() {
        global $dbh;
        $this->dbh = $dbh;
        $this->allowed_upper = range('A','Z');
        $this->allowed_lower = range('a','z');
        $this->allowed_numbers = array('0','1','2','3','4','5','6','7','8','9');
        $this->allowed_symbols = array('`','!','"','?','$','%','^','&','*','(',')','_','-','+','=','{','[','}',']',':',';','@','~','#','|','<',',','>','.',"'","/",'\\');
    }
    
    private function addError($error)
    {
        $this->errors[] = $error;
    }

    /** Call the requested method and pass the corresponding values */
    function validate_items($items = [])
    {
        if (empty($items)) {
            return false;
        }

        foreach ($items as $value => $validations) {
            foreach ($validations as $method => $data) {
                if (empty($method)) { $method = $data; } // In case we only specify the method with no data array
                if (!$this->{$method}($value, $data)) {
                    $error = (!empty($data['error'])) ? $data['error'] : $this->getDefaultError($method, $value);
                    $this->addError($error);
                }
            }
        }

        return $this->passed();
    }

    private function getDefaultError($method, $value) {
        return sprintf(__('"<em>%s</em>" is not valid for validation type <em>%s</em>' ,'cftp_admin'), $value, $method);
    }

    // Methods
    private function required($value)
    {
        // From rakit/validation
        if (is_string($value)) {
            return mb_strlen(trim($value), 'UTF-8') > 0;
        }
        if (is_array($value)) {
            return count($value) > 0;
        }
        return !is_null($value);
    }

    private function email($value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function alpha($value)
    {
        return (preg_match('/[^0-9A-Za-z]/', $value) != true);
    }

    private function number($value)
    {
        return (preg_match('/[^0-9]/', $value) != true);
    }

    private function alpha_or_dot($value)
    {
        return (preg_match('/[^0-9A-Za-z.]/', $value) != true);
    }

    private function alpha_underscores($value)
    {
        return (preg_match('/[^0-9A-Za-z.]/', $value) != true);
    }

    private function password($value)
    {
        $allowed_characters = array_merge($this->allowed_numbers,$this->allowed_lower,$this->allowed_upper,$this->allowed_symbols);

        $passw = str_split($value);
        $char_errors = 0;
        foreach ($passw as $p) {
            if (!in_array($p,$allowed_characters, true)) {
                $char_errors++;
            }
        }
        return ($char_errors == 0);
    }

    private function password_rules($value)
    {
        $rules = [
            'lower' => [
                'value'	=> get_option('pass_require_lower'),
                'chars'	=> $this->allowed_lower,
            ],
            'upper' => [
                'value'	=> get_option('pass_require_upper'),
                'chars'	=> $this->allowed_lower,
            ],
            'number' => [
                'value'	=> get_option('pass_require_number'),
                'chars'	=> $this->allowed_lower,
            ],
            'special' => [
                'value'	=> get_option('pass_require_special'),
                'chars'	=> $this->allowed_lower,
            ],
        ];
    
        $rules_active = [];
        foreach ( $rules as $rule => $data ) {
            if ( $data['value'] == '1' ) {
                $rules_active[$rule] = $data['chars'];
            }
        }
        
        if ( count( $rules_active ) > 0 ) {
            // $passw = str_split($value);
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

            return ($char_errors == 0);
        }

        // default true when no rules are active
        return true;
    }

    private function length($value, $data = [])
    {
        return (strlen($value) >= $data['min'] && strlen($value) <= $data['max']);
    }

    private function matches($value, $data)
    {
        return ($value == $data['matches']);
    }

    // private function matches_strict($value, $data)
    // {
    //     return ($value === $data['matches']);
    // }

    private function user_exists($value)
    {
        $statement = $this->dbh->prepare( "SELECT user FROM " . TABLE_USERS . " WHERE user = :user" );
        $statement->execute([
            ':user' => $value,
        ]);

        return ($statement->rowCount() == 0);
    }

    private function email_exists($value, $data = [])
    {
        $query = "SELECT id, email FROM " . TABLE_USERS . " WHERE email = :email";
        $params = [
            ':email' => $value
        ];
        /**
         * If the ID parameter is set, the validation is used when editing
         * a client or user, and prevents an error if the current user is
         * the owner of that e-mail address.
         */
        if (!empty($data['id_ignore'])) {
            $query .= " AND id != :id";
            $params[':id'] = $data['id_ignore'];
        }

        $statement = $this->dbh->prepare( $query );
        $statement->execute( $params );

        return ($statement->rowCount() == 0);
    }

    private function recaptcha2($value)
    {
        return (strstr($value, "true"));
    }

    private function in_enum($value, $data = ['valid_values' => []])
    {
        return (in_array($value, $data['valid_values']));
    }    

    public function passed()
    {
        if (empty($this->errors)) {
            return true;
        }

        return false;
    }

    /**
     * If errors were found, concatenate the container div (defined above) and the
     * returned errors.
     */
    function list_errors($wrapper = true)
    {
        $before_error = '<div class="alert alert-danger alert-block">
                            <a href="#" class="close" data-dismiss="alert">&times;</a>
                            <p class="alert-title">'.__('The following errors were found','cftp_admin').':</p>
                            <ol>';
        $after_error = '</ol>
                        </div>';
        if ($wrapper == false) {
            $before_error = '<ul>';
            $after_error = '</ul>';
        }

        if (!empty($this->errors)) {
            $return = $before_error;

            foreach ($this->errors as $error) {
                $return .= "<li>".$error."</li>";
            }

            $return .= $after_error;

            // $this->errors = [];
            
            return $return;
        }
    }
}