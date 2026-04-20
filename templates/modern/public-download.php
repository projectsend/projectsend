<?php
/*
Template name: Modern Cards - Public Download
URI: https://www.projectsend.org/templates/modern
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: Public download view for the Modern Cards template with card-based layout
*/
$ld = 'modern_template'; // specify the language domain for this template

define('TEMPLATE_RESULTS_PER_PAGE', get_option('pagination_results_per_page'));
define('TEMPLATE_THUMBNAILS_WIDTH', '280');
define('TEMPLATE_THUMBNAILS_HEIGHT', '200');

$window_title = __('File Download', 'modern_template') . ' - ' . $file->title;

$page_id = 'modern_template_download';

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

$body_class = array('template', 'modern-template', 'modern-download', 'hide_title');

// Determine logged-in state
$is_logged_in = defined('CURRENT_USER_ID') && CURRENT_USER_ID !== null;

// Get client info if logged in
if ($is_logged_in) {
    $client_info = get_client_by_id(CURRENT_USER_ID);
}

// Header buttons for logged users
$header_action_buttons = [];
if ($is_logged_in && current_user_can_upload()) {
    $header_action_buttons[] = [
        'url' => BASE_URI.'upload.php',
        'label' => __('Upload file', 'cftp_admin'),
    ];
}

