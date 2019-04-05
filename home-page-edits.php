<?php
/**
 * Allows to hide, show or delete the files assigend to the
 * selected client.
 * @package ProjectSend
 */
$load_scripts	= array(
						'footable',
					); 

$allowed_levels = array(9);
require_once('sys.includes.php');

$active_nav = 'Admin';
$cc_active_page = 'Home Page Edits';

$page_title = __('Home Page Edits','cftp_admin');

$current_level = get_current_user_level();


/*
 * Get the total downloads count here. The results are then
 * referenced on the results table.
 */ 
 
include('header.php');
global $dbh;
$statement = $dbh->prepare("SELECT * FROM tbl_home_page WHERE hid = 1");
$statement->execute();
$statement->setFetchMode(PDO::FETCH_ASSOC);
$row = $statement->fetch();
if($_POST) {	
	error_reporting(E_ALL);
	ini_set('display_errors', TRUE);
	$image_file_types = "/^\.(jpg|jpeg|gif|png){1}$/i";
	if (is_uploaded_file($_FILES['select_logo']['tmp_name']))
	{ 
		$this_upload = new PSend_Upload_File();
		$safe_filename = $this_upload->safe_rename($_FILES['select_logo']['name']);
		if (preg_match($image_file_types, strrchr($safe_filename, '.'))) {  
		echo LOGO_FOLDER;
			if (move_uploaded_file($_FILES['select_logo']['tmp_name'],LOGO_FOLDER.$safe_filename)) { /*echo 'test1111';exit; */
				$query1= "UPDATE tbl_home_page SET topright=:value WHERE hid = 1";
				$sql = $dbh->prepare($query1);
				$sql->execute(
						array(
								':value'	=> $safe_filename
							)
						);
			}
			
		}
	}
	
	$area_top_left = $_POST['area_top_left'];	
	$area_bottom_left = $_POST['area_bottom_left'];	
	$area_bottom_right = $_POST['area_bottom_right'];
	$created_by= 1;
	$status=1;
	$query = "UPDATE tbl_home_page SET topleft = :topleft, bottomleft = :bottomleft, bottomright = :bottomright, created_by = :created_by, status= :status WHERE hid = 1";
	$stmt = $dbh->prepare($query);
	$stmt->bindParam(':topleft', $area_top_left);
	$stmt->bindParam(':bottomleft', $area_bottom_left);
	$stmt->bindParam(':bottomright', $area_bottom_right);
	$stmt->bindParam(':created_by', $created_by);
	$stmt->bindParam(':status', $status);
	$stmt->execute();
	if($stmt->execute())
	{
		$success=true;
	}
	else
	{
		$success=false;
	}
	while (ob_get_level()) ob_end_clean();

	$location = BASE_URI . 'home-page-edits.php';

	header("Location: $location");

	die();
}
?>
<script src="<?php echo BASE_URI;?>includes/editor/nicEdit.js"></script>
<script type="text/javascript">	bkLib.onDomLoaded(function() { nicEditors.allTextAreas() });</script>
<div id="main">
  <div id="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
			<h1 class="page-title txt-color-blueDark"><i class="fa fa-tasks" aria-hidden="true"></i>&nbsp;<?php echo $page_title; ?></h1>
			<?php if(isset($success) && $success===true) { ?>
			<div class="alert alert-success fade in" style="margin-top: 15px;"><strong>Success!</strong> Home page edits are successfully updated.</div>
			<?php } elseif(isset($success) && $success===false) { ?>
			<div class="alert alert-danger fade in" style="margin-top: 15px;"><strong>Opps!</strong> something went wrong!.</div>
			<?php } ?>
        	
			<form action="" method="POST" enctype="multipart/form-data" class="home_page_edits_cls">		
				<h5>Top Left Text</h5>		
				<textarea name="area_top_left" cols="40"><?php echo isset($row['topleft'])?$row['topleft']:''; ?></textarea><br />		
				<h5>bottom Left Text</h5>		
				<textarea name="area_bottom_left" cols="40"><?php echo isset($row['bottomleft'])?$row['bottomleft']:''; ?></textarea><br />		
				<h5>bottom right Text</h5>		
				<textarea name="area_bottom_right" cols="40"><?php echo isset($row['bottomright'])?$row['bottomright']:''; ?></textarea><br />		
				<input type="hidden" name="MAX_FILE_SIZE" value="1000000000">
				<div class="form-group">
					<label class="col-sm-4 control-label"><?php _e('Select bottom right image','cftp_admin'); ?></label>
					<div class="col-sm-8">
						<input type="file" name="select_logo" />
					</div>
					</div>
					<div id="current_logo_right">
						<div id="current_logo_img">
						<?php
							if (isset($row['topright']) &&  $row['topright']!='') { 
							$logo_file_info = BASE_URI .'img/custom/logo/'. html_entity_decode(isset($row['topright'])?$row['topright']:''); 
							
						?>
								<img src="<?php echo $logo_file_info; ?>" alt="<?php _e('Logo Placeholder','cftp_admin'); ?>" />
						<?php
							}
						?>
					</div>
					
					</div>
					<div class="clear"></div>
				<input type="submit" name="submit">		
			</form>
		</div>	
      </div>
    </div>
  </div>
</div>
<?php
include('footer.php');
 ?>

