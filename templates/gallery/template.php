<?php
/*
Template name:
Gallery

Background modified from: http://www.artofadambetts.com/weblog/2008/05/black-leather-apple-desktop-background/
Delete icon: http://www.iconfinder.com/icondetails/37519/16/can_delete_trash_icon
*/

$ld = 'cftp_template_gallery'; // specify the language domain for this template
include_once(ROOT_DIR.'/templates/common.php'); // include the required functions for every template

$window_title = __('Gallery','cftp_template_gallery');

/**
 * Filter files by type, only save images.
*/
$img_formats = array('gif','jpg','pjpeg','jpeg','png');
foreach ($my_files as $file) {
	$pathinfo = pathinfo($file['url']);
	$extension = strtolower($pathinfo['extension']);
	if (in_array($extension,$img_formats)) {
		$img_files[] = $file;
	}
}
$count = count($img_files);
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo $client_info['name'].' | '.$window_title; ?> | <?php echo SYSTEM_NAME; ?></title>
	<link rel="stylesheet" media="all" type="text/css" href="<?php echo $this_template; ?>main.css" />
	<link rel="shortcut icon" href="<?php echo BASE_URI; ?>/favicon.ico" />
	<script src="<?php echo PROTOCOL; ?>://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js" type="text/javascript"></script>
	<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Sirin+Stencil' rel='stylesheet' type='text/css'>
</head>

<body>

<div id="header">
	<?php if ($logo_file_info['exists'] === true) { ?>
		<div id="current_logo">
			<img src="<?php echo TIMTHUMB_URL; ?>?src=<?php echo $logo_file_info['url']; ?>&amp;w=<?php echo LOGO_MAX_WIDTH; ?>" alt="<?php echo THIS_INSTALL_SET_TITLE; ?>" />
		</div>
	<?php } ?>

	<a href="<?php echo BASE_URI; ?>process.php?do=logout" target="_self" id="logout" class="header_button"><?php _e('Logout', 'cftp_admin'); ?></a>
	<a href="<?php echo BASE_URI; ?>upload-from-computer.php" target="_self" id="upload" class="header_button"><?php _e('Upload files', 'cftp_admin'); ?></a>
</div>
	
<div id="content">

	<div class="wrapper">

<?php
	if (!$count) {
		_e('There are no files.','cftp_template_gallery');
	}
	else {
?>
		<ul class="photo_list">
			<?php
				foreach ($img_files as $this_file) {
					$download_link = make_download_link($this_file);
			?>
					<li>
						<h5><?php echo htmlentities($this_file['name']); ?></h5>
						<?php
							if ($this_file['expired'] == true) {
						?>
								<?php _e('File expired','cftp_template_gallery'); ?>
						<?php
							}
							else {
						?>
							<div class="img_prev">
								<a href="<?php echo $download_link; ?>" target="_blank">
									<?php
										$this_thumbnail_url = UPLOADED_FILES_URL.$this_file['url'];
										if (THUMBS_USE_ABSOLUTE == '1') {
											$this_thumbnail_url = BASE_URI.$this_thumbnail_url;
										}
									?>
									<img src="<?php echo TIMTHUMB_URL; ?>?src=<?php echo $this_thumbnail_url; ?>&amp;w=280&amp;h=215&amp;f=2&amp;q=<?php echo THUMBS_QUALITY; ?>" class="thumbnail" alt="<?php echo htmlentities($this_file['name']); ?>" />
								</a>
							</div>
							<div class="img_data">
								<div class="download_link">
									<a href="<?php echo $download_link; ?>" target="_blank">
										<?php _e('Download original','cftp_template_gallery'); ?>
									</a>
								</div>
							</div>
						<?php
							}
						?>
					</li>
			<?php
				}
			?>
		</ul>
	<?php
	}
	?>

	</div>
</div>

<?php default_footer_info(); ?>

</body>
</html>