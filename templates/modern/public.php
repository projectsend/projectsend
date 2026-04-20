<?php
/*
Template name: Modern Cards - Public Files
URI: https://www.projectsend.org/templates/modern
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: Public files view for the Modern Cards template with card-based layout
*/
$ld = 'modern_template'; // specify the language domain for this template

define('TEMPLATE_RESULTS_PER_PAGE', get_option('pagination_results_per_page'));
define('TEMPLATE_THUMBNAILS_WIDTH', '280');
define('TEMPLATE_THUMBNAILS_HEIGHT', '200');

$filter_by_category = isset($_GET['category']) ? $_GET['category'] : null;

$current_url = get_form_action_with_existing_parameters('public.php');

// Note: Don't include common.php in public templates - it's for client views only
// The root public.php already provides all necessary data:
// - $files (pagination and file data)
// - $count (total files count)
// - $mode ('files' or 'group')
// - $groups (available public groups)
// - $group_props (when viewing a group)

$window_title = __('Public Files', 'modern_template');

$page_id = 'modern_template_public';

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

$body_class = array('template', 'modern-template', 'modern-public', 'hide_title');

// Get count from files array
$count = isset($files['pagination']['total']) ? $files['pagination']['total'] : 0;

// Flash errors
if (!$count) {
    if (isset($no_results_error)) {
        switch ($no_results_error) {
            case 'search':
                $flash->error(__('Your search keywords returned no results.', 'cftp_admin'));
                break;
            case 'filter':
                $flash->error(__('The filters you selected returned no results.', 'cftp_admin'));
                break;
        }
    } else {
        $flash->warning(__('There are no files available.', 'cftp_admin'));
    }
}

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

// Search + filters bar data
$search_form_action = 'public.php';
$filters_form = [
    'action' => '',
    'items' => [],
];

// Note: Categories are not typically available in public view
// If needed, implement public category filtering separately

