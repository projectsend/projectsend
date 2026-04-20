<?php
/*
Template name: Business Professional
URI: https://www.projectsend.org/templates/business
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: Professional business template with light/dark toggle, clean design, and top navigation
*/
$ld = 'business_template'; // specify the language domain for this template

// Handle per_page parameter
$default_per_page = get_option('pagination_results_per_page');
if (isset($_GET['per_page']) && in_array($_GET['per_page'], [5, 10, 15, 20, 25, 50, 100])) {
    define('TEMPLATE_RESULTS_PER_PAGE', (int)$_GET['per_page']);
} else {
    define('TEMPLATE_RESULTS_PER_PAGE', $default_per_page);
}
define('TEMPLATE_THUMBNAILS_WIDTH', '120');
define('TEMPLATE_THUMBNAILS_HEIGHT', '120');

$filter_by_category = (isset($_GET['category']) && $_GET['category'] !== '') ? $_GET['category'] : null;

$current_url = get_form_action_with_existing_parameters('index.php');

// When searching, don't limit to current folder - search globally
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $_GET['global_search'] = true;
}

include_once ROOT_DIR . '/templates/common.php'; // include the required functions for every template

// Logo info is already available from common.php as $logo_file_info

$window_title = __('Document Center', 'business_template');

$page_id = 'business_template';

$body_class = array('template', 'business-template', 'hide_title');

// Flash errors
if (!$count) {
    if (isset($no_results_error)) {
        switch ($no_results_error) {
            case 'search':
                $flash->error(__('Your search keywords returned no results.', 'business_template'));
                break;
            case 'filter':
                $flash->error(__('The filters you selected returned no results.', 'business_template'));
                break;
            default:
                $flash->warning(__('There are no files available.', 'business_template'));
                break;
        }
    } else {
        $flash->warning(__('There are no files available.', 'business_template'));
    }
}

include_once 'lang/' . LOADED_LANG . '.mo.php';
?>
<!DOCTYPE html>
<html lang="<?php echo LOADED_LANG; ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_output($window_title); ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f5f3ff',
                            100: '#ede9fe',
                            200: '#ddd6fe',
                            300: '#c4b5fd',
                            400: '#a78bfa',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                            700: '#6d28d9',
                            800: '#5b21b6',
                            900: '#4c2ab6'
                        },
                        gray: {
                            50: '#f9fafb',
                            100: '#f3f4f6',
                            200: '#e5e7eb',
                            800: '#1f2937',
                            900: '#111827'
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $this_template_url; ?>css/business.css">
    
    <script>
        // Dark mode toggle functionality
        function initTheme() {
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            } else {
                document.documentElement.classList.remove('dark')
            }
        }
        
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark')
                localStorage.theme = 'light'
            } else {
                document.documentElement.classList.add('dark')
                localStorage.theme = 'dark'
            }
        }
        
        // Initialize theme on page load
        initTheme();
    </script>
    
    <script>
        window.base_url = '<?php echo BASE_URI; ?>';
    </script>

    <?php render_custom_assets('head'); ?>
</head>

