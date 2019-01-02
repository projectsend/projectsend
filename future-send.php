<?php
require_once('sys.includes.php');

	$current_date = date("Y-m-d");
	
	/*$test = "select tbl_files.filename,CAST(tbl_files.future_send_date AS DATE) AS  fs_date,tbl_users.email from tbl_files LEFT JOIN  tbl_files_relations ON tbl_files.id = tbl_files_relations.file_id LEFT JOIN  tbl_users ON tbl_files_relations.client_id = tbl_users.id where tbl_files.future_send_date = '".$current_date."'";*/
	$sql = "select tbl_files.filename,CAST(tbl_files.future_send_date AS DATE) AS  fs_date,tbl_users.email from tbl_files LEFT JOIN  tbl_files_relations ON tbl_files.id = tbl_files_relations.file_id LEFT JOIN  tbl_users ON tbl_files_relations.client_id = tbl_users.id where tbl_users.email !='' AND tbl_files.future_send_date = DATE(NOW())";
	$statement = $dbh->prepare($sql);
	
	$statement->execute();
	
	$statement->setFetchMode(PDO::FETCH_ASSOC);
	
	$future_file = $statement->fetchAll();  
	
	if(!empty($future_file)) { 
					
		foreach ($future_file as $key ) 
		{
			$files_list = '';

				$files_list.= '<li style="margin-bottom:11px;">';
				$files_list.= '<p style="font-weight:bold; margin:0 0 5px 0; font-size:14px;">'.$key['filename'].'</p>';
				if (!empty($key['description'])) {
					$files_list.= '<p>'.$key['description'].'</p>';
				}
				$files_list.= '</li>';
				$notify_client = new PSend_Email();
				$email_arguments = array(
										'type' => 'new_files_for_client',
										'address' => $key['email'],
										'files_list' => $files_list
									);
				$try_sending = $notify_client->psend_send_email($email_arguments);
				if ($try_sending == 1) {
					echo "send Successfully";
				}
				else {
					echo "Not send Successfully";
				}
	
		}
	}


?>


