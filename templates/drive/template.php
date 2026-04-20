<?php
/*
Template name: Google Drive
URI: https://www.projectsend.org/templates/drive
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: A clean, modern template inspired by Google Drive's interface design. Features a minimalist layout with Google's Material Design principles. Developed with assistance from Claude AI.
*/
$ld = 'drive_template'; // specify the language domain for this template

/** Template configuration */
define('TEMPLATE_RESULTS_PER_PAGE', 15);
define('TEMPLATE_USE_PREVIEW_THUMBNAILS', true);
define('TEMPLATE_THUMBNAILS_WIDTH', '120');
define('TEMPLATE_THUMBNAILS_HEIGHT', '120');

$filter_by_category = (isset($_GET['category']) && $_GET['category'] !== '') ? $_GET['category'] : null;

$current_url = get_form_action_with_existing_parameters('index.php');

/** Handle error messages */
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'search':
            $flash->error(__('Your search keywords returned no results.', 'drive_template'));
            break;
        case 'filter':
            $flash->error(__('The filters you selected returned no results.', 'drive_template'));
            break;
        default:
            $flash->error(__('There was an error processing your request.', 'drive_template'));
            break;
    }
}

/** Check if there are no files at all */
if (isset($no_results_error)) {
    switch ($no_results_error) {
        case 'search':
            $flash->error(__('Your search keywords returned no results.', 'drive_template'));
            break;
        case 'filter':
            $flash->error(__('The filters you selected returned no results.', 'drive_template'));
            break;
        default:
            $flash->error(__('There are no files available.', 'drive_template'));
            break;
    }
}

/** Include common functions */
include_once ROOT_DIR . '/templates/common.php';

/** Page title */
$window_title = __('My Files', 'drive_template');

include_once 'lang/' . LOADED_LANG . '.mo.php';
?>

