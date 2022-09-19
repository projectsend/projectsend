<?php
/*
Template name: PinBoxes
URI: http://www.projectsend.org/templates/pinboxes
Author: ProjectSend
Author URI: http://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: Inspired by the awesome design of Pinterest!
*/
$ld = 'pinboxes_template'; // specify the language domain for this template

define('TEMPLATE_RESULTS_PER_PAGE', -1);

if ( !empty( $_GET['category'] ) ) {
	$category_filter = $_GET['category'];
}

include_once ROOT_DIR.'/templates/common.php'; // include the required functions for every template

$window_title = __('Available files','pinboxes_template');

$count = count($my_files);

define('TEMPLATE_THUMBNAILS_WIDTH', '250');
define('TEMPLATE_THUMBNAILS_HEIGHT', '400');
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title><?php echo html_output( $client_info['name'].' | '.$window_title . ' &raquo; ' . SYSTEM_NAME ); ?></title>
		<?php meta_favicon(); ?>
		<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Metrophobic' rel='stylesheet' type='text/css'>
		<link rel="stylesheet" href="<?php echo $this_template_url; ?>/font-awesome-4.6.3/css/font-awesome.min.css">
		<link rel="stylesheet" media="all" type="text/css" href="<?php echo $this_template_url; ?>main.min.css" />
        
        <script>
            window.base_url = '<?php echo BASE_URI; ?>';
        </script>
        <script src="<?php echo PROTOCOL; ?>://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js" type="text/javascript"></script>
        <script src="https://cdn.jsdelivr.net/npm/js-cookie@rc/dist/js.cookie.min.js"></script>
		<script src="<?php echo $this_template_url; ?>/js/jquery.masonry.min.js"></script>
        <script src="<?php echo $this_template_url; ?>/js/imagesloaded.pkgd.min.js"></script>
        <script src="<?php echo $this_template_url; ?>/js/template.js"></script>

        <?php render_custom_assets('head'); ?>
	</head>
	
	<body>
        <?php render_custom_assets('body_top'); ?>

		<div id="header">
			<?php if ($logo_file_info['exists'] === true) { ?>
				<div id="branding">
                    <?php echo get_branding_layout(true); // true: returns the thumbnail, not the full image ?>
				</div>
			<?php } ?>
		</div>

		<div id="menu">
			<p class="welcome">
				<?php _e('Welcome','pinboxes_template'); ?>, <?php echo html_output($client_info['name']); ?>
			</p>
			<ul>
				<li id="search_box">
					<form action="" name="files_search" method="get">
						<input type="text" name="search" id="search_text" value="<?php echo (isset($_GET['search']) && !empty($_GET['search'])) ? html_output($_GET['search']) : ''; ?>" placeholder="<?php _e('Search...','pinboxes_template'); ?>">
						<button type="submit" id="search_go"><i class="fa fa-search" aria-hidden="true"></i></button>
					</form>
				</li>
				<?php
					if ( !empty( $get_categories['categories'] ) ) {
						$url_client_id	= ( !empty($_GET['client'] ) && CURRENT_USER_LEVEL != '0') ? $_GET['client'] : null;
						$link_template	= CLIENT_VIEW_FILE_LIST_URL;
				?>
						<li class="categories_trigger">
							<a href="#" target="_self"><i class="fa fa-filter" aria-hidden="true"></i> <?php _e('Categories', 'pinboxes_template'); ?></a>
							<ul class="categories">
								<li class="filter_all_files"><a href="<?php echo CLIENT_VIEW_FILE_LIST_URL; if ( !empty( $url_client_id ) ) { echo '?client=' . $url_client_id; }; ?>"><?php  _e('All files', 'pinboxes_template'); ?></a></li>
								<?php
									foreach ( $get_categories['categories'] as $category ) {
										$link_data	= array(
                                            'client' => $url_client_id,
                                            'category' => $category['id'],
                                        );
										$link_query	= http_build_query($link_data);
								?>
										<li><a href="<?php echo $link_template . '?' . $link_query; ?>"><?php echo $category['name']; ?></a></li>
								<?php
									}
								?>							
							</ul>
						</li>
				<?php
					}
				?>
				<li>
                    <a href="<?php echo BASE_URI; ?>process.php" id="zip_download" target="_self" class="disabled">
                        <i class="fa fa-download" aria-hidden="true"></i> <?php _e('Download zipped', 'pinboxes_template'); ?>
                    </a>
				</li>
				<li>
                    <a href="<?php echo BASE_URI; ?>upload.php" target="_self">
                        <i class="fa fa-cloud-upload" aria-hidden="true"></i> <?php _e('Upload', 'pinboxes_template'); ?>
                    </a>
				</li>
				<li>
                    <a href="<?php echo BASE_URI; ?>manage-files.php" target="_self">
                        <i class="fa fa-file" aria-hidden="true"></i> <?php _e('Manage', 'pinboxes_template'); ?>
                    </a>
				</li>
				<li>
                    <a href="<?php echo BASE_URI; ?>process.php?do=logout" target="_self">
                        <i class="fa fa-sign-out" aria-hidden="true"></i> <?php _e('Logout', 'pinboxes_template'); ?>
                    </a>
				</li>
			</ul>
		</div>
			
		<div id="content">
			<div class="content_cover"></div>
			<div class="wrapper">
		
		<?php
			if (!$count) {
		?>
				<div class="no_files">
					<?php
						_e('There are no files.','pinboxes_template');
					?>
				</div>
		<?php
			}
			else {
		?>
				<div class="photo_list">
                <?php
					foreach ($available_files as $file_id) {
                        $file = new \ProjectSend\Classes\Files();
                        $file->get($file_id);
				?>
						<div class="photo <?php if ($file->expired == true) { echo 'expired'; } ?>">
							<div class="photo_int">
                                <?php
                                    if ($file->isImage()) {
								?>
										<div class="img_prev">
											<?php
												if ($file->expired == false) {
											?>
													<a href="<?php echo $file->download_link; ?>" target="_blank">
                                                        <?php $thumbnail = make_thumbnail( UPLOADED_FILES_DIR.DS.$file->filename_on_disk, 'proportional', TEMPLATE_THUMBNAILS_WIDTH, TEMPLATE_THUMBNAILS_HEIGHT ); ?>
														<img src="<?php echo $thumbnail['thumbnail']['url']; ?>" alt="<?php echo $file->title; ?>" />
													</a>
											<?php
												}
											?>
										</div>
								<?php
									} else {
										if ($file->expired == false) {
								?>
											<div class="ext_prev">
												<a href="<?php echo $file->download_link; ?>" target="_blank">
													<h6><?php echo $file->extension; ?></h6>
												</a>
											</div>
								<?php
										}
									}
								?>
							</div>
							<div class="img_data">
								<h2><?php echo $file->title; ?></h2>
								<div class="photo_info">
									<?php echo $file->description; ?>
									<p class="file_size">
										<?php _e('File size:','pinboxes_template'); ?> <strong><?php echo $file->size_formatted; ?></strong>
									</p>

									<p class="exp_date">
										<?php
											if ( $file->expires == '1' ) {
												$exp_date = date( get_option('timeformat'), strtotime( $file->expiry_date ) );
												_e('Expiration date:','pinboxes_template'); ?> <span><?php echo $exp_date; ?></span>
										<?php
											}
										?>
									</p>
								</div>
								<div class="download_link">
									<?php
										if ($file->expired == false) {
                                    ?>
											<a href="<?php echo $file->download_link; ?>" target="_blank" class="button button_gray">
												<?php _e('Download','pinboxes_template'); ?>
                                            </a>
                                            <div class="checkbox">
                                                <input type="checkbox" class="checkbox_file" name="file_id" value="<?php echo $file->id; ?>" id="checkbox_file_<?php echo $file->id; ?>">
                                                <label for="checkbox_file_<?php echo $file->id; ?>"></label>
                                            </div>
									<?php
										}
										else {
									?>
											<?php _e('File expired','pinboxes_template'); ?>
									<?php
										}
									?>
								</div>
							</div>
						</div>
					<?php
						}
					?>
				</div>
        <?php
			}
        ?>
		
			</div>
	
			<?php render_footer_text(); ?>
	
        </div>
        
        <div class="downloading">
            <img src="<?php echo $this_template_url; ?>/img/loading.svg">
        </div>

        <?php render_custom_assets('body_bottom'); ?>
	
	</body>
</html>