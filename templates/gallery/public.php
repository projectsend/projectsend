<?php
/*
Template name: Gallery - Public Files
URI: https://www.projectsend.org/templates/gallery
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: Shows only images (jpg, gif, and png). Do not use if you plan to upload other file types! They will not be shown.
*/

$ld = 'cftp_template_gallery'; // specify the language domain for this template

$count = $files['pagination']['total'];

if ($count == 0) {
    if (isset($_GET['search'])) {
        $no_results_message = __('Your search keywords returned no results.', 'cftp_template_gallery');
    } else {
        $no_results_message = __('There are no images available.', 'cftp_template_gallery');
    }
}

$groups = get_groups([
    'public' => true,
]);

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

/**
 * Filter files by type, only save images.
*/
$img_files = [];
foreach ($files['files_ids'] as $file_id) {
    $file = new \ProjectSend\Classes\Files($file_id);
    if ($file->isImage()) {
        $img_files[] = $file;
    }
}
$count = count($img_files);

$window_title = __('Gallery','cftp_template_gallery');

define('TEMPLATE_THUMBNAILS_WIDTH', '280');
define('TEMPLATE_THUMBNAILS_HEIGHT', '215');
?>
<!doctype html>
<html lang="<?php echo SITE_LANG; ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo html_output( $window_title . ' &raquo; ' . SYSTEM_NAME ); ?></title>
	<?php meta_favicon(); ?>

	<link rel="stylesheet" href="<?php echo $this_template_url; ?>/font-awesome-4.6.3/css/font-awesome.min.css">
    <script src="<?php echo $this_template_url; ?>/js/jquery.1.11.1.min.js"></script>
	<link href='https://fonts.googleapis.com/css?family=Montserrat:400,700' rel='stylesheet' type='text/css'>

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
                        <li><a href="<?php echo BASE_URI; ?>index.php" target="_self" id="login"><i class="fa fa-sign-in" aria-hidden="true"></i> <?php _e('Login', 'cftp_admin'); ?></a></li>
					</ul>
				</nav>

				<?php if (!empty($groups)) { ?>
						<h4><?php _e('Filter by group', 'cftp_template_gallery'); ?></h4>
						<nav class="categories">
							<ul>
								<li class="filter_all_files"><a href="<?php echo BASE_URI; ?>public.php"><?php  _e('All files', 'cftp_template_gallery'); ?></a></li>
								<?php
									foreach ($groups as $group) {
										$group_url = BASE_URI . 'public.php?group=' . $group['id'] . '&token=' . $group['public_token'];
								?>
										<li><a href="<?php echo $group_url; ?>"><?php echo html_output($group['name']); ?></a></li>
								<?php
									}
								?>
							</ul>
						</nav>
				<?php } ?>
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
            <?php if (!$count) { ?>
				<?php echo $no_results_message; ?>
            <?php } else { ?>
				<ul class="photo_list">
					<?php
						foreach ($img_files as $file) {
                            $dimensions = $file->getDimensions();
					?>
							<li>
								<h5><?php echo html_output($file->title); ?></h5>
                                <?php if (!empty($dimensions['width'])) { ?>
                                    <div class="file_meta">
                                        <small>
                                            <?php echo $dimensions['width']; ?> x <?php echo $dimensions['height']; ?> px
                                        </small>
                                    </div>
                                <?php } ?>

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
                                                <img src="<?php echo $thumbnail['thumbnail']['url']; ?>" class="thumbnail" alt="<?php echo html_output($file->title); ?>" />
                                            </a>
                                        </div>
                                        <div class="actions">
                                            <div class="action">
                                                <a href="<?php echo $file->download_link; ?>" target="_blank" class="button button_gray">
                                                    <?php _e('Download','cftp_template_gallery'); ?>
                                                </a>
                                                <a href="<?php echo BASE_URI; ?>download.php?id=<?php echo $file->id; ?>&token=<?php echo $file->public_token; ?>" target="_blank" class="button button_gray">
                                                    <?php _e('View','cftp_template_gallery'); ?>
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
            <?php } ?>
		</div>

		<footer>
			<?php render_footer_text(); ?>
		</footer>

	</div>

</div>

<?php render_custom_assets('body_bottom'); ?>

</body>
</html>