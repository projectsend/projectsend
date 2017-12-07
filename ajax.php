<?php 
require_once('sys.includes.php');
//include('header-unlogged.php');
if(isset($_POST['action']) && !empty($_POST['action'])) {
	
    $action = $_POST['action'];
    switch($action) {
        case 'get_organization' : get_organization();break;
        case 'remove_organization' : remove_organization();break;
	case 'add_new_client_organization' : add_new_client_organization();break;
	case 'add_new_user_organization' : add_new_user_organization();break;
	case 'admin_remove_organization' : admin_remove_organization();break;
	case 'admin_activate_user_organization' : admin_activate_user_organization();break;
    }
}

function get_organization()
{
	global $dbh;
	$level = $_POST['selectedid'];
	if($level!=''){
	$sql = $dbh->prepare("SELECT * FROM " . TABLE_GROUPS . " WHERE organization_type = '".$level."' ORDER BY name ASC");
	
	//var_dump($sql);
	//echo 'test';exit;
	$sql->execute();
	$sql->setFetchMode(PDO::FETCH_ASSOC);
	while ( $row = $sql->fetch() ) {
	echo "<option value=".$row['id'].">".$row['name']."</option>";
	}
	}
}
function remove_organization()
{
	global $dbh;
	$group_id = $_POST['group_id'];
	$client_id = $_POST['client_id'];
	$sql = $dbh->prepare("DELETE FROM " . TABLE_MEMBERS . " WHERE " . TABLE_MEMBERS . ".client_id = ". $client_id." AND " . TABLE_MEMBERS . ".group_id =".$group_id);
	//var_dump($sql);
	//echo 'test';exit;
	if($sql->execute()){
		echo "done";
	}else{
		echo "not done";
	}
}
function admin_remove_organization() {
	global $dbh;
	$m_id = $_POST['m_id'];
	$sql = $dbh->prepare("DELETE FROM " . TABLE_MEMBERS . " WHERE " . TABLE_MEMBERS . ".id = ". $m_id);
	//var_dump($sql);
	//echo 'test';exit;
	if($sql->execute()){
		echo "done";
	}else{
		echo "not done";
	}
}
function add_new_client_organization () {
	if($_SESSION['loggedin_id']) {
		
		global $dbh;
		$gp_id = $_POST['cat_id'];
		if($_SESSION['userlevel'] == 9) {
			$client_id = $_POST['client_id'];
		}
		else {
			$client_id = $_SESSION['loggedin_id'];
		}
		
		$sql = $dbh->prepare("INSERT INTO `".TABLE_MEMBERS."` (`timestamp`, `added_by`, `client_id`, `group_id`,`m_org_status`) VALUES (CURRENT_TIMESTAMP, '".$_SESSION['loggedin']."', '".$client_id."', '".$gp_id."', '0');");
		//var_dump($sql);
		//echo 'test';exit;
		if($sql->execute()){
			echo "done";
		}else{
			echo "not done";
		}
	}

}
function add_new_user_organization() {

		if($_SESSION['loggedin_id']) {
		global $dbh;
		$gp_id = $_POST['cat_id'];
		if($_SESSION['userlevel'] == 9) {
			$client_id = $_POST['client_id'];
			$status = 0;
		}
		else {
			$client_id = $_SESSION['loggedin_id'];
			$status = 0;
		}
		$sql = $dbh->prepare("INSERT INTO `".TABLE_MEMBERS."` (`timestamp`, `added_by`, `client_id`, `group_id`, m_org_status) VALUES (CURRENT_TIMESTAMP, '".$_SESSION['loggedin']."', '".$client_id."', '".$gp_id."',".$status.");");
		//re$sql);
		if($sql->execute()){
			echo "done";
		}else{
			echo "not done";
		}
	}
}
function admin_activate_user_organization(){
	
	global $dbh;
	$m_id = $_POST['m_id'];
	$sql = $dbh->prepare("UPDATE " . TABLE_MEMBERS . " SET `m_org_status` = '1' WHERE " . TABLE_MEMBERS . ".id = ". $m_id);
	//var_dump($sql);
	//echo 'test';exit;
	if($sql->execute()){
		echo "done";
	}else{
		echo "not done";
	}

}
?>
