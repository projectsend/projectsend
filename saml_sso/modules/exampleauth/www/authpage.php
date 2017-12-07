<?php

/**
 * This page serves as a dummy login page.
 *
 * Note that we don't actually validate the user in this example. This page
 * just serves to make the example work out of the box.
 *
 * @package SimpleSAMLphp
 */

if (!isset($_REQUEST['ReturnTo'])) {
	die('Missing ReturnTo parameter.');
}

$returnTo = \SimpleSAML\Utils\HTTP::checkURLAllowed($_REQUEST['ReturnTo']);


/*
 * The following piece of code would never be found in a real authentication page. Its
 * purpose in this example is to make this example safer in the case where the
 * administrator of * the IdP leaves the exampleauth-module enabled in a production
 * environment.
 *
 * What we do here is to extract the $state-array identifier, and check that it belongs to
 * the exampleauth:External process.
 */

if (!preg_match('@State=(.*)@', $returnTo, $matches)) {
	die('Invalid ReturnTo URL for this example.');
}
SimpleSAML_Auth_State::loadState(urldecode($matches[1]), 'exampleauth:External');

/*
 * The loadState-function will not return if the second parameter does not
 * match the parameter passed to saveState, so by now we know that we arrived here
 * through the exampleauth:External authentication page.
 */


/*
 * Our list of users.
 */
$users = array(
	'student' => array(
		'password' => 'student',
		'uid' => 'student',
		'name' => 'Student Name',
		'mail' => 'somestudent@example.org',
		'type' => 'student',
	),
	'admin' => array(
		'password' => 'admin',
		'uid' => 'admin',
		'name' => 'Admin Name',
		'mail' => 'someadmin@example.org',
		'type' => 'employee',
	),
);


/*
 * Time to handle login responses.
 * Since this is a dummy example, we accept any data.
 */

$badUserPass = FALSE;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = (string)$_REQUEST['username'];
	$password = (string)$_REQUEST['password'];

	if (!isset($users[$username]) || $users[$username]['password'] !== $password) {
		$badUserPass = TRUE;
	} else {

		$user = $users[$username];

		if (!session_id()) {
			// session_start not called before. Do it here.
			session_start();
		}

		$_SESSION['uid'] = $user['uid'];
		$_SESSION['name'] = $user['name'];
		$_SESSION['mail'] = $user['mail'];
		$_SESSION['type'] = $user['type'];

		\SimpleSAML\Utils\HTTP::redirectTrustedURL($returnTo);
	}
}


/*
 * If we get this far, we need to show the login page to the user.
 */
?><!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>exampleauth login page</title>
</head>
<body>
<h1>exampleauth login page</h1>
<p>In this example you can log in with two accounts: <code>student</code> and <code>admin</code>. In both cases, the password is the same as the username.</p>
<?php if ($badUserPass) { ?>
<p>Bad username or password.</p>
<?php } ?>
<form method="post" action="?">
<p>
Username:
<input type="text" name="username">
</p>
<p>
Password:
<input type="text" name="password">
</p>
<input type="hidden" name="ReturnTo" value="<?php echo htmlspecialchars($returnTo); ?>">
<p><input type="submit" value="Log in"></p>
</form>
</body>
</html>
