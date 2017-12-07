<?php
require_once('sys.includes.php');
include('header-unlogged.php');
?>
<div id="main">
<div id="content" class="container">
<div class="row">
<?php
$target_dir = UPLOADED_FILES_FOLDER.'../avatar/';
$target_file = $target_dir;
$uploadOk = 1;
$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
?>

<form action="<?php echo $_SERVER['PHP_SELF']; ?>" name="adduser" method="post" class="form-horizontal" enctype="multipart/form-data">
	<div class="col-sm-6">
		<input type="file" name="userfiles" class="required" value="" placeholder="upload file" />
	</div>	
	<div class="inside_form_buttons">
	<input type="submit" name="submit" class="btn btn-wide btn-primary" value="1">SUBMIT</input>
	</div>
</form>
<?php
if($_POST) {
print_r($_POST);
print_r($_FILES);
	$target_file = $target_dir . basename($_FILES["userfiles"]["name"]);
	$uploadOk = 1;
	$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
	// Check if image file is a actual image or fake image
	if(isset($_POST["submit"])) {
	    $check = getimagesize($_FILES["userfiles"]["tmp_name"]);
	    if($check !== false) {
		echo "File is an image - " . $check["mime"] . ".";
		$uploadOk = 1;
	    } else {
		echo "File is not an image.";
		$uploadOk = 0;
	    }
	}
	// Check file size
	if ($_FILES["userfiles"]["size"] > 500000) {
	    echo "Sorry, your file is too large.";
	    $uploadOk = 0;
	}
	// Allow certain file formats
	if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
	&& $imageFileType != "gif" ) {
	    echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
	    $uploadOk = 0;
	}
	// Check if $uploadOk is set to 0 by an error
	if ($uploadOk == 0) {
	    echo "Sorry, your file was not uploaded.";
	// if everything is ok, try to upload file
	} else {
		echo $target_file;
	    if (move_uploaded_file($_FILES["userfiles"]["tmp_name"], $target_file)) {
		echo "The file ". basename( $_FILES["userfiles"]["name"]). " has been uploaded.";
	    } else {
		echo "Sorry, there was an error uploading your file.";
	    }
	}
}


								//	$this_file = new PSend_Upload_File();
									// Rename the file
									//$fileName = $this_file->safe_rename($fileName);
									$target_dir = UPLOADED_FILES_FOLDER.'../avatar/';
									$target_file = $target_dir;
									$uploadOk = 1;
									$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
									$target_file = $target_dir . "/".$fileName);
									$uploadOk = 1;
									// Check if image file is a actual image or fake image
									$check = getimagesize($_FILES["userfiles"]["tmp_name"]);
									if($check !== false) {
										echo "File is an image - " . $check["mime"] . ".";
										$uploadOk = 1;
									} else {
										echo "File is not an image.";
										$uploadOk = 0;
									}
									// Check file size
									/*if ($_FILES["userfiles"]["size"] > 500000) {
											echo "Sorry, your file is too large.";
											$uploadOk = 0;
									}*/
									// Allow certain file formats
									if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
									&& $imageFileType != "gif" ) {
											echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
											$uploadOk = 0;
									}
									// Check if $uploadOk is set to 0 by an error
									if ($uploadOk == 0) {
											echo "Sorry, your file was not uploaded.";
									// if everything is ok, try to upload file
									} else {
										echo $target_file;
											if (move_uploaded_file($_FILES["userfiles"]["tmp_name"], $target_file)) {
										echo "The file ". basename( $_FILES["userfiles"]["name"]). " has been uploaded.";
											} else {
										echo "Sorry, there was an error uploading your file.";
											}
									}

										//	exit;
