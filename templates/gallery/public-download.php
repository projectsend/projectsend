<?php
/*
Template name: Gallery - Download Page
URI: https://www.projectsend.org/templates/gallery
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: Shows only images (jpg, gif, and png). Do not use if you plan to upload other file types! They will not be shown.
*/
$ld = 'cftp_template_gallery'; // specify the language domain for this template

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

if (!$can_view) {
    header("Location: " . BASE_URI);
    exit;
}

$window_title = __('Download file','cftp_template_gallery');

define('TEMPLATE_THUMBNAILS_WIDTH', '400');
define('TEMPLATE_THUMBNAILS_HEIGHT', '300');
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

    <style>
        /* Additional styles for download page */
        .download-container {
            max-width: 800px;
            margin: 30px auto;
            background: rgba(0,0,0,0.8);
            border: 1px solid #333;
            border-radius: 7px;
            overflow: hidden;
            box-shadow: 0 1px 4px #111;
        }

        .download-header {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #333;
        }

        .download-header h1 {
            font-size: 2.2em;
            color: #f0f0f0;
            margin-bottom: 10px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
        }

        .file-icon {
            font-size: 4em;
            color: #dedede;
            margin-bottom: 15px;
        }

        .file-preview {
            text-align: center;
            margin: 20px 0;
            background: #111;
            padding: 20px;
            border-radius: 4px;
        }

        .file-preview img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }

        .file-info {
            padding: 20px;
            background: rgba(0,0,0,0.6);
        }

        .file-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .file-detail {
            background: rgba(255,255,255,0.05);
            padding: 12px;
            border-radius: 4px;
            border-left: 3px solid #dedede;
        }

        .file-detail strong {
            display: block;
            color: #f0f0f0;
            font-size: 0.9em;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .file-detail span {
            color: #ccc;
            font-size: 1.1em;
        }

        .file-description {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 3px solid #dedede;
            color: #ccc;
        }

        .download-actions {
            text-align: center;
            padding: 20px;
            background: rgba(255,255,255,0.05);
            border-top: 1px solid #333;
        }

        .download-button {
            display: inline-block;
            background: #444;
            color: #f0f0f0;
            padding: 15px 30px;
            font-size: 1.3em;
            font-weight: bold;
            text-decoration: none;
            border-radius: 4px;
            margin: 0 10px;
            transition: all 0.3s ease;
            border: 1px solid #555;
            font-family: 'Montserrat', sans-serif;
        }

        .download-button:hover {
            background: #555;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.5);
            text-decoration: none;
            color: #fff;
        }

        .preview-button {
            background: #333;
            color: #ccc;
            padding: 10px 20px;
            font-size: 1em;
            text-decoration: none;
            border-radius: 4px;
            margin: 0 5px;
            transition: all 0.3s ease;
            border: 1px solid #444;
        }

        .preview-button:hover {
            background: #444;
            text-decoration: none;
            color: #f0f0f0;
        }

        .back-link {
            display: inline-block;
            color: #ccc;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 1.1em;
        }

        .back-link:hover {
            color: #f0f0f0;
            text-decoration: none;
        }

        .back-link i {
            margin-right: 8px;
        }

        .access-denied {
            text-align: center;
            padding: 50px 20px;
            color: #ccc;
            font-size: 1.3em;
        }

        .access-denied i {
            font-size: 3em;
            color: #444;
            margin-bottom: 20px;
            display: block;
        }

        /* Modal styles */
        .preview-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
        }

        .preview-modal-content {
            position: relative;
            margin: 2% auto;
            width: 95%;
            max-width: 1000px;
            background: rgba(0,0,0,0.9);
            border-radius: 4px;
            overflow: hidden;
            border: 1px solid #333;
        }

        .preview-modal-header {
            background: rgba(255,255,255,0.1);
            color: #f0f0f0;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #333;
        }

        .preview-modal-close {
            color: #f0f0f0;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
        }

        .preview-modal-close:hover {
            opacity: 0.8;
        }

        .preview-modal-body {
            padding: 20px;
            max-height: 80vh;
            overflow: auto;
            text-align: center;
        }

        @media (max-width: 768px) {
            .download-container {
                margin: 10px;
                max-width: none;
            }

            .file-details {
                grid-template-columns: 1fr;
            }

            .download-button {
                display: block;
                margin: 10px 0;
            }

            .preview-modal-content {
                width: 98%;
                margin: 1% auto;
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/js-cookie@rc/dist/js.cookie.min.js"></script>
    <script src="<?php echo $this_template_url; ?>/js/template.js"></script>
    <script>
        window.base_url = '<?php echo BASE_URI; ?>';

        function openPreview(url, title) {
            console.log('Opening preview for:', title, url);
            var modal = document.getElementById('previewModal');
            var modalTitle = document.getElementById('previewModalTitle');
            var modalBody = document.getElementById('previewModalBody');

            if (modal && modalTitle && modalBody) {
                modalTitle.textContent = title;
                modalBody.innerHTML = '<div style="text-align: center; padding: 20px; color: #ccc;">Loading preview...</div>';
                modal.style.display = 'block';

                // Fetch the preview data
                fetch(url)
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.text();
                    })
                    .then(text => {
                        console.log('Raw response:', text);

                        try {
                            const data = JSON.parse(text);
                            console.log('Parsed data:', data);
                            let previewHtml = '';

                            if (data.file_url && data.type) {
                                switch(data.type) {
                                    case 'image':
                                        previewHtml = `<img src="${data.file_url}" alt="${title}" style="max-width: 100%; height: auto; display: block; margin: 0 auto; border-radius: 4px;">`;
                                        break;
                                    case 'pdf':
                                        previewHtml = `<iframe src="${data.file_url}" style="width: 100%; height: 600px; border: none; border-radius: 4px;"></iframe>`;
                                        break;
                                    default:
                                        previewHtml = `<div style="text-align: center; padding: 20px; color: #ccc;">Preview not available for this file type: ${data.type}</div>`;
                                }
                            } else if (data.error || data.message) {
                                previewHtml = `<div style="text-align: center; padding: 20px; color: #ccc;">Error: ${data.message || data.error}</div>`;
                            } else {
                                previewHtml = `<div style="text-align: center; padding: 20px; color: #ccc;">Invalid response format</div>`;
                            }

                            modalBody.innerHTML = previewHtml;
                        } catch (parseError) {
                            console.error('JSON parse error:', parseError);
                            modalBody.innerHTML = `<div style="text-align: center; padding: 20px; color: #ccc;">Invalid response format<br><small>${text.substring(0, 200)}...</small></div>`;
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        modalBody.innerHTML = '<div style="text-align: center; padding: 20px; color: #ccc;">Network error loading preview.</div>';
                    });
            } else {
                console.error('Modal elements not found');
            }
        }

        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
            document.getElementById('previewModalBody').innerHTML = '';
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreview();
            }
        });

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            var modal = document.getElementById('previewModal');
            if (e.target === modal) {
                closePreview();
            }
        });
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
            <?php if ($can_view && !empty($file)) { ?>
                <a href="javascript:history.back()" class="back-link">
                    <i class="fa fa-arrow-left"></i> <?php _e('Back', 'cftp_template_gallery'); ?>
                </a>

                <div class="download-container">
                    <div class="download-header">
                        <div class="file-icon">
                            <?php
                                if ($file->isImage()) {
                                    echo '<i class="fa fa-picture-o"></i>';
                                } else {
                                    echo '<i class="fa fa-file-o"></i>';
                                }
                            ?>
                        </div>
                        <h1><?php echo html_output($file->title); ?></h1>
                    </div>

                    <div class="file-info">
                        <?php if ($file->isImage() && $can_download) { ?>
                            <div class="file-preview">
                                <?php
                                    $thumbnail = make_thumbnail(UPLOADED_FILES_DIR.DS.$file->filename_on_disk, 'proportional', TEMPLATE_THUMBNAILS_WIDTH, TEMPLATE_THUMBNAILS_HEIGHT);
                                    if (!empty($thumbnail) && isset($thumbnail['thumbnail']) && !empty($thumbnail['thumbnail']['exists'])) {
                                ?>
                                    <img src="<?php echo $thumbnail['thumbnail']['url']; ?>" alt="<?php echo html_output($file->title); ?>" />
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <div class="file-details">
                            <div class="file-detail">
                                <strong><?php _e('File name', 'cftp_template_gallery'); ?></strong>
                                <span><?php echo html_output($file->filename_original); ?></span>
                            </div>
                            <div class="file-detail">
                                <strong><?php _e('File size', 'cftp_template_gallery'); ?></strong>
                                <span><?php echo $file->size_formatted; ?></span>
                            </div>
                            <div class="file-detail">
                                <strong><?php _e('File type', 'cftp_template_gallery'); ?></strong>
                                <span><?php echo strtoupper($file->extension); ?></span>
                            </div>
                            <div class="file-detail">
                                <strong><?php _e('Upload date', 'cftp_template_gallery'); ?></strong>
                                <span><?php echo !empty($file->uploaded_date) ? format_date($file->uploaded_date) : __('Unknown', 'cftp_template_gallery'); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($file->description)) { ?>
                            <div class="file-description">
                                <strong><?php _e('Description', 'cftp_template_gallery'); ?>:</strong><br>
                                <?php echo !empty($file->description) ? format_description($file->description) : ''; ?>
                            </div>
                        <?php } ?>

                        <?php if ($file->isImage()) {
                            $dimensions = $file->getDimensions();
                            if (!empty($dimensions['width'])) {
                        ?>
                                <div class="file-description">
                                    <strong><?php _e('Dimensions', 'cftp_template_gallery'); ?>:</strong><br>
                                    <?php echo $dimensions['width']; ?> x <?php echo $dimensions['height']; ?> px
                                </div>
                        <?php
                            }
                        } ?>

                        <?php if ($file->expires == '1') { ?>
                            <div class="file-description">
                                <strong><?php _e('Expiration date', 'cftp_template_gallery'); ?>:</strong><br>
                                <span style="color: #dedede; font-weight: bold;">
                                    <?php echo date(get_option('timeformat'), strtotime($file->expiry_date)); ?>
                                </span>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="download-actions">
                        <?php if ($can_download) { ?>
                            <a href="<?php echo $file->download_link; ?>" class="download-button">
                                <i class="fa fa-download"></i> <?php _e('Download', 'cftp_template_gallery'); ?>
                            </a>

                            <?php
                                // Preview for images only (gallery theme is image-focused)
                                if ($file->isImage()) {
                                    $preview_url = BASE_URI . 'process.php?do=get_preview&file_id=' . $file->id;
                            ?>
                                <br><br>
                                <a href="#" onclick="openPreview('<?php echo $preview_url; ?>', '<?php echo addslashes($file->title); ?>'); return false;" class="preview-button">
                                    <i class="fa fa-eye"></i> <?php _e('Preview', 'cftp_template_gallery'); ?>
                                </a>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="access-denied">
                                <i class="fa fa-lock"></i>
                                <?php _e('This file is not available for download.', 'cftp_template_gallery'); ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>

            <?php } else { ?>
                <div class="download-container">
                    <div class="access-denied">
                        <i class="fa fa-exclamation-triangle"></i>
                        <?php _e('Access denied or file not found.', 'cftp_template_gallery'); ?>
                    </div>
                </div>
            <?php } ?>
		</div>

		<footer>
			<?php render_footer_text(); ?>
		</footer>

	</div>

</div>

<!-- Preview Modal -->
<div id="previewModal" class="preview-modal">
    <div class="preview-modal-content">
        <div class="preview-modal-header">
            <h3 id="previewModalTitle"><?php _e('File Preview', 'cftp_template_gallery'); ?></h3>
            <button class="preview-modal-close" onclick="closePreview()">&times;</button>
        </div>
        <div class="preview-modal-body" id="previewModalBody">
            <!-- Preview content will be loaded here -->
        </div>
    </div>
</div>

<?php render_custom_assets('body_bottom'); ?>

</body>
</html>