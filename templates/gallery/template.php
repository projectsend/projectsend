<?php
/*
Template name:
Gallery

Background modified from: http://www.artofadambetts.com/weblog/2008/05/black-leather-apple-desktop-background/
Delete icon: http://www.iconfinder.com/icondetails/37519/16/can_delete_trash_icon
*/

$ld = 'cftp_template_gallery'; // specify the language domain for this template

define('TEMPLATE_RESULTS_PER_PAGE', -1);

if ( !empty( $_GET['category'] ) ) {
	$category_filter = $_GET['category'];
}

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
<!doctype html>
<html lang="<?php echo SITE_LANG; ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo html_output( $client_info['name'].' | '.$window_title . ' &raquo; ' . SYSTEM_NAME ); ?></title>
	<?php meta_favicon(); ?>

	<link rel="stylesheet" href="<?php echo $this_template; ?>/font-awesome-4.6.3/css/font-awesome.min.css">
	<script src="<?php echo PROTOCOL; ?>://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js" type="text/javascript"></script>
	<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Montserrat:400,700' rel='stylesheet' type='text/css'>

	<link rel="stylesheet" media="all" type="text/css" href="<?php echo $this_template; ?>main.css" />
</head>

<body>

<div id="wrapper">

	<div id="movable">

		<a href="#" class="btn_nav"><i class="fa fa-bars"></i></a>

		<div id="offsite">
			<div id="offsite_nav">
				<nav class="account_actions">
					<ul>
						<li><a href="<?php echo BASE_URI; ?>process.php?do=logout" target="_self" id="logout"><i class="fa fa-sign-out" aria-hidden="true"></i> <?php _e('Logout', 'cftp_admin'); ?></a></li>
						<li><a href="<?php echo BASE_URI; ?>upload-from-computer.php" target="_self" id="upload"><i class="fa fa-cloud-upload" aria-hidden="true"></i> <?php _e('Upload files', 'cftp_admin'); ?></a></li>
					</ul>
				</nav>
				
				<?php
					if ( !empty( $get_categories['categories'] ) ) {
						$url_client_id	= ( !empty($_GET['client'] ) && CURRENT_USER_LEVEL != '0') ? $_GET['client'] : null;
						$link_template	= BASE_URI . 'my_files/';
				?>
						<h4><?php _e('Filter by category', 'cftp_admin'); ?></h4>
						<nav class="categories">
							<ul>
								<li class="filter_all_files"><a href="<?php echo BASE_URI . 'my_files/'; if ( !empty( $url_client_id ) ) { echo '?client=' . $url_client_id; }; ?>"><?php  _e('All files', 'pinboxes_template'); ?></a></li>
								<?php
									foreach ( $get_categories['categories'] as $category ) {
										$link_data	= array(
																'client'	=> $url_client_id,
																'category'	=> $category['id'],
															);
										$link_query	= http_build_query($link_data);
								?>
										<li><a href="<?php echo $link_template . '?' . $link_query; ?>"><?php echo $category['name']; ?></a></li>
								<?php
									}
								?>							
							</ul>
						</nav>
				<?php
					}
				?>
			</div>
		</div>

		<header>
			<?php if ($logo_file_info['exists'] === true) { ?>
				<div id="logo">
					<img src="<?php echo TIMTHUMB_URL; ?>?src=<?php echo $logo_file_info['url']; ?>&amp;w=<?php echo LOGO_MAX_WIDTH; ?>" alt="<?php echo THIS_INSTALL_SET_TITLE; ?>" />
				</div>
			<?php } ?>
		</header>
			
		<div id="content">
		
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
													<i class="fa fa-cloud-download" aria-hidden="true"></i> <?php _e('Download original','cftp_template_gallery'); ?>
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
			<?php default_footer_info(); ?>
		</div>
	</div>
</div>

<script type="text/javascript">
	$(document).ready(function(e) {
		$('.btn_nav').click(function(e) {
			e.preventDefault();
			$('#wrapper').toggleClass('show-nav');
			$('#wrapper').toggleClass('open-nav');
		});
	});
</script>

</body>
</html>