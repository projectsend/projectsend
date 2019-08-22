<?php
require_once('sys.includes.php');
if (! check_for_session() ) {
	die();
}
$targetDir = UPLOADED_FILES_FOLDER;

    $zip = new ZipArchive();
    $finishedfile=$_POST['finished_files'];
    $fileName = $finishedfile['0'];
    $ext = strrpos($fileName, '.');
  	$fileName_a = substr($fileName, 0, $ext);
  	$fileName_b = substr($fileName, $ext);
		$curr_usr_id= CURRENT_USER_ID;

		$count = 1;
  	while (file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName_a . 'compressed_' . $count . '_'. $curr_usr_id . '.zip'))
  	$count++;

  	$fileName = $fileName_a . 'compressed_' . $count. '_' . $curr_usr_id;
    $withoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $fileName);
    $zipname=$withoutExt.".zip";
    $zipFilePath = UPLOADED_FILES_FOLDER.$zipname;
    $r = $zip->open($zipFilePath,  ZipArchive::CREATE);
		foreach ($_POST['finished_files'] as $p) {

					$zip->addFile(UPLOADED_FILES_FOLDER.$p,$p);

				}



		$r=$zip->close();

		foreach ($_POST['finished_files'] as $p) {
		unlink(UPLOADED_FILES_FOLDER.'temp/'.$p);
		unlink(UPLOADED_FILES_FOLDER.$p);
		 }
    $repost = array(
    "uploader_0_name" => $zipname,
    "zipupload"=>1
    );
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
