<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('sys.includes.php');
$loggedin_id = $_SESSION['loggedin_id'];

$sql_file = $dbh->prepare("SELECT * FROM tbl_files_relations INNER JOIN tbl_files
			WHERE ( tbl_files_relations.file_id = tbl_files.id AND tbl_files.expires =1 AND DATE(.tbl_files.expiry_date) = DATE(NOW())) ");
$sql_file->execute();
$sql_file->setFetchMode(PDO::FETCH_ASSOC);
$exp_files = $sql_file->fetchAll();
//print_r($exp_files);
echo("Outside Foreach <br>");
//
//
if( !empty($exp_files)){
	echo("Expire files loop<br>");
  foreach ($exp_files as $exp_file) {
		echo("inside foreach");
     print_r($exp_file);
     echo("---------------------------------------------------------------------------------------------<br>");
    $statement = $dbh->prepare("SELECT email FROM " . TABLE_USERS ." WHERE ( id = ".$exp_file['from_id']." OR id =".$exp_file['client_id'].")" );
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    $usermail = $statement->fetch();
    print_r($usermail);
    echo("<br>");
    echo($exp_file['from_id']);
    echo("<br>");
    echo($exp_file['client_id']);
		
    							$e_notify = new PSend_Email();
    							$e_arg = array(
    										'type'		=> 'file_expired',
    										'address'	=> $usermail['email'],
                        'files_list'=>$exp_file['url']
    									);
                   print_r($e_arg);
    							$notify_send = $e_notify->psend_send_email($e_arg);
                  echo("Done".$notify_send);


  }
}
?>
