<?Php
require_once('sys.includes.php');
//include('header.php');
global $dbh;
$date=$_POST['date'];
$date = new DateTime($date);
$date=$date->format('Y-m-d');


$q_sent_file = "SELECT tu.name As clientid, tg.name As groupid, tf.* 
FROM tbl_files AS tf 
LEFT JOIN ".TABLE_FILES_RELATIONS." AS tfr ON tf.id = tfr.file_id 
LEFT JOIN  ".TABLE_USERS." As tu ON tfr.client_id=tu.id
LEFT JOIN  ".TABLE_GROUPS." As tg ON tfr.group_id=tg.id
where tfr.from_id =" . CURRENT_USER_ID. " AND DATE(tf.future_send_date)='".$date."'";

$statement = $dbh->prepare($q_sent_file);
$statement->execute();
$statement->setFetchMode(PDO::FETCH_ASSOC);
$rows = $statement->fetchAll();
$full_files=array();
$row_f = array();
if(!empty($rows)) {
	
	foreach($rows as $row)
	{
		$row_f['send'][]= $row;
	}
}
else
{
	$row_f['send']='false';
}

$q_received = "SELECT  tbl_files.* FROM tbl_files
LEFT JOIN tbl_files_relations ON tbl_files.id = tbl_files_relations.file_id
where  tbl_files_relations.client_id =" . CURRENT_USER_ID." AND DATE(tbl_files.timestamp)='".$date."'" ;

$statement1 = $dbh->prepare($q_received);
$statement1->execute();
$statement1->setFetchMode(PDO::FETCH_ASSOC);
$rows1 = $statement1->fetchAll();
$row_f1 = array();
if(!empty($rows1)) {
	foreach($rows1 as $row1)
	{
		$row_f1['receive'][]= $row1;
	}
}
else {
	$row_f1['receive']='false';
}

/* Expired files */
$q_expirydate = "SELECT  tbl_files.* FROM tbl_files
												LEFT JOIN tbl_files_relations ON tbl_files.id = tbl_files_relations.file_id
													where  (tbl_files_relations.from_id =" . CURRENT_USER_ID." || tbl_files_relations.client_id =" . CURRENT_USER_ID.")
																	AND (DATE(tbl_files.expiry_date)='".$date."' ) AND (tbl_files.expires = 1)" ;

$statement2 = $dbh->prepare($q_expirydate);
$statement2->execute();
$statement2->setFetchMode(PDO::FETCH_ASSOC);
$rows2 = $statement2->fetchAll();
$row_f2 = array();
if(!empty($rows2)) {
	foreach($rows2 as $row2)
	{
		$row_f2['expiry'][]= $row2;
	}
}
else {
	$row_f2['expiry']='false';
}
$full_files=array_merge($row_f,$row_f1,$row_f2);
echo json_encode($full_files);

?>
