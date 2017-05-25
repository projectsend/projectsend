<?php
require_once('sys.includes.php');
$userinput =$_POST['checkmicrosenduser']; 
$sql = $dbh->prepare("select email from ".TABLE_USERS." where email like '%$userinput%' order by id limit 5");

	$sql->setFetchMode(PDO::FETCH_ASSOC);
	$result = $sql->execute();
	$total = $sql->rowCount();
	echo "<ul class='mhusermid'>";
	if($total) {
		while($result = $sql->fetch()) {
			echo "<li>".$result['email']."</li>";
		}
	}
	else {
		echo "<li>No Result Found!</li>";
	}
	echo "</ul>";
?>
