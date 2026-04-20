<?php
/*
Public files template for Business Professional theme
*/
$ld = 'business_template';

// Get count from files array
$count = isset($files['pagination']['total']) ? $files['pagination']['total'] : 0;

// Handle per_page parameter for public view
$default_per_page = get_option('pagination_results_per_page');
if (isset($_GET['per_page']) && in_array($_GET['per_page'], [5, 10, 15, 20, 25, 50, 100])) {
    define('TEMPLATE_RESULTS_PER_PAGE', (int)$_GET['per_page']);
} else {
    define('TEMPLATE_RESULTS_PER_PAGE', $default_per_page);
}
define('TEMPLATE_THUMBNAILS_WIDTH', '120');
define('TEMPLATE_THUMBNAILS_HEIGHT', '120');

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
        }
    } else {
        $flash->warning(__('There are no public files available.', 'business_template'));
    }
}

// Determine logged-in state
$is_logged_in = defined('CURRENT_USER_ID') && CURRENT_USER_ID !== null;

// Get client info if logged in
if ($is_logged_in) {
    $client_info = get_client_by_id(CURRENT_USER_ID);
}

$window_title = __('Public Document Center', 'business_template');

$page_id = 'business_template_public';

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

$body_class = array('template', 'business-template', 'business-public', 'hide_title');

// Results count
$elements_found_count = $count;
$count_for_pagination = $count;

// Pagination
$pagination_page = (isset($_GET["page"])) ? $_GET["page"] : 1;