<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
    <?php render_custom_assets('body_top'); ?>

    <!-- Top Navigation Bar -->
    <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo/Title -->
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <?php if ($logo_file_info && $logo_file_info['exists']): ?>
                            <img src="<?php echo $logo_file_info['url']; ?>" alt="<?php echo get_option('this_install_title'); ?>" class="h-10 w-auto">
                        <?php else: ?>
                            <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                                <?php echo html_output(get_option('this_install_title')); ?>
                            </h1>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Navigation Menu -->
                <div class="flex items-center space-x-6">
                    <!-- Files Count -->
                    <div class="hidden md:flex text-sm text-gray-600 dark:text-gray-300">
                        <i class="fas fa-folder-open mr-2"></i>
                        <?php echo sprintf(__('%d documents available', 'business_template'), $count_for_pagination); ?>
                    </div>

                    <!-- Navigation Icons -->
                    <a href="<?php echo BASE_URI; ?>manage-files.php" 
                       class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200"
                       title="<?php _e('Manage Files', 'business_template'); ?>">
                        <i class="fas fa-tachometer-alt"></i>
                    </a>
                    
                    <?php if (current_user_can_upload()): ?>
                    <a href="<?php echo BASE_URI; ?>upload.php" 
                       class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200"
                       title="<?php _e('Upload Files', 'business_template'); ?>">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </a>
                    <?php endif; ?>

                    <!-- Dark Mode Toggle -->
                    <button onclick="toggleTheme()" 
                            class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200"
                            title="<?php _e('Toggle dark mode', 'business_template'); ?>">
                        <i class="fas fa-moon dark:hidden"></i>
                        <i class="fas fa-sun hidden dark:inline"></i>
                    </button>

                    <!-- Account Menu -->
                    <div class="relative group">
                        <button class="flex items-center p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                            <i class="fas fa-user-circle mr-2"></i>
                            <span class="hidden md:inline"><?php echo html_output(CURRENT_USER_NAME); ?></span>
                            <i class="fas fa-chevron-down ml-2"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="py-2">
                                <a href="<?php echo client_get_profile_link(); ?>" 
                                   class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user mr-3"></i>
                                    <?php _e('Edit Profile', 'business_template'); ?>
                                </a>
                                <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>
                                <a href="<?php echo BASE_URI; ?>process.php?do=logout" 
                                   class="flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                    <i class="fas fa-sign-out-alt mr-3"></i>
                                    <?php _e('Sign Out', 'business_template'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb Navigation -->
    <?php if (!empty($_GET['folder_id'])): ?>
    <div class="w-full bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center space-x-2 text-sm">
                <?php 
                // Build full breadcrumb path
                $breadcrumbs = [];
                $current_folder_id = !empty($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
                
                // If we're in a folder, build the path
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
                
                // Root link
                $root_link = modify_url_with_parameters($current_url, [], ['folder_id']); ?>
                <a href="<?php echo $root_link; ?>" class="flex items-center text-primary-900 dark:text-primary-500 hover:text-primary-800 dark:hover:text-primary-400">
                    <i class="fas fa-hdd mr-1"></i>
                    <?php _e('Root', 'business_template'); ?>
                </a>
                
                <?php if (!empty($breadcrumbs)): ?>
                    <?php foreach ($breadcrumbs as $index => $breadcrumb): 
                        $is_last = ($index === count($breadcrumbs) - 1);
                        $breadcrumb_link = modify_url_with_parameters($current_url, ['folder_id' => $breadcrumb['id']], ['folder_id']); ?>
                        <span class="text-gray-400 dark:text-gray-500">></span>
                        <?php if ($is_last): ?>
                            <span class="flex items-center text-gray-700 dark:text-gray-300">
                                <i class="fas fa-folder mr-1"></i>
                                <?php echo html_output($breadcrumb['name']); ?>
                            </span>
                        <?php else: ?>
                            <a href="<?php echo $breadcrumb_link; ?>" class="flex items-center text-primary-900 dark:text-primary-500 hover:text-primary-800 dark:hover:text-primary-400">
                                <i class="fas fa-folder mr-1"></i>
                                <?php echo html_output($breadcrumb['name']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <main class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Flash Messages -->
        <?php if ($flash->hasMessages()): ?>
        <div class="mb-6">
            <?php echo $flash->display(); ?>
        </div>
        <?php endif; ?>

        <!-- Folders Navigation -->
        <?php if (!empty($folders)): ?>
        <div class="mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4"><?php _e('Folders Navigation', 'business_template'); ?></h3>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">
                    <?php foreach ($folders as $folder_data): 
                        $folder = new \ProjectSend\Classes\Folder($folder_data['id']);
                        $folder_data = $folder->getData();
                        $link = modify_url_with_parameters($current_url, ['folder_id' => $folder_data['id']], ['folder_id']); ?>
                        <a href="<?php echo $link; ?>" class="flex items-center p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <i class="fas fa-folder text-yellow-600 mr-2"></i>
                            <span class="text-sm text-gray-700 dark:text-gray-300 truncate"><?php echo html_output($folder->name); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search and Filters -->
        <?php if ($count > 0 || isset($_GET['search']) || isset($_GET['category'])): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <form action="<?php echo BASE_URI; ?>my_files/index.php" method="get" class="flex flex-col lg:flex-row gap-4">
                <?php 
                // Preserve folder_id if present
                if (isset($_GET['folder_id'])): ?>
                    <input type="hidden" name="folder_id" value="<?php echo htmlspecialchars($_GET['folder_id']); ?>">
                <?php endif; ?>
                
                <!-- Search Input -->
                <div class="flex-1">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               name="search" 
                               value="<?php echo isset($_GET['search']) ? html_output($_GET['search']) : ''; ?>"
                               placeholder="<?php _e('Search documents...', 'business_template'); ?>"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                </div>

                <!-- Category Filter -->
                <?php if (!empty($get_categories['categories'])): ?>
                <div class="lg:w-64">
                    <select name="category" 
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value=""><?php _e('All Categories', 'business_template'); ?></option>
                        <?php foreach ($get_categories['categories'] as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo ($filter_by_category == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo html_output($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Results Per Page -->
                <div class="lg:w-32">
                    <select name="per_page" onchange="this.form.submit()" 
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <?php 
                        $current_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : TEMPLATE_RESULTS_PER_PAGE;
                        foreach ([5, 10, 15, 20, 25, 50, 100] as $value): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($current_per_page == $value) ? 'selected' : ''; ?>>
                            <?php echo $value; ?> <?php _e('per page', 'business_template'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Search Button -->
                <button type="submit" 
                        class="px-6 py-2 bg-primary-900 hover:bg-primary-800 text-white rounded-lg font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    <i class="fas fa-check mr-2"></i>
                    <?php _e('Apply', 'business_template'); ?>
                </button>
            </form>
        </div>
        <?php endif; ?>




        <!-- Batch Actions -->
        <?php if ($count > 0): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
                <div class="flex items-center">
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="select_all" class="rounded border-gray-300 dark:border-gray-600 text-primary-900 focus:ring-primary-500" />
                        <label for="select_all" class="text-sm text-gray-700 dark:text-gray-300"><?php _e('Select all', 'business_template'); ?></label>
                    </div>
                </div>
            </div>

        <!-- Files Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
            <?php foreach ($available_files as $file_id): 
                $file = new \ProjectSend\Classes\Files($file_id);
            ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow duration-200 overflow-hidden file-card cursor-pointer flex flex-col" data-file-id="<?php echo $file->id; ?>" data-expired="<?php echo $file->expired ? 'true' : 'false'; ?>">
                <!-- Hidden File Checkbox -->
                <?php if (!$file->expired): ?>
                <input type="checkbox" name="files[]" value="<?php echo $file->id; ?>" class="batch_checkbox hidden" />
                <?php endif; ?>
                <!-- File Icon/Thumbnail -->
                <div class="p-6 text-center relative">
                    <?php if ($file->isImage() && !$file->expired): 
                        $thumbnail = make_thumbnail($file->full_path, null, TEMPLATE_THUMBNAILS_WIDTH, TEMPLATE_THUMBNAILS_HEIGHT);
                        if (!empty($thumbnail['thumbnail']['url'])): ?>
                            <img src="<?php echo html_output($thumbnail['thumbnail']['url']); ?>" 
                                 alt="<?php echo html_output($file->title); ?>"
                                 class="w-20 h-20 mx-auto rounded-lg object-cover">
                        <?php else: ?>
                            <div class="w-20 h-20 mx-auto bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <i class="<?php echo get_file_type_icon($file->extension); ?> text-3xl text-gray-400 dark:text-gray-500"></i>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="w-20 h-20 mx-auto bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                            <i class="<?php echo get_file_type_icon($file->extension); ?> text-3xl text-gray-400 dark:text-gray-500"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- File Details -->
                <div class="px-6 pb-4 flex-1 flex flex-col">
                    <!-- Top Content -->
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2 line-clamp-2" title="<?php echo html_output($file->title); ?>">
                            <?php echo html_output($file->title); ?>
                        </h3>
                        
                        <?php if (!empty($file->description)): ?>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                            <?php echo format_description($file->description); ?>
                        </p>
                        <?php endif; ?>
                    </div>

                    <!-- Bottom Content - Always at bottom -->
                    <div class="mt-auto">
                        <!-- File Meta -->
                        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-3">
                            <span><?php echo $file->size_formatted; ?></span>
                            <span><?php echo format_date($file->uploaded_date); ?></span>
                        </div>

                        <!-- Category -->
                        <?php 
                        $file_categories = $file->getCurrentCategories();
                        if (!empty($file_categories)): ?>
                        <div class="mb-3">
                            <?php foreach ($file_categories as $category): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-200 mr-1">
                                <?php echo html_output($category['name']); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                    <!-- Download Button -->
                    <a href="<?php echo $file->download_link; ?>" 
                       class="w-full flex items-center justify-center px-3 py-1.5 bg-primary-900 hover:bg-primary-800 text-white rounded-md text-xs font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                        <i class="fas fa-download mr-1.5 text-xs"></i>
                        <span class="uppercase tracking-wider"><?php _e('Download', 'business_template'); ?></span>
                    </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Fixed ZIP Download Button -->
        <button type="button" class="hidden fixed bottom-6 right-6 px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-full font-medium shadow-lg hover:shadow-xl transition-all duration-300 z-50" id="zip-download-btn">
            <i class="fas fa-download mr-2"></i>
            <span class="zip-text"><?php _e('Download as ZIP', 'business_template'); ?></span>
        </button>

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

        <?php else: ?>
        <!-- No Files State -->
        <div class="text-center py-12">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-12">
                <i class="fas fa-folder-open text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                    <?php _e('No Documents Found', 'business_template'); ?>
                </h3>
                <p class="text-gray-600 dark:text-gray-400">
                    <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                        <?php _e('No documents match your search criteria.', 'business_template'); ?>
                    <?php else: ?>
                        <?php _e('There are no documents available for download at this time.', 'business_template'); ?>
                    <?php endif; ?>
                </p>
                <?php if (isset($_GET['search']) || isset($_GET['category'])): ?>
                <a href="<?php echo BASE_URI; ?>" 
                   class="inline-flex items-center mt-4 px-4 py-2 text-primary-900 dark:text-primary-500 hover:text-primary-800 dark:hover:text-primary-400 font-medium">
                    <i class="fas fa-arrow-left mr-2"></i>
                    <?php _e('View All Documents', 'business_template'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="mt-16 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="text-center text-sm text-gray-600 dark:text-gray-400">
                <?php _e('Powered by', 'business_template'); ?> 
                <a href="https://www.projectsend.org" target="_blank" class="text-primary-900 dark:text-primary-500 hover:text-primary-800 dark:hover:text-primary-400 font-medium">
                    ProjectSend
                </a>
            </div>
        </div>
    </footer>

    <!-- Custom JavaScript -->
    <script src="<?php echo $this_template_url; ?>js/business.js"></script>

    <?php render_custom_assets('body_bottom'); ?>
</body>
</html>