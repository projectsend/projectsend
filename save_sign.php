<?php 
require_once('sys.includes.php');
// Define the Base64 value you need to save as an image
$b64 = $_POST['img_data'];
$user_id_mic =$_POST['user_id_mic'];
// if($user_id_mic!=''){
  // $targetsignature_dir = UPLOADED_FILES_FOLDER.'../../img/avatars/signature/';
// }else{
  $targetsignature_dir = UPLOADED_FILES_FOLDER.'../../img/avatars/tempsignature/';
// }
if (!file_exists($targetsignature_dir)) {
    mkdir($targetsignature_dir, 0777, true);
}


// Obtain the original content (usually binary data)
$bin = base64_decode($b64);
// Gather information about the image using the GD library
$size = getImageSizeFromString($bin);

// Check the MIME type to be sure that the binary data is an image
if (empty($size['mime']) || strpos($size['mime'], 'image/') !== 0) {
  die('Base64 value is not a valid image');
}

if($user_id_mic!=''){
  // Specify the location where you want to save the image
  $img_file = $targetsignature_dir.$user_id_mic.'.png';
}else{
  $img_file = $targetsignature_dir.'temp.png';
}
// Save binary data as raw data (that is, it will not remove metadata or invalid contents)
// In this case, the PHP backdoor will be stored on the server
if(file_put_contents($img_file, $bin)){
  echo json_encode(array('status'=>true,'tname'=>$img_file,'chageid'=>1));
}else{
  echo json_encode(array('status'=>false,'tname'=>'','chageid'=>''));
}



?>