?>
<!DOCTYPE html>
<html lang="<?php echo SITE_LANG; ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_output($window_title . ' &raquo; ' . SYSTEM_NAME); ?></title>
    <?php meta_favicon(); ?>

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
        
        // Initialize theme on load
        initTheme();
    </script>

    <script>
        window.base_url = '<?php echo BASE_URI; ?>';
        window.isPublicContext = true;
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
                    <div class="ml-4 px-3 py-1 bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200 text-sm font-medium rounded-full">
                        <?php echo __('Public Access', 'business_template'); ?>
                    </div>
                </div>

                <!-- Navigation Menu -->
                <div class="flex items-center space-x-6">
                    <!-- Files Count -->
                    <div class="hidden md:flex text-sm text-gray-600 dark:text-gray-300">
                        <i class="fas fa-globe mr-2"></i>
                        <?php echo sprintf(__('%d public documents', 'business_template'), $count_for_pagination); ?>
                    </div>

                    <?php if ($is_logged_in) { ?>
                        <!-- User Info (if logged in) -->
                        <div class="hidden md:flex items-center text-sm text-gray-600 dark:text-gray-300">
                            <i class="fas fa-user mr-2"></i>
                            <?php echo htmlspecialchars($client_info['name']); ?>
                        </div>
                        
                        <!-- Private Files Link -->
                        <a href="<?php echo CLIENT_VIEW_FILE_LIST_URL; ?>" 
                           class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200"
                           title="<?php _e('Private Files', 'business_template'); ?>">
                            <i class="fas fa-lock"></i>
                        </a>
                        
                        <!-- Dashboard Link -->
                        <a href="<?php echo BASE_URI; ?>manage-files.php" 
                           class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200"
                           title="<?php _e('Dashboard', 'business_template'); ?>">
                            <i class="fas fa-tachometer-alt"></i>
                        </a>
                        
                        <?php if (current_user_can_upload()) { ?>
                        <a href="<?php echo BASE_URI; ?>upload.php" 
                           class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200"
                           title="<?php _e('Upload Files', 'business_template'); ?>">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </a>
                        <?php } ?>
                        
                        <!-- Logout -->
                        <a href="<?php echo BASE_URI; ?>process.php?do=logout" 
                           class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200"
                           title="<?php _e('Logout', 'business_template'); ?>">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    <?php } else { ?>
                        <!-- Login Link -->
                        <a href="<?php echo BASE_URI; ?>index.php" 
                           class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            <?php _e('Login', 'business_template'); ?>
                        </a>
                    <?php } ?>

                    <!-- Dark Mode Toggle -->
                    <button onclick="toggleTheme()" 
                            class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200"
                            title="<?php _e('Toggle dark mode', 'business_template'); ?>">
                        <i class="fas fa-moon dark:hidden"></i>
                        <i class="fas fa-sun hidden dark:inline"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                        <?php echo __('Public Document Center', 'business_template'); ?>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300">
                        <?php echo __('Browse and download publicly available documents', 'business_template'); ?>
                    </p>
                </div>
                
                <!-- Quick Stats -->
                <div class="mt-4 sm:mt-0 flex space-x-4">
                    <div class="bg-white dark:bg-gray-800 px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-600 dark:text-gray-300"><?php echo __('Total Files', 'business_template'); ?></div>
                        <div class="text-xl font-semibold text-gray-900 dark:text-white"><?php echo $count_for_pagination; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Groups Navigation -->
        <?php 
        $groups = get_groups(['public' => true]);
        if (!empty($groups) && $mode !== 'group'): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <i class="fas fa-layer-group mr-2"></i>
                <?php echo __('Browse by Group', 'business_template'); ?>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($groups as $group): 
                    $group_file_count = count_public_files_in_group($group['id']); ?>
                    <a href="<?php echo BASE_URI; ?>public.php?group=<?php echo $group['id']; ?>&token=<?php echo $group['public_token']; ?>" 
                       class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-primary-300 dark:hover:border-primary-700 transition-colors duration-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-folder text-primary-600 dark:text-primary-400 mr-3"></i>
                                <span class="font-medium text-gray-900 dark:text-white"><?php echo html_output($group['name']); ?></span>
                            </div>
                            <span class="bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                <?php echo $group_file_count; ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Group Breadcrumb (when viewing a group) -->
        <?php if ($mode === 'group' && isset($group_props)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
            <div class="flex items-center space-x-2 text-sm">
                <a href="<?php echo BASE_URI; ?>public.php" class="flex items-center text-primary-900 dark:text-primary-500 hover:text-primary-800 dark:hover:text-primary-400">
                    <i class="fas fa-home mr-1"></i>
                    <?php echo __('All Public Files', 'business_template'); ?>
                </a>
                <span class="text-gray-400 dark:text-gray-500">></span>
                <span class="flex items-center text-gray-700 dark:text-gray-300">
                    <i class="fas fa-folder mr-1"></i>
                    <?php echo html_output($group_props['name']); ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search and Filters -->
        <?php if ($count > 0 || isset($_GET['search'])): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <form action="<?php echo BASE_URI; ?>public.php" method="get" class="flex flex-col lg:flex-row gap-4">
                <?php if (isset($_GET['group'])): ?>
                    <input type="hidden" name="group" value="<?php echo htmlspecialchars($_GET['group']); ?>">
                <?php endif; ?>
                <?php if (isset($_GET['token'])): ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
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
                               placeholder="<?php echo __('Search documents...', 'business_template'); ?>"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                </div>

                <!-- Search Button -->
                <button type="submit" 
                        class="px-6 py-2 bg-primary-900 hover:bg-primary-800 text-white rounded-lg font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    <i class="fas fa-check mr-2"></i>
                    <?php echo __('Apply', 'business_template'); ?>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Folder Navigation -->
        <?php
            $current_url = get_form_action_with_existing_parameters('public.php');
            $current_folder = (isset($_GET['folder_id'])) ? (int)$_GET['folder_id'] : null;
            include_once LAYOUT_DIR . DS . 'breadcrumbs.php';
            include_once LAYOUT_DIR . DS . 'folders-nav.php';
        ?>

        <!-- Files Grid -->
        <?php if (isset($count) && $count > 0 && isset($files['files_ids']) && !empty($files['files_ids'])) { ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
            <?php foreach ($files['files_ids'] as $file_id): 
                $file = new \ProjectSend\Classes\Files($file_id);
                
                // Skip expired files in public view
                if ($file->expired) {
                    continue;
                }
            ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow duration-200 overflow-hidden file-card cursor-pointer flex flex-col" data-file-id="<?php echo $file->id; ?>" data-expired="<?php echo $file->expired ? 'true' : 'false'; ?>">
                
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

                    <!-- Action Buttons -->
                    <div class="flex gap-2">
                        <!-- View Details Button -->
                        <a href="<?php echo BASE_URI; ?>download.php?id=<?php echo $file->id; ?>&token=<?php echo $file->public_token; ?>" 
                           class="flex-1 flex items-center justify-center px-3 py-1.5 border border-primary-900 text-primary-900 hover:bg-primary-900 hover:text-white rounded-md text-xs font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                            <i class="fas fa-eye mr-1.5 text-xs"></i>
                            <span class="uppercase tracking-wider"><?php echo __('View', 'business_template'); ?></span>
                        </a>
                        
                        <!-- Download Button -->
                        <a href="<?php echo $file->download_link; ?>" 
                           class="flex-1 flex items-center justify-center px-3 py-1.5 bg-primary-900 hover:bg-primary-800 text-white rounded-md text-xs font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                            <i class="fas fa-download mr-1.5 text-xs"></i>
                            <span class="uppercase tracking-wider"><?php echo __('Download', 'business_template'); ?></span>
                        </a>
                    </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
            
            <!-- Pagination -->
            <?php
            if (isset($files['pagination']) && isset($files['pagination']['total']) && $files['pagination']['total'] > TEMPLATE_RESULTS_PER_PAGE) {
                $pagination = new \ProjectSend\Classes\Layout\Pagination;
                echo '<div class="flex justify-center">';
                echo $pagination->make([
                    'link' => 'public.php',
                    'current' => $pagination_page,
                    'item_count' => $files['pagination']['total'],
                    'items_per_page' => TEMPLATE_RESULTS_PER_PAGE,
                ]);
                echo '</div>';
            }
            ?>
            
        <?php } else { ?>
            <!-- No Files Message -->
            <div class="text-center py-12">
                <div class="mx-auto w-24 h-24 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-folder-open text-3xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                    <?php echo __('No public documents found', 'business_template'); ?>
                </h3>
                <p class="text-gray-600 dark:text-gray-300 mb-6 max-w-md mx-auto">
                    <?php if (isset($_GET['search']) && !empty($_GET['search'])) { ?>
                        <?php echo __('Your search returned no results. Try different keywords or browse all files.', 'business_template'); ?>
                    <?php } else { ?>
                        <?php echo __('There are currently no documents available for public access.', 'business_template'); ?>
                    <?php } ?>
                </p>
                
                <?php if (isset($_GET['search']) && !empty($_GET['search'])) { ?>
                    <a href="<?php echo BASE_URI; ?>public.php" 
                       class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">
                        <i class="fas fa-list mr-2"></i>
                        <?php echo __('Browse All Files', 'business_template'); ?>
                    </a>
                <?php } elseif ($is_logged_in && current_user_can_upload()) { ?>
                    <a href="<?php echo BASE_URI; ?>upload.php" 
                       class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200">
                        <i class="fas fa-cloud-upload-alt mr-2"></i>
                        <?php echo __('Upload Files', 'business_template'); ?>
                    </a>
                <?php } ?>
            </div>
        <?php } ?>
        
    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12">
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="text-center text-sm text-gray-600 dark:text-gray-300">
                <?php render_footer_text(); ?>
            </div>
        </div>
    </footer>

    <script>
        // Per page functionality
        function updatePerPage(value) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', value);
            window.location = url.toString();
        }
        
        // Initialize tooltips if needed
        document.addEventListener('DOMContentLoaded', function() {
            // Add any JavaScript initialization here
            console.log('Business Template Public View loaded');
        });
    </script>

    <?php render_custom_assets('body_bottom'); ?>

</body>
</html>