<?php
/*
Public files template for Google Drive theme
*/
$ld = 'drive_template';

$count = $files['pagination']['total'];

if ($count == 0) {
    if (isset($_GET['search'])) {
        $no_results_message = __('Your search keywords returned no results.', 'drive_template');
    } else {
        $no_results_message = __('There are no files available.', 'drive_template');
    }
}

$groups = get_groups([
    'public' => true,
]);

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

$page_title = ($mode == 'files') ? __('Public Files', 'drive_template') : sprintf(__('Files in group: %s', 'drive_template'), $group_props['name']);

// Include language file
include_once 'lang/' . LOADED_LANG . '.mo.php';
?>
<!DOCTYPE html>
<html lang="<?php echo SITE_LANG; ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_output($page_title . ' - ' . get_option('this_install_title')); ?></title>
    
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
        
        // Theme management - only initialization
        function initTheme() {
            try {
                var storedTheme = localStorage.getItem('theme');
                
                if (storedTheme === 'dark' || (storedTheme === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        }
        
        // Initialize theme immediately
        initTheme();
    </script>
</head>

<body class="h-full bg-white dark:bg-google-gray-900 font-sans">
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
                <?php if ($count > 0): ?>
                <div class="flex-1 max-w-2xl">
                    <form action="<?php echo BASE_URI; ?>public.php" method="get" class="relative">
                        <?php if (isset($_GET['group'])): ?>
                            <input type="hidden" name="group" value="<?php echo htmlspecialchars($_GET['group']); ?>">
                        <?php endif; ?>
                        <?php if (isset($_GET['token'])): ?>
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
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
                <?php endif; ?>
            </div>

            <!-- Right Side: Actions -->
            <div class="flex items-center space-x-2">
                <?php if (user_is_logged_in()): ?>
                    <!-- Navigation Icons for logged in users -->
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
                    <?php if (user_is_logged_in()): ?>
                        <button class="w-8 h-8 bg-google-green rounded-full flex items-center justify-center text-white text-sm font-medium hover:shadow-md transition-shadow">
                            <?php echo strtoupper(substr(CURRENT_USER_NAME, 0, 1)); ?>
                        </button>
                        
                        <!-- User Dropdown -->
                        <div class="absolute right-0 mt-2 w-80 bg-white dark:bg-google-gray-800 rounded-lg shadow-lg border border-google-gray-200 dark:border-google-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="p-6 text-center border-b border-google-gray-200 dark:border-google-gray-700">
                                <div class="w-20 h-20 bg-google-green rounded-full flex items-center justify-center text-white text-2xl font-medium mx-auto mb-4">
                                    <?php echo strtoupper(substr(CURRENT_USER_NAME, 0, 1)); ?>
                                </div>
                                <p class="font-medium text-google-gray-900 dark:text-google-gray-100"><?php echo html_output(CURRENT_USER_NAME); ?></p>
                                <p class="text-sm text-google-gray-500 dark:text-google-gray-400"><?php echo html_output(CURRENT_USER_USERNAME); ?></p>
                            </div>
                            <div class="py-2">
                                <a href="<?php echo client_get_profile_link(); ?>" 
                                   class="flex items-center px-4 py-3 text-sm text-google-gray-700 dark:text-google-gray-200 hover:bg-google-gray-100 dark:hover:bg-google-gray-700">
                                    <span class="material-icons text-google-gray-500 mr-3">account_circle</span>
                                    <?php _e('Edit Profile', 'drive_template'); ?>
                                </a>
                                <a href="<?php echo BASE_URI; ?>my_files/" 
                                   class="flex items-center px-4 py-3 text-sm text-google-gray-700 dark:text-google-gray-200 hover:bg-google-gray-100 dark:hover:bg-google-gray-700">
                                    <span class="material-icons text-google-gray-500 mr-3">folder</span>
                                    <?php _e('My Files', 'drive_template'); ?>
                                </a>
                                <a href="<?php echo BASE_URI; ?>process.php?do=logout" 
                                   class="flex items-center px-4 py-3 text-sm text-google-gray-700 dark:text-google-gray-200 hover:bg-google-gray-100 dark:hover:bg-google-gray-700">
                                    <span class="material-icons text-google-gray-500 mr-3">logout</span>
                                    <?php _e('Sign Out', 'drive_template'); ?>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo BASE_URI; ?>index.php" class="w-8 h-8 bg-google-green rounded-full flex items-center justify-center text-white text-sm font-medium hover:shadow-md transition-shadow">
                            <span class="material-icons text-sm">person</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb Navigation -->
    <?php if ($mode == 'group'): ?>
    <div class="bg-google-gray-50 dark:bg-google-gray-800 border-b border-google-gray-200 dark:border-google-gray-700">
        <div class="px-6 py-3">
            <div class="flex items-center space-x-2 text-sm">
                <a href="<?php echo BASE_URI; ?>public.php" class="flex items-center text-google-green hover:text-google-green-dark">
                    <span class="material-icons text-base mr-1">folder</span>
                    <?php _e('Public Files', 'drive_template'); ?>
                </a>
                <span class="material-icons text-google-gray-400 text-sm">chevron_right</span>
                <span class="flex items-center text-google-gray-700 dark:text-google-gray-300">
                    <span class="material-icons text-base mr-1">folder</span>
                    <?php echo html_output($group_props['name']); ?>
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="flex-1 overflow-auto">
        <!-- Group Description -->
        <?php if ($mode == 'group' && !empty($group_props['description'])): ?>
        <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-400 p-6 m-6 rounded-lg">
            <h3 class="text-lg font-medium text-blue-900 dark:text-blue-200 mb-2">
                <?php _e('About this group', 'drive_template'); ?>
            </h3>
            <p class="text-blue-700 dark:text-blue-300">
                <?php echo htmlentities_allowed($group_props['description']); ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="border-b border-google-gray-200 dark:border-google-gray-700 bg-white dark:bg-google-gray-900">
            <div class="px-6 py-4 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <h1 class="text-xl font-medium text-google-gray-900 dark:text-white">
                        <?php echo html_output($page_title); ?>
                    </h1>
                    
                    <!-- Group Filter -->
                    <?php if (!empty($groups) && $mode !== 'group'): ?>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-google-gray-600 dark:text-google-gray-400">
                            <?php _e('Filter:', 'drive_template'); ?>
                        </span>
                        <form action="<?php echo BASE_URI; ?>public.php" method="get" class="inline">
                            <select name="group" onchange="this.form.submit()" 
                                    class="px-3 py-1 bg-white dark:bg-google-gray-800 border border-google-gray-300 dark:border-google-gray-600 rounded text-sm text-google-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-google-green">
                                <option value=""><?php _e('All files', 'drive_template'); ?> (<?php echo count_public_files_not_in_groups(); ?>)</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>" 
                                            data-token="<?php echo $group['public_token']; ?>"
                                            <?php if (isset($_GET['group']) && $_GET['group'] == $group['id']) echo 'selected'; ?>>
                                        <?php echo html_output($group['name']); ?> (<?php echo count_public_files_in_group($group['id']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="flex items-center space-x-2">
                    <?php if ($count > 0): ?>
                    <span class="text-sm text-google-gray-600 dark:text-google-gray-400">
                        <?php echo sprintf(_n('%d file', '%d files', $count, 'drive_template'), $count); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Files List -->
        <?php if ($count > 0): ?>
        <div class="files-wrapper">
            <?php foreach ($files['files_ids'] as $file_id): 
                $file = new \ProjectSend\Classes\Files($file_id);
                $is_expired = $file->expired;
                
                // Get file icon
                $icon = get_material_file_icon($file->extension);
                ?>
                
                <div class="file-row border-b border-google-gray-200 dark:border-google-gray-700 hover:bg-google-gray-50 dark:hover:bg-google-gray-800 <?php echo $is_expired ? 'opacity-60' : ''; ?>">
                    <div class="flex items-center px-6 py-4">
                        <!-- File Icon -->
                        <div class="flex-shrink-0 mr-4">
                            <?php if (!$is_expired && $file->isImage()): ?>
                                <?php $thumbnail = make_thumbnail($file->full_path, null, 40, 40); ?>
                                <?php if (!empty($thumbnail['thumbnail']['url'])): ?>
                                    <img src="<?php echo $thumbnail['thumbnail']['url']; ?>" 
                                         alt="<?php echo html_output($file->title); ?>"
                                         class="w-10 h-10 object-cover rounded">
                                <?php else: ?>
                                    <span class="material-icons text-2xl text-google-gray-600 dark:text-google-gray-400"><?php echo $icon; ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="material-icons text-2xl text-google-gray-600 dark:text-google-gray-400"><?php echo $icon; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- File Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center">
                                <h3 class="text-sm font-medium text-google-gray-900 dark:text-white truncate">
                                    <?php echo html_output($file->title); ?>
                                </h3>
                                <?php if ($file->title != $file->filename_original): ?>
                                <span class="ml-2 text-xs text-google-gray-500 dark:text-google-gray-400 truncate">
                                    (<?php echo html_output($file->filename_original); ?>)
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($file->description)): ?>
                            <p class="text-xs text-google-gray-600 dark:text-google-gray-300 truncate mt-1">
                                <?php echo format_description($file->description); ?>
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- File Details -->
                        <div class="flex items-center space-x-6 text-sm text-google-gray-500 dark:text-google-gray-400">
                            <div class="hidden md:block">
                                <?php echo format_date($file->uploaded_date); ?>
                            </div>
                            <div class="hidden sm:block">
                                <?php echo $file->size_formatted; ?>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex items-center space-x-2">
                                <?php if (!$is_expired): ?>
                                    <button type="button" 
                                           class="file-info-btn p-2 rounded-full hover:bg-google-gray-200 dark:hover:bg-google-gray-700"
                                           data-file-id="<?php echo $file->id; ?>"
                                           title="<?php _e('File info', 'drive_template'); ?>">
                                        <span class="material-icons text-lg text-google-gray-600 dark:text-google-gray-400">info</span>
                                    </button>
                                    
                                    <a href="<?php echo $file->public_url; ?>" 
                                       target="_blank"
                                       class="p-2 rounded-full hover:bg-google-gray-200 dark:hover:bg-google-gray-700"
                                       title="<?php _e('Direct link', 'drive_template'); ?>">
                                        <span class="material-icons text-lg text-google-gray-600 dark:text-google-gray-400">link</span>
                                    </a>
                                    
                                    <?php if (get_option('public_listing_use_download_link') == 1 && $file->isPublic()): ?>
                                    <a href="<?php echo $file->download_link; ?>" 
                                       target="_blank"
                                       class="p-2 rounded-full hover:bg-google-gray-200 dark:hover:bg-google-gray-700"
                                       title="<?php _e('Download', 'drive_template'); ?>">
                                        <span class="material-icons text-lg text-google-gray-600 dark:text-google-gray-400">download</span>
                                    </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-red-500 text-xs">
                                        <?php _e('Expired', 'drive_template'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($files['pagination']['total'] > $per_page): ?>
        <div class="border-t border-google-gray-200 dark:border-google-gray-700 px-6 py-4">
            <div class="flex justify-center">
                <?php 
                $pagination = new \ProjectSend\Classes\Layout\Pagination;
                echo $pagination->make([
                    'link' => 'public.php',
                    'current' => $pagination_page,
                    'item_count' => $files['pagination']['total'],
                    'items_per_page' => $per_page,
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Empty State -->
        <div class="flex flex-col items-center justify-center py-20">
            <span class="material-icons text-6xl text-google-gray-300 dark:text-google-gray-600 mb-4">folder_open</span>
            <h3 class="text-lg font-medium text-google-gray-900 dark:text-white mb-2">
                <?php echo $no_results_message; ?>
            </h3>
            <?php if (!empty($_GET['search'])): ?>
            <p class="text-google-gray-500 dark:text-google-gray-400 text-center max-w-sm">
                <?php _e('Try adjusting your search terms or browse all files', 'drive_template'); ?>
            </p>
            <div class="mt-4">
                <a href="<?php echo BASE_URI; ?>public.php" 
                   class="inline-flex items-center px-4 py-2 bg-google-green text-white rounded-lg hover:bg-google-green-dark transition-colors">
                    <span class="material-icons mr-2">refresh</span>
                    <?php _e('Clear search', 'drive_template'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="px-6 py-8 mt-12 border-t border-google-gray-200 dark:border-google-gray-700">
            <!-- Login Form Links -->
            <div class="flex justify-center space-x-4 text-sm mb-6">
                <?php
                $links = [];
                if (!user_is_logged_in()) {
                    $links[] = 'register';
                }
                $links[] = 'homepage';
                login_form_links($links);
                ?>
            </div>
            
            <!-- Footer Text -->
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
    <script src="<?php echo ASSETS_URL; ?>/lib/jquery/jquery.min.js"></script>
    <script>
        // Set CSRF token for JavaScript
        window.csrf_token = '<?php echo getCsrfToken(); ?>';
    </script>
    <!-- Load drive.js for functionality -->
    <script src="<?php echo $this_template_url; ?>js/drive.js"></script>
    
    <!-- JavaScript for public file context -->
    <script>
    // Override the file info endpoint for public context
    window.isPublicContext = true;
    
    document.addEventListener('DOMContentLoaded', function() {
        const groupSelect = document.querySelector('select[name="group"]');
        if (groupSelect) {
            groupSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const form = this.closest('form');
                
                if (this.value && selectedOption.dataset.token) {
                    // Add token as hidden input
                    let tokenInput = form.querySelector('input[name="token"]');
                    if (!tokenInput) {
                        tokenInput = document.createElement('input');
                        tokenInput.type = 'hidden';
                        tokenInput.name = 'token';
                        form.appendChild(tokenInput);
                    }
                    tokenInput.value = selectedOption.dataset.token;
                } else {
                    // Remove token input
                    const tokenInput = form.querySelector('input[name="token"]');
                    if (tokenInput) {
                        tokenInput.remove();
                    }
                }
                
                form.submit();
            });
        }
    });
    
    // Theme toggle function - defined after all other scripts load
    window.toggleTheme = function() {
        try {
            var isDark = document.documentElement.classList.contains('dark');
            
            if (isDark) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        } catch (e) {
            // Fallback if localStorage fails - still toggle theme visually
            var isDark = document.documentElement.classList.contains('dark');
            if (isDark) {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
        }
    };
    </script>
</body>
</html>