<?php
/*
Template name: Dark Cards
URI: https://www.projectsend.org/templates/dark-cards
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: A dark theme template with purple and orange gradients, card-based file layout, click-to-select functionality, and mobile-inspired design
*/
$ld = 'dark_cards_template'; // specify the language domain for this template

// Handle per_page parameter
$default_per_page = get_option('pagination_results_per_page');
if (isset($_GET['per_page']) && in_array($_GET['per_page'], [5, 10, 15, 20, 25, 50, 100])) {
    define('TEMPLATE_RESULTS_PER_PAGE', (int)$_GET['per_page']);
} else {
    define('TEMPLATE_RESULTS_PER_PAGE', $default_per_page);
}
define('TEMPLATE_THUMBNAILS_WIDTH', '120');
define('TEMPLATE_THUMBNAILS_HEIGHT', '120');

// Handle category filter
$filter_by_category = (isset($_GET['category']) && $_GET['category'] !== '') ? $_GET['category'] : null;

// When searching, don't limit to current folder - search globally
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $_GET['global_search'] = true;
}

// Include common template functions and data
include_once ROOT_DIR . '/templates/common.php';

// Generate CSRF token
$csrf_token = getCsrfToken();

// Build breadcrumbs
$breadcrumbs = [['name' => 'Home', 'url' => modify_url_with_parameters($_SERVER['REQUEST_URI'], [], ['folder_id'])]];
if (!empty($current_folder)) {
    $hierarchy = (new \ProjectSend\Classes\Folder($current_folder))->getHierarchy();
    foreach (array_reverse($hierarchy) as $level) {
        if ($level['id'] != $current_folder) {
            $breadcrumbs[] = [
                'name' => $level['name'],
                'url' => modify_url_with_parameters($_SERVER['REQUEST_URI'], ['folder_id' => $level['id']])
            ];
        }
    }
    $folder_obj = new \ProjectSend\Classes\Folder($current_folder);
    $breadcrumbs[] = ['name' => $folder_obj->name, 'url' => '#'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo html_output(get_option('this_install_title')); ?></title>
    
    <!-- Google Fonts - Rounded Font -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'dark-bg': '#0a0a0f',
                        'dark-surface': '#1a1a24',
                        'dark-surface-light': '#252538',
                        'purple-primary': '#8b5cf6',
                        'purple-secondary': '#a78bfa',
                        'orange-primary': '#f97316',
                        'orange-secondary': '#fb923c',
                        'text-primary': '#f8fafc',
                        'text-secondary': '#cbd5e1',
                        'text-muted': '#64748b'
                    },
                    fontFamily: {
                        'rounded': ['Nunito', 'sans-serif']
                    },
                    backgroundImage: {
                        'gradient-purple-orange': 'linear-gradient(135deg, #8b5cf6 0%, #f97316 100%)',
                        'gradient-dark': 'linear-gradient(135deg, #1a1a24 0%, #252538 100%)',
                        'gradient-card': 'linear-gradient(135deg, #1a1a24 0%, #252538 50%, #1a1a24 100%)',
                    }
                }
            }
        }
    </script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URI; ?>templates/dark-cards/css/dark-cards.css">
