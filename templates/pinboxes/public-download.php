<?php
/*
Template name: PinBoxes - Download Page
URI: https://www.projectsend.org/templates/pinboxes
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: Inspired by the awesome design of Pinterest!
*/
$ld = 'pinboxes_template'; // specify the language domain for this template

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

if (!$can_view) {
    header("Location: " . BASE_URI);
    exit;
}

$window_title = __('Download file','pinboxes_template');

define('TEMPLATE_THUMBNAILS_WIDTH', '300');
define('TEMPLATE_THUMBNAILS_HEIGHT', '300');
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title><?php echo html_output( $window_title . ' &raquo; ' . SYSTEM_NAME ); ?></title>
		<?php meta_favicon(); ?>
		<link href='https://fonts.googleapis.com/css?family=Metrophobic' rel='stylesheet' type='text/css'>
		<link rel="stylesheet" href="<?php echo $this_template_url; ?>/font-awesome-4.6.3/css/font-awesome.min.css">
		<link rel="stylesheet" media="all" type="text/css" href="<?php echo $this_template_url; ?>main.min.css" />

        <style>
            /* Additional styles for download page */
            .download-container {
                max-width: 600px;
                margin: 30px auto;
                background: #fff;
                border: 1px solid #eee;
                border-top: 4px solid #de3c4b;
                border-radius: 6px;
                overflow: hidden;
            }

            .download-header {
                background: #f9f9f9;
                padding: 20px;
                text-align: center;
                border-bottom: 1px solid #eee;
            }

            .download-header h1 {
                font-size: 2.2em;
                color: #333;
                margin-bottom: 10px;
                font-family: "Metrophobic", Georgia, serif;
            }

            .file-icon {
                font-size: 4em;
                color: #de3c4b;
                margin-bottom: 15px;
            }

            .file-preview {
                text-align: center;
                margin: 20px 0;
            }

            .file-preview img {
                max-width: 100%;
                max-height: 300px;
                border-radius: 4px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .file-info {
                padding: 20px;
                background: #fff;
            }

            .file-details {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 20px;
            }

            .file-detail {
                background: #f8f8f8;
                padding: 12px;
                border-radius: 4px;
                border-left: 3px solid #de3c4b;
            }

            .file-detail strong {
                display: block;
                color: #333;
                font-size: 0.9em;
                text-transform: uppercase;
                margin-bottom: 5px;
            }

            .file-detail span {
                color: #666;
                font-size: 1.1em;
            }

            .file-description {
                background: #f9f9f9;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 20px;
                border-left: 3px solid #de3c4b;
            }

            .download-actions {
                text-align: center;
                padding: 20px;
                background: #f9f9f9;
                border-top: 1px dashed #ddd;
            }

            .download-button {
                display: inline-block;
                background: #de3c4b;
                color: white;
                padding: 15px 30px;
                font-size: 1.3em;
                font-weight: bold;
                text-decoration: none;
                border-radius: 5px;
                margin: 0 10px;
                transition: all 0.3s ease;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }

            .download-button:hover {
                background: #303036;
                transform: translateY(-1px);
                box-shadow: 0 3px 6px rgba(0,0,0,0.3);
                text-decoration: none;
                color: white;
            }

            .preview-button {
                background: #666;
                color: white;
                padding: 10px 20px;
                font-size: 1em;
                text-decoration: none;
                border-radius: 4px;
                margin: 0 5px;
                transition: all 0.3s ease;
            }

            .preview-button:hover {
                background: #303036;
                text-decoration: none;
                color: white;
            }

            .back-link {
                display: inline-block;
                color: #666;
                text-decoration: none;
                margin-bottom: 20px;
                font-size: 1.1em;
            }

            .back-link:hover {
                color: #de3c4b;
                text-decoration: none;
            }

            .back-link i {
                margin-right: 8px;
            }

            .access-denied {
                text-align: center;
                padding: 50px 20px;
                color: #666;
                font-size: 1.3em;
            }

            .access-denied i {
                font-size: 3em;
                color: #ccc;
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
                background-color: rgba(0,0,0,0.8);
            }

            .preview-modal-content {
                position: relative;
                margin: 5% auto;
                width: 90%;
                max-width: 800px;
                background: white;
                border-radius: 6px;
                overflow: hidden;
            }

            .preview-modal-header {
                background: #de3c4b;
                color: white;
                padding: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .preview-modal-close {
                color: white;
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
                max-height: 70vh;
                overflow: auto;
            }

            .preview-iframe {
                width: 100%;
                height: 500px;
                border: none;
                border-radius: 4px;
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
                    width: 95%;
                    margin: 2% auto;
                }
            }
        </style>

        <script>
            window.base_url = '<?php echo BASE_URI; ?>';
        </script>
        <script src="<?php echo $this_template_url; ?>/js/jquery.1.11.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/js-cookie@rc/dist/js.cookie.min.js"></script>

        <script>
            function openPreview(url, title) {
                console.log('Opening preview for:', title, url);
                var modal = document.getElementById('previewModal');
                var modalTitle = document.getElementById('previewModalTitle');
                var modalBody = document.getElementById('previewModalBody');

                if (modal && modalTitle && modalBody) {
                    modalTitle.textContent = title;
                    modalBody.innerHTML = '<div style="text-align: center; padding: 20px;">Loading preview...</div>';
                    modal.style.display = 'block';

                    // Fetch the preview data
                    fetch(url)
                        .then(response => {
                            console.log('Response status:', response.status);
                            return response.text(); // Get as text first to see what we're getting
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
                                            previewHtml = `<img src="${data.file_url}" alt="${title}" style="max-width: 100%; height: auto; display: block; margin: 0 auto;">`;
                                            break;
                                        case 'pdf':
                                            previewHtml = `<iframe src="${data.file_url}" style="width: 100%; height: 500px; border: none;"></iframe>`;
                                            break;
                                        default:
                                            previewHtml = `<div style="text-align: center; padding: 20px;">Preview not available for this file type: ${data.type}</div>`;
                                    }
                                } else if (data.error || data.message) {
                                    previewHtml = `<div style="text-align: center; padding: 20px;">Error: ${data.message || data.error}</div>`;
                                } else {
                                    previewHtml = `<div style="text-align: center; padding: 20px;">Invalid response format</div>`;
                                }

                                modalBody.innerHTML = previewHtml;
                            } catch (parseError) {
                                console.error('JSON parse error:', parseError);
                                modalBody.innerHTML = `<div style="text-align: center; padding: 20px;">Invalid response format<br><small>${text.substring(0, 200)}...</small></div>`;
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            modalBody.innerHTML = '<div style="text-align: center; padding: 20px;">Network error loading preview.</div>';
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

		<div id="header">
			<?php if ($logo_file_info['exists'] === true) { ?>
				<div id="branding">
                    <?php echo get_branding_layout(true); // true: returns the thumbnail, not the full image ?>
				</div>
			<?php } ?>
		</div>

		<div id="menu">
			<p class="welcome">
				<?php _e('File Download','pinboxes_template'); ?>
			</p>
			<ul>
				<li>
                    <a href="<?php echo BASE_URI; ?>index.php" target="_self">
                        <i class="fa fa-sign-in" aria-hidden="true"></i> <?php _e('Login', 'pinboxes_template'); ?>
                    </a>
				</li>
			</ul>
		</div>

		<div id="content">
			<div class="wrapper">

                <?php if ($can_view && !empty($file)) { ?>
                    <a href="javascript:history.back()" class="back-link">
                        <i class="fa fa-arrow-left"></i> <?php _e('Back', 'pinboxes_template'); ?>
                    </a>

                    <div class="download-container">
                        <div class="download-header">
                            <div class="file-icon">
                                <?php
                                    if ($file->isImage()) {
                                        echo '<i class="fa fa-picture-o"></i>';
                                    } elseif (in_array($file->extension, ['pdf'])) {
                                        echo '<i class="fa fa-file-pdf-o"></i>';
                                    } elseif (in_array($file->extension, ['doc', 'docx'])) {
                                        echo '<i class="fa fa-file-word-o"></i>';
                                    } elseif (in_array($file->extension, ['xls', 'xlsx'])) {
                                        echo '<i class="fa fa-file-excel-o"></i>';
                                    } elseif (in_array($file->extension, ['zip', 'rar', '7z'])) {
                                        echo '<i class="fa fa-file-archive-o"></i>';
                                    } elseif (in_array($file->extension, ['mp4', 'avi', 'mov'])) {
                                        echo '<i class="fa fa-file-video-o"></i>';
                                    } elseif (in_array($file->extension, ['mp3', 'wav', 'ogg'])) {
                                        echo '<i class="fa fa-file-audio-o"></i>';
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
                                    <strong><?php _e('File name', 'pinboxes_template'); ?></strong>
                                    <span><?php echo html_output($file->filename_original); ?></span>
                                </div>
                                <div class="file-detail">
                                    <strong><?php _e('File size', 'pinboxes_template'); ?></strong>
                                    <span><?php echo $file->size_formatted; ?></span>
                                </div>
                                <div class="file-detail">
                                    <strong><?php _e('File type', 'pinboxes_template'); ?></strong>
                                    <span><?php echo strtoupper($file->extension); ?></span>
                                </div>
                                <div class="file-detail">
                                    <strong><?php _e('Upload date', 'pinboxes_template'); ?></strong>
                                    <span><?php echo !empty($file->uploaded_date) ? format_date($file->uploaded_date) : __('Unknown', 'pinboxes_template'); ?></span>
                                </div>
                            </div>

                            <?php if (!empty($file->description)) { ?>
                                <div class="file-description">
                                    <strong><?php _e('Description', 'pinboxes_template'); ?>:</strong><br>
                                    <?php echo format_description($file->description); ?>
                                </div>
                            <?php } ?>

                            <?php if ($file->isImage()) {
                                $dimensions = $file->getDimensions();
                                if (!empty($dimensions['width'])) {
                            ?>
                                    <div class="file-description">
                                        <strong><?php _e('Dimensions', 'pinboxes_template'); ?>:</strong><br>
                                        <?php echo $dimensions['width']; ?> x <?php echo $dimensions['height']; ?> px
                                    </div>
                            <?php
                                }
                            } ?>

                            <?php if ($file->expires == '1') { ?>
                                <div class="file-description">
                                    <strong><?php _e('Expiration date', 'pinboxes_template'); ?>:</strong><br>
                                    <span style="color: #de3c4b; font-weight: bold;">
                                        <?php echo date(get_option('timeformat'), strtotime($file->expiry_date)); ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="download-actions">
                            <?php if ($can_download) { ?>
                                <a href="<?php echo $file->download_link; ?>" class="download-button">
                                    <i class="fa fa-download"></i> <?php _e('Download', 'pinboxes_template'); ?>
                                </a>

                                <?php
                                    // Preview for embeddable files
                                    $can_preview = false;
                                    $preview_url = '';

                                    if ($file->isImage()) {
                                        $can_preview = true;
                                        $preview_url = BASE_URI . 'process.php?do=get_preview&file_id=' . $file->id;
                                    } elseif (in_array($file->extension, ['pdf'])) {
                                        $can_preview = true;
                                        $preview_url = BASE_URI . 'process.php?do=get_preview&file_id=' . $file->id;
                                    }

                                    if ($can_preview) {
                                ?>
                                    <br><br>
                                    <a href="#" onclick="openPreview('<?php echo $preview_url; ?>', '<?php echo addslashes($file->title); ?>'); return false;" class="preview-button">
                                        <i class="fa fa-eye"></i> <?php _e('Preview', 'pinboxes_template'); ?>
                                    </a>
                                <?php } ?>
                            <?php } else { ?>
                                <div class="access-denied">
                                    <i class="fa fa-lock"></i>
                                    <?php _e('This file is not available for download.', 'pinboxes_template'); ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                <?php } else { ?>
                    <div class="download-container">
                        <div class="access-denied">
                            <i class="fa fa-exclamation-triangle"></i>
                            <?php _e('Access denied or file not found.', 'pinboxes_template'); ?>
                        </div>
                    </div>
                <?php } ?>

			</div>

			<?php render_footer_text(); ?>
        </div>

        <!-- Preview Modal -->
        <div id="previewModal" class="preview-modal">
            <div class="preview-modal-content">
                <div class="preview-modal-header">
                    <h3 id="previewModalTitle"><?php _e('File Preview', 'pinboxes_template'); ?></h3>
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