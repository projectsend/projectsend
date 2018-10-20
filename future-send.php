<?php
require_once('sys.includes.php');

	$current_date = date("Y-m-d");
	
	/*$test = "select tbl_files.filename,CAST(tbl_files.future_send_date AS DATE) AS  fs_date,tbl_users.email from tbl_files LEFT JOIN  tbl_files_relations ON tbl_files.id = tbl_files_relations.file_id LEFT JOIN  tbl_users ON tbl_files_relations.client_id = tbl_users.id where tbl_files.future_send_date = '".$current_date."'";*/

	$statement = $dbh->prepare("select tbl_files.filename,CAST(tbl_files.future_send_date AS DATE) AS  fs_date,tbl_users.email from tbl_files LEFT JOIN  tbl_files_relations ON tbl_files.id = tbl_files_relations.file_id LEFT JOIN  tbl_users ON tbl_files_relations.client_id = tbl_users.id where tbl_files.future_send_date = '".$current_date."'");
	
	$statement->execute();
	
	$statement->setFetchMode(PDO::FETCH_ASSOC);
	
	$future_file = $statement->fetchAll(); 
	
	
							/*echo '<pre>';
							print_r($future_file); 
							echo '</pre>';*/
							
	foreach ($future_file as $key ) 
	
{
							

if (date("Y-m-d") == $key['fs_date'])
	
{ 
						$email_to = $key['email'];
						$email_subject = "Msend";
						$email_body = "New files uploaded for you </br>'".$key['filename']."'";
						$file_name = $key['filename'];

						if(mail($email_to, $email_subject, $email_body))
							
						{
							echo "The email notification $email_subject was successfully sent.";
						} 
						
						else 
							
						{
							echo "The email  notification $email_subject was NOT sent.";
						}
						
}
}
							


?>


