<?php
/* 
 * The configuration of the login-auto authentication module.
 */

$config = array (

	/*
	 * This option enables or disables the login-auto authentication
	 * handler. This handler is implemented in 'www/auth/login-auto.php'.
	 *
	 * When this option is set to true, a user can go to the
	 * 'auth/login-auto.php' web page to be authenticated as an example
	 * user. The user will receive the attributes set in the
	 * 'auth.auto.attributes' option.
	 *
	 * WARNING: setting this option to true will make it possible to use
	 * this authenticator for all users, irrespectively of the 'auth'
	 * setting in the IdP's metadata. They can always use it by opening the
	 * 'auth/login-auto.php' webpage manually.
	 */
	'auth.auto.enable' => true,

	/*
	 * This option configures which attributes the login-auto
	 * authentication handler will set for the user. It is an array of
	 * arrays. The name of the attribute is the index in the first array,
	 * and all the values for the attribute is given in the array
	 * referenced to by the name.
	 *
	 * Example:
	 * 'auth.auto.attributes' => array(
	 *     'edupersonaffiliation' => array('student', 'member'),
	 *     'uid' => array('example_uid'),
	 *     'mail' => array('example@example.com'),
	 * ),
	 */
	'auth.auto.attributes' => array(
		'edupersonaffiliation' => array('student', 'member'),
		'title' => array('Example user title'),
		'uid' => array('example_uid'),
		'mail' => array('example@example.com'),
		'cn' => array('Example user commonname'),
		'givenname' => array('Example user givenname'),
		'sn' => array("Example surname"),
	),

	/*
	 * When this option is set to true, the login-auto authentication
	 * handler will ask for a username and a password. This can be used to
	 * test the IdP. The username and password isn't verified, and the
	 * user/script can enter anything.
	 */
	'auth.auto.ask_login' => false,

	/*
	 * This option configures a delay in the login-auto authentication
	 * handler. The script will wait for the given number of milliseconds
	 * before authenticating the user. This can, for example, be used in
	 * a simple simulation of a slow LDAP server.
	 */
	'auth.auto.delay_login' => 0,
);
