<?php
require_once('sys.includes.php');
$dataval =$_POST['bname']; 
$sql = $dbh->prepare( "UPDATE " . TABLE_OPTIONS . " SET `value` = '".$dataval."' WHERE `".TABLE_OPTIONS."`.`name` = 'branding_title'" );

if($sql->execute()) {
	echo 1;
	}
else {
	$sql1 = $dbh->prepare( "INSERT INTO " . TABLE_OPTIONS . "(`name`, `value`) VALUES ('branding_title', '".$dataval."')" );
	if($sql1->execute()) {
		echo 1;
	}
	else {
		echo 0;
	}
}
						
//echo json_encode($dataval);
?>