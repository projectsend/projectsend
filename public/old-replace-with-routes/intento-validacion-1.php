<?php
$allowed_levels = array(9,8);
require_once('bootstrap.php');
$type = 'new_client';

$values = array(
    'nada' => '',
    'name' => 'Ignacio',
    'email' => 'infos.co',
    'max_file_size' => 'veinte',
    'username' => 'kakaroto',
    'password' => '$dsaER93m',
);



// Set the strings variable here to Avoid repetition
$strings = $json_strings['validation'];
// Prepare validation fields. These ones are validated during creating and also editing.
$validation_fields = [
    'nada' => [
        'is_complete' => [],
    ],
    'name' => [
        'is_complete' => [
            'message' => $strings['no_name']
        ],
    ],
    'email' => [
        'is_complete' => [
            'message' => $strings['no_email']
        ],
        'is_email' => [
            'message' => $strings['invalid_email']
        ],
    ],
    'max_file_size' => [
        'is_number' => [
            'message' => $strings['file_size']
        ],
    ],
];
// Validations for NEW CLIENT submission only.
if ($type == 'new_client') {
    // Later, check the password
    $validate_password = true;

    $validation_fields['email']['email_exists'] = [
        'message' => $strings['email_exists'],
        'id' => 4,
    ];
    
    $validation_fields['username'] = [
        'user_exists' => [
            'message' => $strings['user_exists']
        ],
        'is_complete' => [
            'message' => $strings['no_user']
        ],
        'is_alpha_or_dot' => [
            'message' => $strings['alpha_user']
        ],
        'is_length' => [
            'message' => $strings['length_user'],
            'min' => MIN_USER_CHARS,
            'max' => MAX_USER_CHARS,
        ],
    ];
}
// Validations for CLIENT EDITING only.
else if ($type == 'edit_client') {
    // Changing password is optional
    if ($arguments['password'] != ''/* || $arguments['password_repeat'] != ''*/) {
        $validate_password = true;
    }
    // Check if the email is currently assigned to this clients's id. If not, then check if it exists.
    $validation_fields['email']['email_exists'] = [
        'message' => $strings['email_exists']
    ];
}

// Instantiate
global $dbh, $json_strings;
$validation = new \ProjectSend\Validation($values, $strings);
$validation->make($validation_fields);
echo $validation->errors_formatted();
echo $validation->errors_json();
if ( $validation->validation_passed() ) { echo 'passed'; } else { echo 'errors found'; }