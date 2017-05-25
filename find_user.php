<?php 
require_once('sys.includes.php');
$s_key = filter_var($_POST['skey'],FILTER_VALIDATE_EMAIL);
if ($s_key) {
		$statement = $dbh->query("SELECT DISTINCT email FROM " . TABLE_USERS . " WHERE email LIKE '%".$s_key."%'");
		$statement->execute();
		$result = $statement->fetchAll();
		if($result) {
			echo 1; // user exist.
		}
		else {
			echo 0; // user not exist.
		}
}
else {
	echo 3; // Email not valid.
}


?>