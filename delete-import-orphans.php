<?php
/**
 * Ajax function to delete orphan files
 */
$load_scripts	= array(
						'footable',
					); 

$allowed_levels = array(9);
require_once('sys.includes.php');
$work_folder = UPLOADED_FILES_FOLDER;
if(!empty($_SESSION)){
	if($_SESSION['userlevel'] == '9'){
	$value = $_POST['values'];
		if(!empty($value)){
			/* Decode all file names*/
			$data = json_decode(stripslashes($value));
				foreach($data as $d){
					/* Delete file */
					if(unlink($work_folder.$d)){
						echo "done";
					}				
				}
		
		}
	}else{
		header("location:".BASE_URI);
	}
}
?>