?>
<!DOCTYPE html>
<html lang="<?php echo SITE_LANG; ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_output( $window_title . ' &raquo; ' . SYSTEM_NAME ); ?></title>
    <?php meta_favicon(); ?>

    <link rel="stylesheet" href="<?php echo $this_template_url; ?>font-awesome-4.6.3/css/font-awesome.min.css">
    <link rel="stylesheet" media="all" type="text/css" href="<?php echo $this_template_url; ?>main.css" />

    <style>
        /* Download page specific styles */
        .download-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .file-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .file-icon {
            font-size: 3rem;
            color: #007cba;
            margin-right: 1rem;
        }

        .file-info h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            color: #333;
        }

        .file-info .subtitle {
            color: #666;
            font-size: 1rem;
            margin: 0;
        }

        .file-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .detail-item i {
            margin-right: 0.75rem;
            color: #007cba;
            width: 20px;
            text-align: center;
        }

        .detail-content .label {
            font-weight: 600;
            color: #333;
            font-size: 0.875rem;
        }

        .detail-content .value {
            color: #666;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .file-description {
            margin-bottom: 2rem;
        }

        .file-description h3 {
            margin: 0 0 0.75rem 0;
            font-size: 1.125rem;
            color: #333;
        }

        .file-description p {
            color: #666;
            line-height: 1.5;
            margin: 0;
        }

        .preview-section {
            margin-bottom: 2rem;
            text-align: center;
        }

        .preview-section img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .download-actions {
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .btn-download {
            background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
            color: white;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,124,186,0.3);
        }

        .btn-download:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,124,186,0.4);
            color: white;
            text-decoration: none;
        }

        .btn-download i {
            margin-right: 0.5rem;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-size: 0.875rem;
            margin-right: 0.5rem;
        }

        .btn-secondary:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }

        .back-link {
            text-align: center;
            margin-top: 1rem;
        }

        .back-link a {
            color: #007cba;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .access-denied {
            text-align: center;
            padding: 3rem 2rem;
            color: #dc3545;
        }

        .access-denied i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.7;
        }

        .access-denied h2 {
            margin: 0 0 1rem 0;
            font-size: 1.5rem;
        }

        .access-denied p {
            margin: 0;
            color: #666;
        }

        /* Preview Modal */
        .preview-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: none;
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .preview-modal.show {
            display: flex;
        }

        .preview-content {
            background: white;
            border-radius: 8px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
            position: relative;
        }

        .preview-header {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .preview-header h3 {
            margin: 0;
            font-size: 1.125rem;
        }

        .preview-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            margin-left: auto;
        }

        .preview-body {
            padding: 1rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .download-card {
                margin: 1rem;
                padding: 1.5rem;
            }

            .file-header {
                flex-direction: column;
                text-align: center;
            }

            .file-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }

            .file-details {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <?php render_custom_assets('head'); ?>
</head>

<body>
    <?php render_custom_assets('body_top'); ?>

<div class="modern-template-wrapper">
    <!-- Mobile Sidebar Toggle -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="modern-sidebar">
        <!-- Logo Section -->
        <div class="sidebar-logo">
            <?php if ($logo_file_info['exists'] === true) { ?>
                <?php echo get_branding_layout(true); ?>
            <?php } else { ?>
                <div class="default-logo">
                    <h2><?php echo SYSTEM_NAME; ?></h2>
                </div>
            <?php } ?>
        </div>

        <!-- Navigation Menu -->
        <nav class="sidebar-nav">
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="<?php echo BASE_URI; ?>public.php" class="nav-link">
                        <i class="fa fa-folder-open"></i>
                        <span><?php echo __('Public Files', 'modern_template'); ?></span>
                    </a>
                </li>
                <?php if ($is_logged_in) { ?>
                <li class="nav-item">
                    <a href="<?php echo CLIENT_VIEW_FILE_LIST_URL; ?>" class="nav-link">
                        <i class="fa fa-files-o"></i>
                        <span><?php echo __('My Files', 'modern_template'); ?></span>
                    </a>
                </li>
                <?php if (current_user_can_upload()) { ?>
                <li class="nav-item">
                    <a href="<?php echo BASE_URI; ?>upload.php" class="nav-link">
                        <i class="fa fa-cloud-upload"></i>
                        <span><?php echo __('Upload Files', 'modern_template'); ?></span>
                    </a>
                </li>
                <?php } ?>
                <li class="nav-item">
                    <a href="<?php echo BASE_URI; ?>manage-files.php" class="nav-link">
                        <i class="fa fa-cog"></i>
                        <span><?php echo __('Manage Files', 'modern_template'); ?></span>
                    </a>
                </li>
                <?php } ?>
            </ul>
        </nav>

        <!-- Profile Section or Login -->
        <div class="sidebar-profile">
            <?php if ($is_logged_in) { ?>
                <div class="profile-info">
                    <div class="profile-avatar">
                        <?php
                        $user_name = $client_info['name'];
                        $initials = '';
                        $name_parts = explode(' ', trim($user_name));
                        foreach ($name_parts as $part) {
                            if (!empty($part)) {
                                $initials .= strtoupper(substr($part, 0, 1));
                                if (strlen($initials) >= 2) break;
                            }
                        }
                        if (empty($initials)) {
                            $initials = strtoupper(substr($user_name, 0, 2));
                        }
                        ?>
                        <span class="avatar-initials"><?php echo htmlspecialchars($initials); ?></span>
                    </div>
                    <div class="profile-details">
                        <div class="profile-name"><?php echo htmlspecialchars($client_info['name']); ?></div>
                        <div class="profile-email"><?php echo htmlspecialchars($client_info['email']); ?></div>
                    </div>
                </div>
                <div class="profile-actions">
                    <a href="<?php echo BASE_URI; ?>process.php?do=logout" class="btn btn-modern btn-logout">
                        <i class="fa fa-sign-out"></i>
                        <span><?php echo __('Logout', 'modern_template'); ?></span>
                    </a>
                </div>
            <?php } else { ?>
                <div class="login-prompt">
                    <div class="login-info">
                        <h3><?php echo __('Welcome', 'modern_template'); ?></h3>
                        <p><?php echo __('Access your files by logging in', 'modern_template'); ?></p>
                    </div>
                    <div class="login-actions">
                        <a href="<?php echo BASE_URI; ?>index.php" class="btn btn-modern btn-primary">
                            <i class="fa fa-sign-in"></i>
                            <span><?php echo __('Login', 'modern_template'); ?></span>
                        </a>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="modern-main-content">
        <!-- Header Section -->
        <div class="modern-header">
            <div class="header-content">
                <h1 class="page-title">
                    <?php echo __('File Download', 'modern_template'); ?>
                </h1>
            </div>
        </div>

        <!-- Download Content -->
        <?php if ($can_view): ?>
            <div class="download-card">
                <!-- File Header -->
                <div class="file-header">
                    <div class="file-icon">
                        <i class="fa <?php echo get_file_type_icon($file->extension); ?>"></i>
                    </div>
                    <div class="file-info">
                        <h1><?php echo html_output($file->filename_original); ?></h1>
                        <?php if ($file->filename_original != $file->title): ?>
                            <p class="subtitle"><?php echo html_output($file->title); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- File Details -->
                <div class="file-details">
                    <div class="detail-item">
                        <i class="fa fa-hdd-o"></i>
                        <div class="detail-content">
                            <div class="label"><?php echo __('File Size', 'modern_template'); ?></div>
                            <div class="value">
                                <?php echo $file->size_formatted; ?>
                                <?php
                                if (file_is_image($file->full_path)) {
                                    $dimensions = $file->getDimensions();
                                    if (!empty($dimensions)) {
                                        echo ' • ' . $dimensions['width'] . ' × ' . $dimensions['height'] . ' px';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="detail-item">
                        <i class="fa fa-tag"></i>
                        <div class="detail-content">
                            <div class="label"><?php echo __('File Type', 'modern_template'); ?></div>
                            <div class="value"><?php echo strtoupper($file->extension); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <?php if (!empty($file->description)): ?>
                    <div class="file-description">
                        <h3><?php echo __('Description', 'modern_template'); ?></h3>
                        <div><?php echo format_description($file->description); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Preview Section -->
                <?php if (get_option('public_listing_enable_preview') == 1): ?>
                    <div class="preview-section">
                        <?php if (file_is_image($file->full_path)): ?>
                            <?php
                            $thumbnail = make_thumbnail($file->full_path, null, 400, 300);
                            if (!empty($thumbnail['thumbnail']['url'])): ?>
                                <img src="<?php echo $thumbnail['thumbnail']['url']; ?>"
                                     alt="<?php echo html_output($file->title); ?>">
                            <?php endif; ?>
                        <?php elseif ($file->embeddable): ?>
                            <button onclick="showPreview('<?php echo BASE_URI; ?>process.php?do=get_preview&file_id=<?php echo $file->id; ?>')"
                                    class="btn-secondary">
                                <i class="fa fa-eye"></i>
                                <?php echo __('Preview', 'modern_template'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Download Actions -->
                <div class="download-actions">
                    <?php if ($can_download): ?>
                        <a href="<?php echo $file->public_url . '&download'; ?>" class="btn-download">
                            <i class="fa fa-download"></i>
                            <?php echo __('Download File', 'modern_template'); ?>
                        </a>
                    <?php else: ?>
                        <div class="access-denied">
                            <p style="color: #dc3545; font-weight: 600;">
                                <?php echo __('Download not available', 'modern_template'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Back Link -->
                <div class="back-link">
                    <a href="<?php echo BASE_URI; ?>public.php">
                        <i class="fa fa-arrow-left"></i>
                        <?php echo __('Back to Public Files', 'modern_template'); ?>
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- Access Denied -->
            <div class="download-card">
                <div class="access-denied">
                    <i class="fa fa-ban"></i>
                    <h2><?php echo __('Access Denied', 'modern_template'); ?></h2>
                    <p><?php echo __('You do not have permission to view this file.', 'modern_template'); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer within main content -->
        <div class="modern-footer">
            <?php render_footer_text(); ?>
        </div>

    </div> <!-- End modern-main-content -->
</div> <!-- End modern-template-wrapper -->

<!-- Preview Modal -->
<div id="previewModal" class="preview-modal">
    <div class="preview-content">
        <div class="preview-header">
            <h3><?php echo __('File Preview', 'modern_template'); ?></h3>
            <button class="preview-close" onclick="closePreview()">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <div class="preview-body" id="previewBody">
            <!-- Preview content will be loaded here -->
        </div>
    </div>
</div>

<!-- Modern Template JavaScript -->
<script src="<?php echo $this_template_url; ?>js/template.js"></script>

<script>
function showPreview(url) {
    const modal = document.getElementById('previewModal');
    const body = document.getElementById('previewBody');

    body.innerHTML = '<div style="padding: 2rem; text-align: center;"><i class="fa fa-spinner fa-spin" style="font-size: 2rem; color: #007cba;"></i></div>';
    modal.classList.add('show');

    fetch(url)
        .then(response => response.json())
        .then(data => {
            let previewHtml = '';

            switch(data.type) {
                case 'image':
                    previewHtml = `<img src="${data.file_url}" alt="${data.name}" style="max-width: 100%; height: auto;">`;
                    break;
                case 'video':
                    previewHtml = `<video controls style="max-width: 100%; height: auto;">
                                    <source src="${data.file_url}" type="${data.mime_type}">
                                    Your browser does not support video playback.
                                  </video>`;
                    break;
                case 'audio':
                    previewHtml = `<audio controls style="width: 100%;">
                                    <source src="${data.file_url}" type="${data.mime_type}">
                                    Your browser does not support audio playback.
                                  </audio>`;
                    break;
                case 'pdf':
                    previewHtml = `<iframe src="${data.file_url}" style="width: 100%; height: 400px; border: none;"></iframe>`;
                    break;
                default:
                    previewHtml = `<div style="padding: 2rem; text-align: center;">
                                    <i class="fa fa-file-o" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                                    <p>Preview not available for this file type.</p>
                                  </div>`;
            }

            body.innerHTML = previewHtml;
        })
        .catch(error => {
            body.innerHTML = '<div style="padding: 2rem; text-align: center; color: #dc3545;">Error loading preview.</div>';
        });
}

function closePreview() {
    document.getElementById('previewModal').classList.remove('show');
    document.getElementById('previewBody').innerHTML = '';
}

// Close modal on background click
document.getElementById('previewModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePreview();
    }
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePreview();
    }
});
</script>

<?php render_custom_assets('body_bottom'); ?>

</body>
</html>