<!DOCTYPE html>
<html lang="<?php echo SITE_LANG; ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $window_title; ?> - <?php echo get_option('this_install_title'); ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Roboto', 'sans-serif'],
                    },
                    colors: {
                        google: {
                            green: '#1e8e3e',
                            'green-dark': '#188038',
                            'green-50': '#e6f4ea',
                            'green-900': '#0d652d',
                            gray: {
                                50: '#f8f9fa',
                                100: '#f1f3f4',
                                200: '#e8eaed',
                                300: '#dadce0',
                                400: '#bdc1c6',
                                500: '#9aa0a6',
                                600: '#80868b',
                                700: '#5f6368',
                                800: '#3c4043',
                                900: '#202124'
                            }
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $this_template_url; ?>css/drive.css">
    
    <script>
        window.base_url = '<?php echo BASE_URI; ?>';
        
        // Theme management with fallback for localStorage issues
        function initTheme() {
            try {
                var storedTheme = localStorage.getItem('theme');
                
                if (storedTheme === 'dark' || (storedTheme === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {
                // Fallback to system preference if localStorage fails
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        }
        
        function toggleTheme() {
            var isDark = document.documentElement.classList.contains('dark');
            
            if (isDark) {
                document.documentElement.classList.remove('dark');
                try {
                    localStorage.setItem('theme', 'light');
                } catch (e) {
                    // localStorage might not be available (e.g., private browsing)
                }
            } else {
                document.documentElement.classList.add('dark');
                try {
                    localStorage.setItem('theme', 'dark');
                } catch (e) {
                    // localStorage might not be available (e.g., private browsing)
                }
            }
        }
        
        // Initialize theme immediately
        initTheme();
    </script>

    <?php render_custom_assets('head'); ?>
</head>

<body class="h-full bg-white dark:bg-google-gray-900 font-sans">
    <?php render_custom_assets('body_top'); ?>

    <!-- Google Drive Header -->
    <header class="bg-white dark:bg-google-gray-900 border-b border-google-gray-200 dark:border-google-gray-700 h-16">
        <div class="flex items-center h-full px-6">
            <!-- Left Side: Logo, Title and Search -->
            <div class="flex items-center flex-1">
                <!-- Menu button (mobile) -->
                <button class="p-3 -ml-3 rounded-full hover:bg-google-gray-100 dark:hover:bg-google-gray-700 mr-1 md:hidden">
                    <span class="material-icons text-google-gray-600 dark:text-google-gray-400">menu</span>
                </button>
                
                <!-- Logo -->
                <div class="flex items-center mr-8">
                    <?php if ($logo_file_info): ?>
                        <img src="<?php echo $logo_file_info['url']; ?>" alt="<?php echo get_option('this_install_title'); ?>" class="h-10 w-auto">
                    <?php else: ?>
                        <span class="material-icons text-google-green text-3xl">folder_shared</span>
                    <?php endif; ?>
                </div>

                <!-- Search Bar -->
                <div class="flex-1 max-w-2xl">
                    <form action="<?php echo BASE_URI; ?>my_files/index.php" method="get" class="relative">
                        <?php if (isset($_GET['folder_id'])): ?>
                            <input type="hidden" name="folder_id" value="<?php echo htmlspecialchars($_GET['folder_id']); ?>">
                        <?php endif; ?>
                        
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="material-icons text-google-gray-400">search</span>
                            </div>
                            <input type="text" 
                                   name="search" 
                                   value="<?php echo isset($_GET['search']) ? html_output($_GET['search']) : ''; ?>"
                                   placeholder="<?php _e('Search files', 'drive_template'); ?>"
                                   class="w-full pl-12 pr-10 py-3 bg-google-gray-100 dark:bg-google-gray-800 rounded-full border-0 text-google-gray-900 dark:text-white placeholder-google-gray-500 focus:outline-none focus:ring-2 focus:ring-google-green focus:bg-white dark:focus:bg-google-gray-700">
                            
                            <?php if (!empty($_GET['search'])): ?>
                            <button type="button" onclick="this.form.search.value=''; this.form.submit();" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <span class="material-icons text-google-gray-400 hover:text-google-gray-600">close</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Side: Actions and User -->
            <div class="flex items-center space-x-2">
                <!-- Navigation Icons -->
                <a href="<?php echo BASE_URI; ?>manage-files.php" 
                   class="p-3 rounded-full hover:bg-google-gray-100 dark:hover:bg-google-gray-700 transition-colors"
                   title="<?php _e('Manage Files', 'drive_template'); ?>">
                    <span class="material-icons text-google-gray-600 dark:text-google-gray-400">dashboard</span>
                </a>
                
                <?php if (current_user_can_upload()): ?>
                <a href="<?php echo BASE_URI; ?>upload.php" 
                   class="p-3 rounded-full hover:bg-google-gray-100 dark:hover:bg-google-gray-700 transition-colors"
                   title="<?php _e('Upload Files', 'drive_template'); ?>">
                    <span class="material-icons text-google-gray-600 dark:text-google-gray-400">cloud_upload</span>
                </a>
                <?php endif; ?>

                <!-- Theme Toggle -->
                <button onclick="toggleTheme()" 
                        class="p-3 rounded-full hover:bg-google-gray-100 dark:hover:bg-google-gray-700 transition-colors"
                        title="Toggle theme">
                    <span class="material-icons text-google-gray-600 dark:text-google-gray-400 dark:hidden">light_mode</span>
                    <span class="material-icons text-google-gray-600 dark:text-google-gray-400 hidden dark:inline">dark_mode</span>
                </button>

                <!-- User Avatar -->
                <div class="relative group">
                    <button class="w-8 h-8 bg-google-green rounded-full flex items-center justify-center text-white text-sm font-medium hover:shadow-md transition-shadow">
                        <?php echo strtoupper(substr($client_info['name'], 0, 1)); ?>
                    </button>
                    
                    <!-- User Dropdown -->
                    <div class="absolute right-0 mt-2 w-80 bg-white dark:bg-google-gray-800 rounded-lg shadow-lg border border-google-gray-200 dark:border-google-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <div class="p-6 text-center border-b border-google-gray-200 dark:border-google-gray-700">
                            <div class="w-20 h-20 bg-google-green rounded-full flex items-center justify-center text-white text-2xl font-medium mx-auto mb-4">
                                <?php echo strtoupper(substr($client_info['name'], 0, 1)); ?>
                            </div>
                            <p class="font-medium text-google-gray-900 dark:text-google-gray-100"><?php echo html_output($client_info['name']); ?></p>
                            <p class="text-sm text-google-gray-500 dark:text-google-gray-400"><?php echo html_output($client_info['username']); ?></p>
                        </div>
                        <div class="py-2">
                            <a href="<?php echo client_get_profile_link(); ?>" 
                               class="flex items-center px-4 py-3 text-sm text-google-gray-700 dark:text-google-gray-200 hover:bg-google-gray-100 dark:hover:bg-google-gray-700">
                                <span class="material-icons text-google-gray-500 mr-3">account_circle</span>
                                <?php _e('Edit Profile', 'drive_template'); ?>
                            </a>
                            <a href="<?php echo BASE_URI; ?>process.php?do=logout" 
                               class="flex items-center px-4 py-3 text-sm text-google-gray-700 dark:text-google-gray-200 hover:bg-google-gray-100 dark:hover:bg-google-gray-700">
                                <span class="material-icons text-google-gray-500 mr-3">logout</span>
                                <?php _e('Sign Out', 'drive_template'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb Navigation -->
    <?php if (!empty($_GET['folder_id'])): ?>
    <div class="bg-google-gray-50 dark:bg-google-gray-800 border-b border-google-gray-200 dark:border-google-gray-700">
        <div class="px-6 py-3">
            <div class="flex items-center space-x-2 text-sm">
                <?php 
                // Build full breadcrumb path
                $breadcrumbs = [];
                $current_folder_id = !empty($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
                
                if ($current_folder_id) {
                    $temp_folder_id = $current_folder_id;
                    while ($temp_folder_id) {
                        $temp_folder = new \ProjectSend\Classes\Folder($temp_folder_id);
                        $temp_data = $temp_folder->getData();
                        if ($temp_data) {
                            array_unshift($breadcrumbs, [
                                'id' => $temp_data['id'],
                                'name' => $temp_data['name'],
                                'parent' => $temp_data['parent']
                            ]);
                            $temp_folder_id = $temp_data['parent'];
                        } else {
                            break;
                        }
                    }
                }
                
                $root_link = modify_url_with_parameters($current_url, [], ['folder_id']); ?>
                <a href="<?php echo $root_link; ?>" class="flex items-center text-google-green hover:text-google-green-dark">
                    <span class="material-icons text-base mr-1">folder</span>
                    <?php _e('My Files', 'drive_template'); ?>
                </a>
                
                <?php if (!empty($breadcrumbs)): ?>
                    <?php foreach ($breadcrumbs as $index => $breadcrumb): 
                        $is_last = ($index === count($breadcrumbs) - 1);
                        $breadcrumb_link = modify_url_with_parameters($current_url, ['folder_id' => $breadcrumb['id']], ['folder_id']); ?>
                        <span class="material-icons text-google-gray-400 text-sm">chevron_right</span>
                        <?php if ($is_last): ?>
                            <span class="flex items-center text-google-gray-700 dark:text-google-gray-300">
                                <span class="material-icons text-base mr-1">folder</span>
                                <?php echo html_output($breadcrumb['name']); ?>
                            </span>
                        <?php else: ?>
                            <a href="<?php echo $breadcrumb_link; ?>" class="flex items-center text-google-green hover:text-google-green-dark">
                                <span class="material-icons text-base mr-1">folder</span>
                                <?php echo html_output($breadcrumb['name']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="flex-1 bg-white dark:bg-google-gray-900">
        
        <?php 
        // Define folder navigation variables early
        $current_folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
        ?>
        
        <!-- Flash Messages -->
        <?php if ($flash->hasMessages()): ?>
        <div class="px-6 py-4">
            <?php echo $flash->display(); ?>
        </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-google-gray-200 dark:border-google-gray-700">
            <div class="flex items-center space-x-4">
                <!-- View Toggle -->
                <div class="flex items-center bg-google-gray-100 dark:bg-google-gray-800 rounded-lg p-1">
                    <button id="view-list" class="view-toggle p-2 rounded bg-white dark:bg-google-gray-700 shadow-sm" data-view="list">
                        <span class="material-icons text-google-gray-600 dark:text-google-gray-400">view_list</span>
                    </button>
                    <button id="view-grid" class="view-toggle p-2 rounded opacity-50" data-view="grid">
                        <span class="material-icons text-google-gray-400">grid_view</span>
                    </button>
                </div>
            </div>

            <div class="flex items-center space-x-4">
                <!-- Category Filter -->
                <?php if (!empty($get_categories['categories'])): ?>
                <form action="<?php echo BASE_URI; ?>my_files/index.php" method="get" class="flex items-center">
                    <?php if (isset($_GET['folder_id'])): ?>
                        <input type="hidden" name="folder_id" value="<?php echo htmlspecialchars($_GET['folder_id']); ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['search'])): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
                    <?php endif; ?>
                    
                    <select name="category" onchange="this.form.submit()" 
                            class="px-3 py-2 border border-google-gray-300 dark:border-google-gray-600 rounded-lg bg-white dark:bg-google-gray-700 text-google-gray-900 dark:text-white text-sm focus:outline-none focus:ring-1 focus:ring-google-green">
                        <option value=""><?php _e('All Types', 'drive_template'); ?></option>
                        <?php foreach ($get_categories['categories'] as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo ($filter_by_category == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo html_output($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>

                <!-- Sort -->
                <div class="text-sm text-google-gray-500 dark:text-google-gray-400">
                    <?php echo sprintf(__('%d files', 'drive_template'), $count_for_pagination ?? 0); ?>
                </div>
            </div>
        </div>

        <!-- Folder Breadcrumbs -->
        <?php if ($current_folder_id): 
            $folders_obj = new \ProjectSend\Classes\Folders;
            $breadcrumbs = $folders_obj->makeFolderBreadcrumbs($current_folder_id, $current_url);
            if (!empty($breadcrumbs)): ?>
        <div class="px-6 py-3 border-b border-google-gray-200 dark:border-google-gray-700 bg-google-gray-50 dark:bg-google-gray-800">
            <nav class="flex items-center text-sm">
                <?php foreach ($breadcrumbs as $index => $nav_item): ?>
                    <?php if ($index > 0): ?>
                        <span class="mx-2 text-google-gray-400">/</span>
                    <?php endif; ?>
                    
                    <?php if (!empty($nav_item['url'])): ?>
                        <a href="<?php echo $nav_item['url']; ?>" class="text-google-green hover:text-google-green-dark transition-colors">
                            <?php echo html_output($nav_item['name']); ?>
                        </a>
                    <?php else: ?>
                        <span class="text-google-gray-700 dark:text-google-gray-300 font-medium">
                            <?php echo html_output($nav_item['name']); ?>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php endif; endif; ?>

        <!-- Bulk Actions Toolbar (hidden by default) -->
        <div id="bulk-actions" class="hidden bg-google-green text-white px-6 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <span id="selection-count" class="text-sm font-medium"></span>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="download-zip-btn" class="bg-white text-google-green px-4 py-2 rounded-lg text-sm font-medium hover:bg-google-gray-100 transition-colors">
                        <span class="material-icons text-sm mr-1">download</span>
                        <?php _e('Download as ZIP', 'drive_template'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Folders Section -->
        <?php 
        // Check if we're inside a folder (already defined above)
        $show_navigation = false;
        $parent_folder_id = null;
        
        if ($current_folder_id) {
            $show_navigation = true;
            $current_folder_obj = new \ProjectSend\Classes\Folder($current_folder_id);
            if ($current_folder_obj->id) {
                $parent_folder_id = $current_folder_obj->parent;
            }
        }
        
        // Show folders if we have any or if we need navigation
        if (!empty($folders) || $show_navigation): ?>
        <div class="px-6 py-4">
            <h3 class="text-sm font-medium text-google-gray-700 dark:text-google-gray-300 mb-3 flex items-center">
                <span class="material-icons text-base mr-2">folder</span>
                <?php _e('Folders', 'drive_template'); ?>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                <?php 
                // Add navigation folders when inside a folder
                if ($show_navigation): 
                    // Root folder link (removes folder_id param)
                    $root_link = modify_url_with_parameters($current_url, [], ['folder_id']);
                ?>
                    <a href="<?php echo $root_link; ?>" class="flex items-center p-3 rounded-lg bg-google-gray-50 dark:bg-google-gray-800 hover:bg-google-gray-100 dark:hover:bg-google-gray-700 transition-colors group">
                        <span class="material-icons text-2xl text-google-gray-500 mr-3">home</span>
                        <span class="text-sm text-google-gray-700 dark:text-google-gray-300 truncate"><?php _e('Root', 'drive_template'); ?></span>
                    </a>
                    
                    <?php 
                    // Parent folder link if not at root level
                    if ($parent_folder_id !== null): 
                        if ($parent_folder_id == 0) {
                            // Parent is root
                            $parent_link = modify_url_with_parameters($current_url, [], ['folder_id']);
                        } else {
                            // Parent is another folder
                            $parent_link = modify_url_with_parameters($current_url, ['folder_id' => $parent_folder_id], ['folder_id']);
                        }
                    ?>
                        <a href="<?php echo $parent_link; ?>" class="flex items-center p-3 rounded-lg bg-google-gray-50 dark:bg-google-gray-800 hover:bg-google-gray-100 dark:hover:bg-google-gray-700 transition-colors group">
                            <span class="material-icons text-2xl text-google-gray-500 mr-3">arrow_upward</span>
                            <span class="text-sm text-google-gray-700 dark:text-google-gray-300 truncate"><?php _e('Up one level', 'drive_template'); ?></span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php 
                // Regular folders
                foreach ($folders as $folder_data): 
                    $folder = new \ProjectSend\Classes\Folder($folder_data['id']);
                    $folder_data = $folder->getData();
                    $link = modify_url_with_parameters($current_url, ['folder_id' => $folder_data['id']], ['folder_id']); ?>
                    <a href="<?php echo $link; ?>" class="flex items-center p-3 rounded-lg bg-google-gray-50 dark:bg-google-gray-800 hover:bg-google-gray-100 dark:hover:bg-google-gray-700 transition-colors group">
                        <span class="material-icons text-2xl text-google-green mr-3">folder</span>
                        <span class="text-sm text-google-gray-700 dark:text-google-gray-300 truncate"><?php echo html_output($folder->name); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="border-b border-google-gray-200 dark:border-google-gray-700 mt-4"></div>
        </div>
        <?php endif; ?>

        <!-- Files Container -->
        <div id="files-container" class="list-view">
            
        <?php if ($count > 0): ?>
        <!-- List View Header -->
        <div class="list-header px-6 py-3 border-b border-google-gray-200 dark:border-google-gray-700 bg-google-gray-50 dark:bg-google-gray-800">
            <div class="flex items-center">
                <div class="w-8 flex items-center justify-center">
                    <input type="checkbox" id="select_all" class="w-4 h-4 rounded border-google-gray-300 dark:border-google-gray-600 text-google-green focus:ring-google-green focus:ring-2" />
                </div>
                <div class="flex-1 px-4">
                    <span class="text-xs font-medium text-google-gray-600 dark:text-google-gray-400 uppercase tracking-wide">Name</span>
                </div>
                <div class="w-24 px-2 text-xs font-medium text-google-gray-600 dark:text-google-gray-400 uppercase tracking-wide hidden md:block">
                    Owner
                </div>
                <div class="w-32 px-2 text-xs font-medium text-google-gray-600 dark:text-google-gray-400 uppercase tracking-wide hidden lg:block">
                    Last modified
                </div>
                <div class="w-20 px-2 text-xs font-medium text-google-gray-600 dark:text-google-gray-400 uppercase tracking-wide hidden sm:block">
                    Size
                </div>
                <div class="w-20"></div>
            </div>
        </div>

        <!-- Files -->
        <div class="files-wrapper divide-y divide-google-gray-200 dark:divide-google-gray-700">
            <?php foreach ($available_files as $file_id): 
                $file = new \ProjectSend\Classes\Files($file_id);
                
                if (!$file->id) continue;
                
                // Image detection using Files class method
                ?>
                
                <div class="flex items-center px-6 py-3 hover:bg-google-gray-50 dark:hover:bg-google-gray-800 file-row cursor-pointer group transition-colors" 
                     data-file-id="<?php echo $file->id; ?>" 
                     data-expired="<?php echo $file->expired ? 'true' : 'false'; ?>">
                    
                    <!-- Selection Checkbox -->
                    <div class="w-8 flex items-center justify-center">
                        <?php if (!$file->expired): ?>
                        <input type="checkbox" 
                               name="files[]" 
                               value="<?php echo $file->id; ?>" 
                               class="file-checkbox w-4 h-4 rounded border-google-gray-300 dark:border-google-gray-600 text-google-green focus:ring-google-green focus:ring-2" 
                               data-file-id="<?php echo $file->id; ?>">
                        <!-- Selection indicator (green circle when selected) -->
                        <div class="selection-indicator hidden absolute w-4 h-4 bg-google-green rounded-full flex items-center justify-center">
                            <span class="material-icons text-white text-xs">check</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- File Icon and Name -->
                    <div class="flex-1 flex items-center px-4 min-w-0">
                        <div class="flex-shrink-0 mr-3">
                            <?php if ($file->isImage() && !$file->expired && !empty($file->full_path)): ?>
                                <?php 
                                $thumbnail_src = make_thumbnail($file->full_path, null, 24, 24);
                                if ($thumbnail_src && isset($thumbnail_src['thumbnail']['url'])): ?>
                                    <img src="<?php echo $thumbnail_src['thumbnail']['url']; ?>" alt="" class="w-6 h-6 rounded object-cover">
                                <?php else: ?>
                                    <span class="material-icons text-xl text-google-gray-600 dark:text-google-gray-400"><?php echo get_material_file_icon($file->extension); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="material-icons text-xl text-google-gray-600 dark:text-google-gray-400"><?php echo get_material_file_icon($file->extension); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-google-gray-900 dark:text-google-gray-100 truncate">
                                <?php echo html_output($file->title); ?>
                            </p>
                            <?php if ($file->expired): ?>
                            <p class="text-xs text-red-500 dark:text-red-400">
                                <?php _e('Expired', 'drive_template'); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Owner -->
                    <div class="w-24 px-2 text-sm text-google-gray-500 dark:text-google-gray-400 hidden md:block">
                        me
                    </div>

                    <!-- Last Modified -->
                    <div class="w-32 px-2 text-sm text-google-gray-500 dark:text-google-gray-400 hidden lg:block">
                        <?php echo format_date($file->uploaded_date); ?>
                    </div>

                    <!-- File Size -->
                    <div class="w-20 px-2 text-sm text-google-gray-500 dark:text-google-gray-400 hidden sm:block">
                        <?php echo $file->size_formatted; ?>
                    </div>

                    <!-- Actions -->
                    <div class="w-20 flex items-center justify-center space-x-1">
                        <button type="button" 
                               class="file-info-btn p-2 rounded-full hover:bg-google-gray-200 dark:hover:bg-google-gray-700 opacity-0 group-hover:opacity-100 transition-opacity"
                               data-file-id="<?php echo $file->id; ?>"
                               title="<?php _e('File info', 'drive_template'); ?>">
                            <span class="material-icons text-base text-google-gray-600 dark:text-google-gray-400">info</span>
                        </button>
                        <?php if (!$file->expired): ?>
                        <a href="<?php echo $file->download_link; ?>" 
                           class="p-2 rounded-full hover:bg-google-gray-200 dark:hover:bg-google-gray-700 opacity-0 group-hover:opacity-100 transition-opacity"
                           title="<?php _e('Download', 'drive_template'); ?>">
                            <span class="material-icons text-base text-google-gray-600 dark:text-google-gray-400">download</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        </div> <!-- End files-container -->

        <!-- ZIP Download FAB -->
        <button type="button" class="hidden fixed bottom-6 right-6 w-14 h-14 bg-google-green hover:bg-google-green-dark text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 z-50 flex items-center justify-center group" id="zip-download-btn">
            <span class="material-icons">download</span>
            <div class="absolute bottom-16 right-0 bg-google-gray-800 text-white text-sm px-3 py-2 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap">
                <span class="zip-text"><?php _e('Download as ZIP', 'drive_template'); ?></span>
            </div>
        </button>

        <?php else: ?>
        <!-- Empty State -->
        <div class="flex flex-col items-center justify-center py-16 px-6">
            <span class="material-icons text-6xl text-google-gray-300 dark:text-google-gray-600 mb-4">folder_open</span>
            <h3 class="text-xl font-medium text-google-gray-700 dark:text-google-gray-300 mb-2">
                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                    <?php _e('No files match your search', 'drive_template'); ?>
                <?php else: ?>
                    <?php _e('This folder is empty', 'drive_template'); ?>
                <?php endif; ?>
            </h3>
            <p class="text-google-gray-500 dark:text-google-gray-400 text-center">
                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                    <?php _e('Try different keywords or check your spelling.', 'drive_template'); ?>
                <?php else: ?>
                    <?php _e('Files shared with you will appear here.', 'drive_template'); ?>
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if (isset($count_for_pagination) && $count_for_pagination > 0 && TEMPLATE_RESULTS_PER_PAGE > 0): ?>
        <div class="mt-8">
            <div class="pagination-wrapper">
                <?php
                $pagination = new \ProjectSend\Classes\Layout\Pagination;
                echo $pagination->make([
                    'link' => 'my_files/index.php',
                    'current' => $pagination_page,
                    'item_count' => $count_for_pagination,
                    'items_per_page' => TEMPLATE_RESULTS_PER_PAGE,
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="px-6 py-8 mt-12 border-t border-google-gray-200 dark:border-google-gray-700">
            <div class="text-center text-sm text-google-gray-500 dark:text-google-gray-400">
                <?php render_footer_text(); ?>
            </div>
        </footer>

    </main>

    <!-- File Info Panel Overlay -->
    <div id="info-panel-overlay" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden z-40 transition-opacity duration-300 ease-in-out"></div>

    <!-- File Info Panel -->
    <div id="file-info-panel" class="fixed top-0 right-0 h-full w-80 bg-white dark:bg-google-gray-900 border-l border-google-gray-200 dark:border-google-gray-700 shadow-lg transform translate-x-full transition-transform duration-300 ease-in-out z-50">
        <div class="flex flex-col h-full">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b border-google-gray-200 dark:border-google-gray-700">
                <h3 class="text-lg font-medium text-google-gray-900 dark:text-white"><?php _e('File info', 'drive_template'); ?></h3>
                <button id="close-info-panel" class="p-2 rounded-full hover:bg-google-gray-100 dark:hover:bg-google-gray-800">
                    <span class="material-icons text-google-gray-600 dark:text-google-gray-400">close</span>
                </button>
            </div>
            
            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-4">
                <div id="file-info-content">
                    <!-- File info will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <?php render_custom_assets('body_bottom'); ?>
    <script src="<?php echo ASSETS_URL; ?>/lib/jquery/jquery.min.js"></script>
    <script>
        // Set CSRF token for JavaScript
        window.csrf_token = '<?php echo getCsrfToken(); ?>';
    </script>
    <script src="<?php echo $this_template_url; ?>js/drive.js"></script>

</body>
</html>