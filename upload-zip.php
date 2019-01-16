<?php
require_once('sys.includes.php');
if (! check_for_session() ) {
	die();
}
$targetDir = UPLOADED_FILES_FOLDER;
//   if(count($_POST['finished_files']) ==1 ){
//     $finishedfile=$_POST['finished_files'];
//     $fileName = $finishedfile['0'];
//     $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
//     $blockSize = 256;
//         $inputKey = "project send encryption";
//
//     $fileData = file_get_contents($filePath);
//     $aes = new AES($fileData, ENCRYPTION_KEY, BLOCKSIZE);
//     $encData = $aes->encrypt();
//     unlink($filePath);
//     file_put_contents($filePath , $encData);
// ?>
  <!-- <form id="myForm" action="upload-process-form.php" method="post">
      <input type="hidden" value="<?php // echo $_POST['uploader_0_name']; ?>" name="uploader_0_name">
      <input type="hidden" value="0" name="zipupload">
      <input type="hidden" value="<?php // echo $finishedfile['0']; ?>" name="finished_files[]">
      <input type="hidden" value="done" name="uploader_0_status">
      <input type="hidden" value="1" name="uploader_count">
  </form>
  <script type="text/javascript">
    document.getElementById('myForm').submit();
  </script> -->
<?php
// }
//
//   else {
    //Create an object from the ZipArchive class.
    $zip = new ZipArchive();
    $finishedfile=$_POST['finished_files'];
    $fileName = $finishedfile['0'];
    $ext = strrpos($fileName, '.');
  	$fileName_a = substr($fileName, 0, $ext);
  	$fileName_b = substr($fileName, $ext);	$curr_usr_id= CURRENT_USER_ID;

  	$count = 1;
  	while (file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName_a . 'compressed_' . $count . '_'. $curr_usr_id . '_'. $fileName_b))
  	$count++;

  	$fileName = $fileName_a . 'compressed_' . $count. '_' . $curr_usr_id . '_'. $fileName_b;
    $withoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $fileName);
    $zipname=$withoutExt.".zip";
    $zipFilePath = UPLOADED_FILES_FOLDER.$zipname;
    $r = $zip->open($zipFilePath,  ZipArchive::CREATE);
		var_dump($r);
    foreach ($_POST['finished_files'] as $p) {
        	$filesToAdd= file_get_contents(UPLOADED_FILES_FOLDER.$p);
        	$img = new AES($filesToAdd, ENCRYPTION_KEY, BLOCKSIZE);
					$decryptData =  $img->decrypt();
					unlink(UPLOADED_FILES_FOLDER.$p);
					file_put_contents(UPLOADED_FILES_FOLDER.$p, $decryptData);
					$r=$zip->addFile(UPLOADED_FILES_FOLDER.$p,$p);
					var_dump($r);
				}


		$r=$zip->close();
		var_dump($r);
		foreach ($_POST['finished_files'] as $p) {
		unlink(UPLOADED_FILES_FOLDER.$p);
		 }
    $repost = array(
    "uploader_0_name" => $zipname,
    "zipupload"=>1
    );

		// Encrypting the zip file
      // $fileData = file_get_contents( UPLOADED_FILES_FOLDER. $zipname);
      // $aes = new AES($fileData, ENCRYPTION_KEY, BLOCKSIZE);
      // $encData = $aes->encrypt();
      // unlink( UPLOADED_FILES_FOLDER. $zipname);
      // file_put_contents(UPLOADED_FILES_FOLDER. $zipname , $encData);
      ?>
    <form id="myForm" action="upload-process-form.php" method="post">

        <input type="hidden" value="<?php echo $repost['uploader_0_name']; ?>" name="uploader_0_name">
        <input type="hidden" value="<?php echo $repost['zipupload']; ?> " name="zipupload">
        <input type="hidden" value="<?php echo $repost['uploader_0_name']; ?>" name="finished_files[]">
        <input type="hidden" value="done" name="uploader_0_status">
        <input type="hidden" value="1" name="uploader_count">
    </form>
    <script type="text/javascript">
      document.getElementById('myForm').submit();
    </script>

<?php
// }

?>
