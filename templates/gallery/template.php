<?php
/*
Template name: Gallery
URI: http://www.projectsend.org/templates/gallery
Author: ProjectSend
Author URI: http://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: Shows only images (jpg, gif, and png). Do not use if you plan to upload other file types! They will not be shown.

Background modified from: http://www.artofadambetts.com/weblog/2008/05/black-leather-apple-desktop-background/
Delete icon: http://www.iconfinder.com/icondetails/37519/16/can_delete_trash_icon
*/

$ld = 'cftp_template_gallery'; // specify the language domain for this template

define('TEMPLATE_RESULTS_PER_PAGE', -1);

if ( !empty( $_GET['category'] ) ) {
	$category_filter = $_GET['category'];
}

include_once ROOT_DIR.'/templates/common.php'; // include the required functions for every template

$window_title = __('Gallery','cftp_template_gallery');

/**
 * Filter files by type, only save images.
*/
foreach ($available_files as $file_id) {
    $file = new \ProjectSend\Classes\Files($file_id);
    if ($file->isImage()) {
		$img_files[] = $file;
	}
}
$count = (isset($img_files)) ? count($img_files) : 0;

define('TEMPLATE_THUMBNAILS_WIDTH', '280');
define('TEMPLATE_THUMBNAILS_HEIGHT', '215');
?>
<!doctype html>
<html lang="<?php echo SITE_LANG; ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo html_output( $client_info['name'].' | '.$window_title . ' &raquo; ' . SYSTEM_NAME ); ?></title>
	<?php meta_favicon(); ?>

	<link rel="stylesheet" href="<?php echo $this_template_url; ?>/font-awesome-4.6.3/css/font-awesome.min.css">
	<script src="<?php echo PROTOCOL; ?>://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js" type="text/javascript"></script>
	<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Montserrat:400,700' rel='stylesheet' type='text/css'>

	<link rel="stylesheet" media="all" type="text/css" href="<?php echo $this_template_url; ?>main.min.css" />

    <script src="https://cdn.jsdelivr.net/npm/js-cookie@rc/dist/js.cookie.min.js"></script>
    <script src="<?php echo $this_template_url; ?>/js/template.js"></script>
    <script>
        window.base_url = '<?php echo BASE_URI; ?>';
    </script>

    <?php render_custom_assets('head'); ?>    
</head>

<body>
    <?php render_custom_assets('body_top'); ?>

<div id="wrapper">

	<div id="movable">

		<a href="#" class="btn_nav"><i class="fa fa-bars"></i></a>

		<div id="offsite">
			<div id="offsite_nav">
				<nav class="account_actions">
					<ul>
                        <li><a href="<?php echo BASE_URI; ?>process.php?do=logout" target="_self" id="logout"><i class="fa fa-sign-out" aria-hidden="true"></i> <?php _e('Logout', 'cftp_admin'); ?></a></li>
                        <li><a href="<?php echo BASE_URI; ?>manage-files.php" target="_self" id="manage"><i class="fa fa-file" aria-hidden="true"></i> <?php _e('Manage files', 'cftp_admin'); ?></a></li>
						<li><a href="<?php echo BASE_URI; ?>upload.php" target="_self" id="upload"><i class="fa fa-cloud-upload" aria-hidden="true"></i> <?php _e('Upload', 'cftp_admin'); ?></a></li>
					</ul>
				</nav>
				
				<?php
					if ( !empty( $get_categories['categories'] ) ) {
						$url_client_id	= ( !empty($_GET['client'] ) && CURRENT_USER_LEVEL != '0') ? $_GET['client'] : null;
						$link_template	= CLIENT_VIEW_FILE_LIST_URL;
				?>
						<h4><?php _e('Filter by category', 'cftp_admin'); ?></h4>
						<nav class="categories">
							<ul>
								<li class="filter_all_files"><a href="<?php echo CLIENT_VIEW_FILE_LIST_URL; if ( !empty( $url_client_id ) ) { echo '?client=' . $url_client_id; }; ?>"><?php  _e('All files', 'pinboxes_template'); ?></a></li>
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
                    <?php echo get_branding_layout(true); // true: returns the thumbnail, not the full image ?>
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
							foreach ($img_files as $file) {
						?>
								<li>
									<h5><?php echo $file->title; ?></h5>
									<?php
										if ($file->expired == true) {
									?>
											<?php _e('File expired','cftp_template_gallery'); ?>
									<?php
										}
										else {
                                            $thumbnail = make_thumbnail( $file->full_path, null, TEMPLATE_THUMBNAILS_WIDTH, TEMPLATE_THUMBNAILS_HEIGHT );
									?>
                                            <div class="img_prev">
                                                <a href="<?php echo $file->download_link; ?>" target="_blank">
                                                    <img src="<?php echo $thumbnail['thumbnail']['url']; ?>" class="thumbnail" alt="<?php echo $file->title; ?>" />
                                                </a>
                                            </div>
                                            <div class="actions">
                                                <div class="action">
                                                    <div class="download_link">
                                                        <a href="<?php echo $file->download_link; ?>" target="_blank">
                                                            <?php _e('Download','cftp_template_gallery'); ?>
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="action">
                                                    <div class="checkbox">
                                                        <input type="checkbox" class="checkbox_file" name="file_id" value="<?php echo $file->id; ?>" id="checkbox_file_<?php echo $file->id; ?>">
                                                        <label for="checkbox_file_<?php echo $file->id; ?>"><?php _e('Select', 'cftp_template_gallery'); ?></label>
                                                    </div>
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
			<?php render_footer_text(); ?>
		</div>
	</div>
</div>

<div id="zip_download">
    <a href="<?php echo BASE_URI; ?>process.php" target="_self" class="disabled" id="trigger">
        <i class="fa fa-cloud-download" aria-hidden="true"></i>
    </a>
    <img src="<?php echo $this_template_url; ?>/img/loading.svg" id="indicator">
</div>

<?php render_custom_assets('body_bottom'); ?>

</body>
</html>