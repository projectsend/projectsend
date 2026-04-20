<?php
/*
Template name: Modern Cards
URI: https://www.projectsend.org/templates/modern
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: A modern, responsive card-based template with advanced pagination and contemporary design
*/
$ld = 'modern_template'; // specify the language domain for this template

define('TEMPLATE_RESULTS_PER_PAGE', get_option('pagination_results_per_page'));
define('TEMPLATE_THUMBNAILS_WIDTH', '280');
define('TEMPLATE_THUMBNAILS_HEIGHT', '200');

$filter_by_category = isset($_GET['category']) ? $_GET['category'] : null;

$current_url = get_form_action_with_existing_parameters('index.php');

include_once ROOT_DIR . '/templates/common.php'; // include the required functions for every template

$window_title = __('File downloads', 'modern_template');

$page_id = 'modern_template';

$body_class = array('template', 'modern-template', 'hide_title');

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

// Header buttons
if (current_user_can_upload()) {
    $header_action_buttons = [
        [
            'url' => BASE_URI.'upload.php',
            'label' => __('Upload file', 'cftp_admin'),
        ],
    ];
}

// Search + filters bar data
$search_form_action = 'index.php';
$filters_form = [
    'action' => '',
    'items' => [],
];

if (!empty($cat_ids)) {
    $selected_parent = (isset($_GET['category'])) ? [$_GET['category']] : [];
    $category_filter = [];
    $generate_categories_options = generate_categories_options($get_categories['arranged'], 0, $selected_parent, 'include', $cat_ids);
    $format_categories_options = format_categories_options($generate_categories_options);
    foreach ($format_categories_options as $key => $category) {
        $category_filter[$category['id']] = $category['label'];
    }
    $filters_form['items']['category'] = [
        'current' => (isset($_GET['category'])) ? $_GET['category'] : null,
        'placeholder' => [
            'value' => '0',
            'label' => __('All categories', 'cftp_admin')
        ],
        'options' => $category_filter,
    ];
}

// Results count and form actions 
$elements_found_count = (isset($count_for_pagination)) ? $count_for_pagination : 0;
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
    <title><?php echo html_output( $client_info['name'].' | '.$window_title . ' &raquo; ' . SYSTEM_NAME ); ?></title>
    <?php meta_favicon(); ?>

    <link rel="stylesheet" href="<?php echo $this_template_url; ?>font-awesome-4.6.3/css/font-awesome.min.css">
    <link rel="stylesheet" media="all" type="text/css" href="<?php echo $this_template_url; ?>main.css" />

    <script src="<?php echo $this_template_url; ?>js/jquery.1.11.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/js-cookie@rc/dist/js.cookie.min.js"></script>
    
    <script>
        window.base_url = '<?php echo BASE_URI; ?>';
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
            </ul>
        </nav>
        
        <!-- Profile Section -->
        <div class="sidebar-profile">
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
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="modern-main-content">
        <!-- Header Section -->
        <div class="modern-header">
            <div class="header-content">
                <h1 class="page-title">
                    <?php echo __('Available Files', 'modern_template'); ?>
                    <?php if ($elements_found_count > 0) { ?>
                        <span class="files-count-badge"><?php echo $elements_found_count; ?></span>
                    <?php } ?>
                </h1>
            </div>
        </div>

    <!-- Search and Filters -->
    <div class="modern-controls">
        <?php include_once LAYOUT_DIR . DS . 'search-filters-bar.php'; ?>
        
        <!-- Breadcrumbs -->
        <?php include_once LAYOUT_DIR . DS . 'breadcrumbs.php'; ?>
        
        <!-- Modern Folders Navigation -->
        <div id="modern_folders_nav" class="modern-folders-nav">
            <?php
                if (!empty($_GET['folder_id'])) {
                    $root_link = modify_url_with_parameters($current_url, [], ['folder_id']);
            ?>
                    <div class="folder-breadcrumb">
                        <a href="<?php echo $root_link; ?>" class="folder-nav-item folder-root">
                            <i class="fa fa-home" aria-hidden="true"></i>
                            <span><?php _e('Root','cftp_admin'); ?></span>
                        </a>
                        
                        <?php
                            $get_parent = new \ProjectSend\Classes\Folder($_GET['folder_id']);
                            $parent_data = $get_parent->getData();
                            $up_link = modify_url_with_parameters($current_url, ['folder_id' => $parent_data['parent']], ['folder_id']);
                        ?>
                        <a href="<?php echo $up_link; ?>" class="folder-nav-item folder-up">
                            <i class="fa fa-arrow-up" aria-hidden="true"></i>
                            <span><?php _e('Parent folder','cftp_admin'); ?></span>
                        </a>
                    </div>
            <?php
                }

                // Modern Folders Grid
                if (!empty($folders)) {
            ?>
                    <div class="folders-grid">
                        <div class="folders-container">
                            <?php
                                foreach ($folders as $folder) {
                                    $folder = new \ProjectSend\Classes\Folder($folder['id']);
                                    $folder_data = $folder->getData();
                                    $link = modify_url_with_parameters($current_url, ['folder_id' => $folder_data['id']], ['folder_id']);
                            ?>
                                    <div class="modern-folder-card" 
                                        data-folder-id="<?php echo $folder_data['id']; ?>"
                                        data-name="<?php echo $folder_data['name']; ?>">
                                        <a href="<?php echo $link; ?>" class="folder-link">
                                            <div class="folder-icon">
                                                <i class="fa fa-folder" aria-hidden="true"></i>
                                            </div>
                                            <span class="folder-name"><?php echo htmlspecialchars($folder->name); ?></span>
                                        </a>
                                    </div>
                            <?php
                                }
                            ?>
                        </div>
                    </div>
            <?php
                }
            ?>
        
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
                    foreach ($available_files as $file_id) {
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
                                    
                                    <?php if ($file->embeddable && !$file->isImage()) { ?>
                                        <button class="btn btn-modern btn-secondary btn-preview" 
                                                data-url="<?php echo BASE_URI; ?>process.php?do=get_preview&file_id=<?php echo $file->id; ?>">
                                            <i class="fa fa-eye"></i>
                                            <?php echo __('Preview', 'modern_template'); ?>
                                        </button>
                                    <?php } ?>
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
                    if (isset($count_for_pagination) && $count_for_pagination > 0) {
                        $pagination = new \ProjectSend\Classes\Layout\Pagination;
                        echo $pagination->make([
                            'link' => 'my_files/index.php',
                            'current' => $pagination_page,
                            'item_count' => $count_for_pagination,
                            'items_per_page' => TEMPLATE_RESULTS_PER_PAGE,
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
                    <h3><?php echo __('No files available', 'modern_template'); ?></h3>
                    <p><?php echo __('There are currently no files to display.', 'modern_template'); ?></p>
                    <?php if (current_user_can_upload()) { ?>
                        <a href="<?php echo BASE_URI; ?>upload.php" class="btn btn-modern btn-primary">
                            <i class="fa fa-cloud-upload"></i>
                            <?php echo __('Upload Your First File', 'modern_template'); ?>
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

<!-- Preview Modal -->
<div id="previewModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <button class="modal-close" id="closeModal">
            <i class="fa fa-times"></i>
        </button>
        <div class="modal-body" id="previewContent">
            <!-- Preview content will be loaded here -->
        </div>
    </div>
</div>

<!-- Modern Template JavaScript -->
<script src="<?php echo $this_template_url; ?>js/template.js"></script>

<?php render_custom_assets('body_bottom'); ?>

</body>
</html>