</head>
<body class="bg-dark-bg text-text-primary font-rounded">
    
    <!-- Header -->
    <header class="bg-gradient-dark border-b border-dark-surface-light sticky top-0 z-30 backdrop-blur-sm">
        <div class="max-w-content mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-4">
                    <?php if ($logo_file_info): ?>
                        <img src="<?php echo $logo_file_info['url']; ?>" alt="<?php echo get_option('this_install_title'); ?>" class="h-10 w-auto max-w-48 branding-logo">
                    <?php else: ?>
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gradient-purple-orange rounded-xl flex items-center justify-center">
                                <span class="material-icons text-white text-xl">cloud</span>
                            </div>
                            <h1 class="text-xl font-bold text-text-primary"><?php echo html_output(get_option('this_install_title')); ?></h1>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- User Actions -->
                <div class="flex items-center space-x-4">
                    <a href="<?php echo BASE_URI; ?>manage-files.php" class="p-2 text-text-secondary hover:text-text-primary transition-colors duration-200" title="Manage Files">
                        <span class="material-icons">dashboard</span>
                    </a>
                    <?php if (current_user_can_upload()): ?>
                    <a href="<?php echo BASE_URI; ?>upload.php" class="p-2 text-text-secondary hover:text-text-primary transition-colors duration-200" title="Upload Files">
                        <span class="material-icons">cloud_upload</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URI; ?>process.php?do=logout" class="p-2 text-text-secondary hover:text-text-primary transition-colors duration-200" title="Logout">
                        <span class="material-icons">logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumbs -->
    <?php if (count($breadcrumbs) > 1 || !empty($_GET['search'])): ?>
    <nav class="bg-dark-surface border-b border-dark-surface-light">
        <div class="max-w-content mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center space-x-2 text-sm">
                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                    <?php if ($index > 0): ?>
                        <span class="material-icons text-text-muted text-sm">chevron_right</span>
                    <?php endif; ?>
                    <?php if ($index === count($breadcrumbs) - 1): ?>
                        <span class="text-text-primary font-medium"><?php echo html_output($crumb['name']); ?></span>
                    <?php else: ?>
                        <a href="<?php echo $crumb['url']; ?>" class="text-text-secondary hover:text-purple-secondary transition-colors duration-200">
                            <?php echo html_output($crumb['name']); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <?php if (!empty($_GET['search'])): ?>
                    <span class="material-icons text-text-muted text-sm">chevron_right</span>
                    <span class="text-orange-primary font-medium">Search: "<?php echo html_output($_GET['search']); ?>"</span>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Search and Filters -->
    <?php if (!empty($my_files) || isset($_GET['search']) || isset($_GET['category'])): ?>
    <div class="max-w-content mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="bg-gradient-card border border-dark-surface-light rounded-2xl p-6 mb-6">
            <form action="<?php echo BASE_URI; ?>my_files/index.php" method="get" class="flex flex-col lg:flex-row gap-4">
                <?php 
                // Preserve folder_id if present
                if (isset($_GET['folder_id'])): ?>
                    <input type="hidden" name="folder_id" value="<?php echo htmlspecialchars($_GET['folder_id']); ?>">
                <?php endif; ?>
                
                <!-- Search Input -->
                <div class="flex-1">
                    <div class="relative">
                        <span class="material-icons absolute left-3 top-1/2 transform -translate-y-1/2 text-text-muted text-xl">search</span>
                        <input type="text" 
                               name="search" 
                               value="<?php echo isset($_GET['search']) ? html_output($_GET['search']) : ''; ?>"
                               placeholder="Search files..." 
                               class="w-full pl-12 pr-4 py-3 bg-dark-surface border border-dark-surface-light rounded-xl text-text-primary placeholder-text-muted focus:outline-none focus:ring-2 focus:ring-purple-primary focus:border-transparent transition-all duration-200">
                    </div>
                </div>

                <!-- Category Filter -->
                <?php if (!empty($get_categories['categories'])): ?>
                <div class="lg:w-64">
                    <select name="category" 
                            class="block w-full px-4 py-3 bg-dark-surface border border-dark-surface-light rounded-xl text-text-primary focus:outline-none focus:ring-2 focus:ring-purple-primary focus:border-transparent transition-all duration-200">
                        <option value="">All Categories</option>
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
                            class="block w-full px-4 py-3 bg-dark-surface border border-dark-surface-light rounded-xl text-text-primary focus:outline-none focus:ring-2 focus:ring-purple-primary focus:border-transparent transition-all duration-200">
                        <?php 
                        $current_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : TEMPLATE_RESULTS_PER_PAGE;
                        foreach ([5, 10, 15, 20, 25, 50, 100] as $value): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($current_per_page == $value) ? 'selected' : ''; ?>>
                            <?php echo $value; ?> per page
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Apply Button -->
                <button type="submit" 
                        class="px-6 py-3 bg-gradient-purple-orange text-white rounded-xl font-medium transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-purple-primary focus:ring-offset-2 focus:ring-offset-dark-bg flex items-center justify-center">
                    <span class="material-icons mr-2">check</span>
                    Apply
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="max-w-content mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Folders Section -->
        <?php if (!empty($folders) && empty($_GET['search'])): ?>
        <section class="mb-8">
            <h2 class="text-lg font-semibold text-text-primary mb-4 flex items-center">
                <span class="material-icons mr-2 text-orange-primary">folder</span>
                Folders
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                <?php foreach ($folders as $folder): ?>
                <a href="<?php echo modify_url_with_parameters($_SERVER['REQUEST_URI'], ['folder_id' => $folder['id']], ['search']); ?>" 
                   class="group block">
                    <div class="bg-gradient-card border border-dark-surface-light rounded-2xl p-6 hover:border-purple-primary transition-all duration-300 hover:shadow-lg hover:shadow-purple-primary/20 hover:scale-105">
                        <div class="flex flex-col items-center text-center">
                            <div class="w-12 h-12 bg-gradient-to-br from-orange-primary to-orange-secondary rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                                <span class="material-icons text-white text-2xl">folder</span>
                            </div>
                            <h3 class="font-medium text-text-primary group-hover:text-purple-secondary transition-colors duration-200 truncate w-full">
                                <?php echo html_output($folder['name']); ?>
                            </h3>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Files Section -->
        <?php if (!empty($my_files)): ?>
        <section>
            <h2 class="text-lg font-semibold text-text-primary mb-4 flex items-center">
                <span class="material-icons mr-2 text-purple-primary">description</span>
                Files
                <span class="ml-2 text-sm text-text-muted">(<?php echo count($my_files); ?>)</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4" id="files-grid">
                <?php foreach ($my_files as $file): ?>
                <div class="file-card group cursor-pointer" data-file-id="<?php echo $file['id']; ?>">
                    <input type="checkbox" class="file-checkbox hidden" value="<?php echo $file['id']; ?>">
                    <div class="bg-gradient-card border border-dark-surface-light rounded-2xl p-4 hover:border-purple-primary transition-all duration-300 hover:shadow-lg hover:shadow-purple-primary/20 hover:scale-105 relative overflow-hidden">
                        
                        <!-- Selection Indicator -->
                        <div class="selection-indicator absolute top-2 right-2 w-6 h-6 rounded-full border-2 border-text-muted opacity-0 group-hover:opacity-100 transition-all duration-200 flex items-center justify-center">
                            <span class="material-icons text-sm text-white opacity-0">check</span>
                        </div>
                        
                        <!-- File Icon/Thumbnail -->
                        <div class="flex flex-col items-center text-center">
                            <?php
                            $file_obj = new \ProjectSend\Classes\Files($file['id']);
                            if ($file_obj->isImage() && !$file['expired'] && !empty($file_obj->full_path)):
                                $thumbnail = make_thumbnail($file_obj->full_path, null, TEMPLATE_THUMBNAILS_WIDTH, TEMPLATE_THUMBNAILS_HEIGHT);
                                if ($thumbnail && isset($thumbnail['thumbnail']['url'])):
                            ?>
                                <div class="w-16 h-16 rounded-xl overflow-hidden mb-3 bg-dark-surface-light">
                                    <img src="<?php echo $thumbnail['thumbnail']['url']; ?>" 
                                         alt="<?php echo html_output($file['name']); ?>" 
                                         class="w-full h-full object-cover">
                                </div>
                            <?php else: ?>
                                <div class="w-16 h-16 bg-gradient-to-br from-purple-primary to-purple-secondary rounded-xl flex items-center justify-center mb-3">
                                    <span class="material-icons text-white text-2xl"><?php echo get_material_file_icon($file['extension']); ?></span>
                                </div>
                            <?php endif; else: ?>
                                <div class="w-16 h-16 bg-gradient-to-br from-purple-primary to-purple-secondary rounded-xl flex items-center justify-center mb-3">
                                    <span class="material-icons text-white text-2xl"><?php echo get_material_file_icon($file['extension']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- File Info -->
                            <h3 class="font-medium text-text-primary text-sm mb-1 truncate w-full group-hover:text-purple-secondary transition-colors duration-200">
                                <?php echo html_output($file['name']); ?>
                            </h3>
                            <p class="text-xs text-text-muted">
                                <?php echo html_output($file_obj->size_formatted); ?>
                            </p>
                            <p class="text-xs text-text-muted">
                                <?php echo strtoupper($file['extension']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        
        <?php elseif (empty($folders)): ?>
        <!-- Empty State -->
        <div class="text-center py-16">
            <div class="w-24 h-24 bg-gradient-to-br from-purple-primary/20 to-orange-primary/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <span class="material-icons text-4xl text-text-muted">folder_open</span>
            </div>
            <h3 class="text-xl font-semibold text-text-primary mb-2">
                <?php if (!empty($_GET['search'])): ?>
                    No files found
                <?php else: ?>
                    This folder is empty
                <?php endif; ?>
            </h3>
            <p class="text-text-muted">
                <?php if (!empty($_GET['search'])): ?>
                    Try adjusting your search terms
                <?php else: ?>
                    No files have been shared with you in this location
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
    </main>

    <!-- Pagination -->
    <?php if (isset($count_for_pagination) && $count_for_pagination > 0 && TEMPLATE_RESULTS_PER_PAGE > 0): ?>
    <div class="pagination-wrapper max-w-content mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
    <?php endif; ?>

    <!-- Fixed ZIP Download Button -->
    <div id="zip-download-fab" class="fixed bottom-6 right-6 hidden z-50">
        <button id="download-zip-btn" class="w-16 h-16 bg-gradient-purple-orange rounded-full shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-110 flex items-center justify-center group">
            <span class="material-icons text-white text-2xl">download</span>
        </button>
        <div id="selection-count" class="absolute -top-2 -left-2 bg-orange-primary text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center"></div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        window.base_url = '<?php echo BASE_URI; ?>';
        window.csrf_token = '<?php echo $csrf_token; ?>';
    </script>
    <script src="<?php echo BASE_URI; ?>templates/dark-cards/js/dark-cards.js"></script>
    
    <!-- Footer -->
    <footer class="mt-16 py-8 border-t border-dark-surface-light">
        <div class="max-w-content mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="text-text-muted text-sm">
                <?php render_footer_text(); ?>
            </div>
        </div>
    </footer>
</body>
</html>