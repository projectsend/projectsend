<?php
require_once('sys.includes.php');
$dataval =$_POST['bname']; 
$sql = $dbh->prepare( "UPDATE " . TABLE_OPTIONS . " SET `value` = '".$dataval."' WHERE `".TABLE_OPTIONS."`.`name` = 'branding_title'" );
if($sql->execute()) {
	echo 1;
}						
?>