// Results count and form actions 
$elements_found_count = $count;
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'zip' => __('Download zipped', 'cftp_admin'),
];

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
        /* Public page specific styles */
        .search-wrapper {
            max-width: 600px;
            margin-bottom: 1.5rem;
        }
        
        .search-input-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .search-input-group .search-input {
            flex: 1;
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-input-group .btn {
            white-space: nowrap;
            padding: 0.5rem 1.5rem;
        }
        
        @media (max-width: 768px) {
            .search-input-group .btn-text {
                display: none;
            }
            
            .search-input-group .btn {
                padding: 0.5rem 1rem;
            }
        }
    </style>

    <script src="<?php echo $this_template_url; ?>js/jquery.1.11.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/js-cookie@rc/dist/js.cookie.min.js"></script>
    
    <script>
        window.base_url = '<?php echo BASE_URI; ?>';
        window.isPublicContext = true; // Flag for JavaScript context
    </script>

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
                <li class="nav-item active">
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
                    <?php echo __('Public Files', 'modern_template'); ?>
                    <?php if ($elements_found_count > 0) { ?>
                        <span class="files-count-badge"><?php echo $elements_found_count; ?></span>
                    <?php } ?>
                </h1>
            </div>
        </div>

    <!-- Search and Filters -->
    <div class="modern-controls">
        <!-- Simple search form for public view -->
        <div class="search-wrapper">
            <form action="<?php echo BASE_URI; ?>public.php" method="get" class="search-form">
                <?php if (isset($_GET['group'])): ?>
                    <input type="hidden" name="group" value="<?php echo htmlspecialchars($_GET['group']); ?>">
                <?php endif; ?>
                <?php if (isset($_GET['token'])): ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                <?php endif; ?>
                
                <div class="search-input-group">
                    <input type="text" name="search" 
                           value="<?php echo isset($_GET['search']) ? html_output($_GET['search']) : ''; ?>"
                           placeholder="<?php echo __('Search public files...', 'modern_template'); ?>"
                           class="form-control search-input">
                    <button type="submit" class="btn btn-modern btn-primary">
                        <i class="fa fa-search"></i>
                        <span class="btn-text"><?php echo __('Search', 'modern_template'); ?></span>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Note: Folders and breadcrumbs are not available in public view -->
        
        <!-- View Controls -->
        <div class="view-controls">
            <div class="bulk-actions">
                <!-- Bulk selection controls will be populated by JavaScript -->
                <div class="bulk-selection-info" style="display: none;">
                    <span class="selected-count">0</span> <?php echo __('files selected', 'modern_template'); ?>
                    <button type="button" class="btn btn-modern btn-sm btn-secondary" id="clear-selection">
                        <?php echo __('Clear', 'modern_template'); ?>
                    </button>
                    <button type="button" class="btn btn-modern btn-sm btn-primary" id="bulk-download">
                        <i class="fa fa-download"></i>
                        <?php echo __('Download Selected', 'modern_template'); ?>
                    </button>
                </div>
            </div>
            
            <div class="view-options">
                <button class="view-toggle active" data-view="cards" title="<?php echo __('Card View', 'modern_template'); ?>">
                    <i class="fa fa-th"></i>
                </button>
                <button class="view-toggle" data-view="list" title="<?php echo __('List View', 'modern_template'); ?>">
                    <i class="fa fa-list"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Files Content -->
    <form action="" name="files_list" method="get" class="form-inline batch_actions">
        <?php addCsrf(); ?>
        <div class="modern-content">
            <?php
            if (isset($count) && $count > 0) {
                ?>
                <div class="files-grid" id="filesGrid">
                    <?php
                    foreach ($files['files_ids'] as $file_id) {
                        $file = new \ProjectSend\Classes\Files($file_id);
                        
                        // File type classes for styling
                        $file_type_class = '';
                        if ($file->isImage()) {
                            $file_type_class = 'file-image';
                        } elseif (in_array(strtolower($file->extension), ['pdf'])) {
                            $file_type_class = 'file-pdf';
                        } elseif (in_array(strtolower($file->extension), ['doc', 'docx'])) {
                            $file_type_class = 'file-doc';
                        } elseif (in_array(strtolower($file->extension), ['xls', 'xlsx'])) {
                            $file_type_class = 'file-excel';
                        } elseif (in_array(strtolower($file->extension), ['zip', 'rar', '7z'])) {
                            $file_type_class = 'file-archive';
                        } else {
                            $file_type_class = 'file-other';
                        }
                        
                        $expired_class = ($file->expired) ? 'file-expired' : '';
                        ?>
                        <div class="file-card <?php echo $file_type_class . ' ' . $expired_class; ?>" data-file-id="<?php echo $file->id; ?>">
                            <!-- Card Header -->
                            <div class="card-header">
                                <div class="card-checkbox">
                                    <?php if (!$file->expired) { ?>
                                        <input type="checkbox" name="files[]" value="<?php echo $file->id; ?>" class="batch_checkbox" id="file_<?php echo $file->id; ?>">
                                        <label for="file_<?php echo $file->id; ?>"></label>
                                    <?php } ?>
                                </div>
                                <div class="file-type-badge">
                                    <span class="badge badge-<?php echo strtolower($file->extension); ?>">
                                        <?php echo strtoupper($file->extension); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Card Image/Preview -->
                            <div class="card-preview">
                                <?php if ($file->isImage() && !$file->expired) { ?>
                                    <?php 
                                    $thumbnail = make_thumbnail($file->full_path, null, TEMPLATE_THUMBNAILS_WIDTH, TEMPLATE_THUMBNAILS_HEIGHT);
                                    if (!empty($thumbnail['thumbnail']['url'])) { ?>
                                        <div class="image-preview">
                                            <img src="<?php echo $thumbnail['thumbnail']['url']; ?>" 
                                                 alt="<?php echo htmlspecialchars($file->title); ?>"
                                                 class="card-image"
                                                 loading="lazy">
                                            <div class="image-overlay">
                                                <button class="btn-preview" data-url="<?php echo BASE_URI; ?>process.php?do=get_preview&file_id=<?php echo $file->id; ?>">
                                                    <i class="fa fa-search-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php } ?>
                                <?php } else { ?>
                                    <div class="file-icon">
                                        <i class="fa fa-file-<?php echo $file->isImage() ? 'image' : 'o'; ?>"></i>
                                    </div>
                                <?php } ?>
                            </div>

                            <!-- Card Content -->
                            <div class="card-content">
                                <h3 class="file-title" title="<?php echo htmlspecialchars($file->title); ?>">
                                    <?php echo htmlspecialchars($file->title); ?>
                                </h3>
                                
                                <?php if ($file->title != $file->filename_original) { ?>
                                    <p class="file-original-name">
                                        <?php echo htmlspecialchars($file->filename_original); ?>
                                    </p>
                                <?php } ?>
                                
                                <?php if (!empty($file->description)) { ?>
                                    <p class="file-description" title="<?php echo htmlspecialchars($file->description); ?>">
                                        <?php echo format_description($file->description); ?>
                                    </p>
                                <?php } ?>

                                <div class="file-meta">
                                    <div class="meta-item">
                                        <i class="fa fa-calendar"></i>
                                        <span><?php echo format_date($file->uploaded_date); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fa fa-hdd-o"></i>
                                        <span><?php echo $file->size_formatted; ?></span>
                                    </div>
                                    <?php if ($file->isImage()) {
                                        $dimensions = $file->getDimensions();
                                        if (!empty($dimensions['width'])) { ?>
                                            <div class="meta-item">
                                                <i class="fa fa-expand"></i>
                                                <span><?php echo $dimensions['width']; ?>×<?php echo $dimensions['height']; ?>px</span>
                                            </div>
                                        <?php }
                                    } ?>
                                </div>

                                <!-- Expiration Info -->
                                <?php if ($file->expires == '1') { ?>
                                    <div class="expiration-info <?php echo $file->expired ? 'expired' : 'expires-soon'; ?>">
                                        <i class="fa fa-clock-o"></i>
                                        <?php if ($file->expired) { ?>
                                            <span class="expired-text"><?php echo __('Expired', 'modern_template'); ?></span>
                                        <?php } else { ?>
                                            <span><?php echo __('Expires', 'modern_template'); ?>: <?php echo date(get_option('timeformat'), strtotime($file->expiry_date)); ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>

                            <!-- Card Actions -->
                            <div class="card-actions">
                                <?php if ($file->expired) { ?>
                                    <button class="btn btn-modern btn-disabled" disabled>
                                        <i class="fa fa-exclamation-triangle"></i>
                                        <?php echo __('File Expired', 'modern_template'); ?>
                                    </button>
                                <?php } else { ?>
                                    <a href="<?php echo $file->download_link; ?>" 
                                       class="btn btn-modern btn-primary btn-download" 
                                       target="_blank"
                                       data-file-id="<?php echo $file->id; ?>">
                                        <i class="fa fa-download"></i>
                                        <?php echo __('Download', 'modern_template'); ?>
                                    </a>
                                    
                                    <a href="<?php echo $file->public_url; ?>" 
                                       class="btn btn-modern btn-secondary" 
                                       target="_blank"
                                       title="<?php echo __('Direct link', 'modern_template'); ?>">
                                        <i class="fa fa-link"></i>
                                        <?php echo __('Link', 'modern_template'); ?>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                
                <!-- Pagination -->
                <div class="modern-pagination-wrapper">
                    <?php
                    if ($files['pagination']['total'] > $per_page) {
                        $pagination_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $per_page = get_option('pagination_results_per_page');
                        
                        $pagination = new \ProjectSend\Classes\Layout\Pagination;
                        echo $pagination->make([
                            'link' => 'public.php',
                            'current' => $pagination_page,
                            'item_count' => $files['pagination']['total'],
                            'items_per_page' => $per_page,
                        ]);
                    }
                    ?>
                </div>
                
                <?php
            } else {
                ?>
                <div class="no-files-message">
                    <div class="no-files-icon">
                        <i class="fa fa-folder-open"></i>
                    </div>
                    <h3><?php echo __('No public files available', 'modern_template'); ?></h3>
                    <p><?php echo __('There are currently no public files to display.', 'modern_template'); ?></p>
                    <?php if ($is_logged_in && current_user_can_upload()) { ?>
                        <a href="<?php echo BASE_URI; ?>upload.php" class="btn btn-modern btn-primary">
                            <i class="fa fa-cloud-upload"></i>
                            <?php echo __('Upload Files', 'modern_template'); ?>
                        </a>
                    <?php } ?>
                </div>
                <?php
            }
            ?>
        </div>
    </form>
    
    <!-- Footer within main content -->
    <div class="modern-footer">
        <?php render_footer_text(); ?>
    </div>
    
    </div> <!-- End modern-main-content -->
</div> <!-- End modern-template-wrapper -->

<!-- Modern Template JavaScript -->
<script src="<?php echo $this_template_url; ?>js/template.js"></script>

<?php render_custom_assets('body_bottom'); ?>

</body>
</html>