<?php
/*
Public template for Dark Cards theme
Based on the Dark Cards template with purple and orange gradients
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

// The $files variable is already set by the main public.php
// $mode is already set by the main public.php
// $group_props is already set if viewing a group

// File count for pagination - get from the pagination array
$count = isset($files['pagination']['total']) ? $files['pagination']['total'] : 0;
$count_for_pagination = $count;

// Pagination page
$pagination_page = (isset($_GET["page"])) ? $_GET["page"] : 1;

// Generate CSRF token
$csrf_token = getCsrfToken();

// Get the logo information
$logo_file_info = generate_logo_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo html_output(get_option('this_install_title')); ?> - <?php echo __('Public Files', 'dark_cards_template'); ?></title>
    
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

        window.base_url = '<?php echo BASE_URI; ?>';
        window.isPublicContext = true;
    </script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URI; ?>templates/dark-cards/css/dark-cards.css">

    <?php render_custom_assets('head'); ?>
</head>
<body class="bg-dark-bg text-text-primary font-rounded">
    <?php render_custom_assets('body_top'); ?>
    
    <!-- Header -->
    <header class="bg-gradient-dark border-b border-dark-surface-light sticky top-0 z-30 backdrop-blur-sm">
        <div class="max-w-content mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-4">
                    <?php if ($logo_file_info && $logo_file_info['exists']): ?>
                        <img src="<?php echo $logo_file_info['url']; ?>" alt="<?php echo get_option('this_install_title'); ?>" class="h-10 w-auto max-w-48 branding-logo">
                    <?php else: ?>
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gradient-purple-orange rounded-xl flex items-center justify-center">
                                <span class="material-icons text-white text-xl">public</span>
                            </div>
                            <h1 class="text-xl font-bold text-text-primary"><?php echo html_output(get_option('this_install_title')); ?></h1>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Public Access Badge -->
                    <div class="px-3 py-1 bg-gradient-purple-orange rounded-full text-white text-sm font-medium">
                        <?php echo __('Public Access', 'dark_cards_template'); ?>
                    </div>
                </div>
                
                <!-- Navigation Actions -->
                <div class="flex items-center space-x-4">
                    <a href="<?php echo BASE_URI; ?>" class="p-2 text-text-secondary hover:text-text-primary transition-colors duration-200" title="<?php echo __('Home', 'dark_cards_template'); ?>">
                        <span class="material-icons">home</span>
                    </a>
                    
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <!-- Logged in user actions -->
                        <a href="<?php echo BASE_URI; ?>my_files/index.php" class="p-2 text-text-secondary hover:text-text-primary transition-colors duration-200" title="<?php echo __('Private Files', 'dark_cards_template'); ?>">
                            <span class="material-icons">folder_shared</span>
                        </a>
                        <a href="<?php echo BASE_URI; ?>process.php?do=logout" class="p-2 text-text-secondary hover:text-text-primary transition-colors duration-200" title="<?php echo __('Logout', 'dark_cards_template'); ?>">
                            <span class="material-icons">logout</span>
                        </a>
                    <?php else: ?>
                        <!-- Guest user actions -->
                        <a href="<?php echo BASE_URI; ?>index.php" class="p-2 text-text-secondary hover:text-text-primary transition-colors duration-200" title="<?php echo __('Login', 'dark_cards_template'); ?>">
                            <span class="material-icons">login</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-content mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Groups Navigation -->
        <?php 
        $groups = get_groups(['public' => true]);
        if (!empty($groups) && $mode !== 'group'): ?>
        <div class="bg-gradient-card rounded-2xl border border-dark-surface-light p-6 mb-8 backdrop-blur-sm">
            <h3 class="text-xl font-bold text-text-primary mb-6 flex items-center">
                <span class="material-icons text-purple-primary mr-3 text-2xl">group_work</span>
                <?php echo __('Browse by Group', 'dark_cards_template'); ?>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($groups as $group): 
                    $group_file_count = count_public_files_in_group($group['id']); ?>
                    <a href="<?php echo BASE_URI; ?>public.php?group=<?php echo $group['id']; ?>&token=<?php echo $group['public_token']; ?>" 
                       class="group block p-4 bg-dark-surface hover:bg-dark-surface-light rounded-xl border border-dark-surface-light hover:border-purple-primary transition-all duration-300 transform hover:scale-105">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-purple-orange rounded-lg flex items-center justify-center">
                                    <span class="material-icons text-white">folder</span>
                                </div>
                                <div>
                                    <p class="font-semibold text-text-primary group-hover:text-purple-secondary"><?php echo html_output($group['name']); ?></p>
                                    <p class="text-xs text-text-muted"><?php echo $group_file_count; ?> <?php echo __('files', 'dark_cards_template'); ?></p>
                                </div>
                            </div>
                            <span class="material-icons text-text-muted group-hover:text-purple-primary">arrow_forward</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Group Breadcrumb (when viewing a group) -->
        <?php if ($mode === 'group' && isset($group_props)): ?>
        <div class="bg-gradient-card rounded-2xl border border-dark-surface-light p-4 mb-8">
            <div class="flex items-center space-x-3 text-sm">
                <a href="<?php echo BASE_URI; ?>public.php" class="flex items-center text-purple-primary hover:text-purple-secondary transition-colors duration-200">
                    <span class="material-icons mr-1 text-lg">home</span>
                    <?php echo __('All Public Files', 'dark_cards_template'); ?>
                </a>
                <span class="material-icons text-text-muted">chevron_right</span>
                <span class="flex items-center text-text-primary font-medium">
                    <span class="material-icons mr-1 text-lg">folder</span>
                    <?php echo html_output($group_props['name']); ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Folder Navigation -->
        <?php
            $current_url = get_form_action_with_existing_parameters('public.php');
            $current_folder = (isset($_GET['folder_id'])) ? (int)$_GET['folder_id'] : null;
            include_once LAYOUT_DIR . DS . 'breadcrumbs.php';
            include_once LAYOUT_DIR . DS . 'folders-nav.php';
        ?>

        <!-- Search and Filters -->
        <?php if ($count > 0 || isset($_GET['search'])): ?>
        <div class="bg-gradient-card rounded-2xl border border-dark-surface-light p-6 mb-8">
            <form action="<?php echo BASE_URI; ?>public.php" method="get" class="flex flex-col lg:flex-row gap-4">
                <?php if (isset($_GET['group'])): ?>
                    <input type="hidden" name="group" value="<?php echo htmlspecialchars($_GET['group']); ?>">
                <?php endif; ?>
                <?php if (isset($_GET['token'])): ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                <?php endif; ?>
                
                <div class="flex-1">
                    <input type="text" 
                           name="search" 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
                           placeholder="<?php echo __('Search files...', 'dark_cards_template'); ?>"
                           class="w-full px-4 py-3 bg-dark-surface border border-dark-surface-light text-text-primary placeholder-text-muted rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-primary focus:border-transparent transition-all duration-200">
                </div>
                
                <button type="submit" class="px-6 py-3 bg-gradient-purple-orange text-white font-semibold rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-200 flex items-center space-x-2">
                    <span class="material-icons">search</span>
                    <span><?php echo __('Search', 'dark_cards_template'); ?></span>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Files Grid -->
        <?php if (isset($count) && $count > 0 && isset($files['files_ids']) && !empty($files['files_ids'])) { ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            <?php foreach ($files['files_ids'] as $file_id): 
                $file = new \ProjectSend\Classes\Files($file_id);
                
                // Skip expired files in public view
                if ($file->expired && get_option('expired_files_hide') == '1') {
                    continue;
                }
                
                // File properties
                $extension_lower = strtolower($file->extension);
                $is_image = $file->isImage();
                
                // File icon
                $icon_map = [
                    'pdf' => 'picture_as_pdf',
                    'doc' => 'article', 'docx' => 'article',
                    'xls' => 'table_chart', 'xlsx' => 'table_chart',
                    'ppt' => 'slideshow', 'pptx' => 'slideshow',
                    'zip' => 'archive', 'rar' => 'archive', '7z' => 'archive',
                    'mp4' => 'movie', 'avi' => 'movie', 'mkv' => 'movie',
                    'mp3' => 'audiotrack', 'wav' => 'audiotrack',
                    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image'
                ];
                $file_icon = isset($icon_map[$extension_lower]) ? $icon_map[$extension_lower] : 'insert_drive_file';
                
                // Thumbnail or icon background
                $thumb_bg = '';
                if ($is_image && !$file->expired) {
                    $thumbnail = make_thumbnail($file->full_path, null, TEMPLATE_THUMBNAILS_WIDTH, TEMPLATE_THUMBNAILS_HEIGHT);
                    if ($thumbnail['thumbnail']['url']) {
                        $thumb_bg = "background-image: url('".$thumbnail['thumbnail']['url']."'); background-size: cover; background-position: center;";
                    }
                }
                ?>
                
                <div class="file-card group bg-gradient-card rounded-2xl border border-dark-surface-light overflow-hidden hover:border-purple-primary transition-all duration-300 <?php echo $file->expired ? 'opacity-60' : ''; ?>" 
                     data-file-id="<?php echo $file->id; ?>"
                     data-expired="<?php echo $file->expired ? 'true' : 'false'; ?>">
                    
                    <!-- File Preview/Icon -->
                    <div class="relative h-32 bg-dark-surface overflow-hidden" <?php echo $thumb_bg ? 'style="'.$thumb_bg.'"' : ''; ?>>
                        <?php if (!$thumb_bg): ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <span class="material-icons text-purple-primary text-4xl"><?php echo $file_icon; ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- File Extension Badge -->
                        <div class="absolute top-2 right-2 px-2 py-1 bg-black bg-opacity-60 text-white text-xs font-medium rounded-md uppercase">
                            <?php echo $file->extension; ?>
                        </div>
                        
                        <?php if ($file->expired): ?>
                            <div class="absolute inset-0 bg-red-500 bg-opacity-20 flex items-center justify-center">
                                <span class="material-icons text-red-400 text-2xl">schedule</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- File Info -->
                    <div class="p-4">
                        <h3 class="font-semibold text-text-primary mb-2 line-clamp-2 text-sm" title="<?php echo html_output($file->title); ?>">
                            <?php echo html_output($file->title); ?>
                        </h3>
                        
                        <?php if (!empty($file->description)): ?>
                            <p class="text-text-muted text-xs mb-3 line-clamp-2" title="<?php echo html_output($file->description); ?>">
                                <?php echo format_description($file->description); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="flex items-center justify-between text-xs text-text-muted mb-3">
                            <span><?php echo $file->size_formatted; ?></span>
                            <span><?php echo format_date($file->uploaded_date); ?></span>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <!-- View Details Button -->
                            <a href="<?php echo BASE_URI; ?>download.php?id=<?php echo $file->id; ?>&token=<?php echo $file->public_token; ?>" 
                               class="flex-1 flex items-center justify-center px-3 py-2 border border-purple-primary text-purple-primary hover:bg-purple-primary hover:text-white rounded-lg text-xs font-semibold transition-all duration-200 transform hover:scale-105">
                                <span class="material-icons mr-1 text-sm">visibility</span>
                                <span><?php echo __('View', 'dark_cards_template'); ?></span>
                            </a>
                            <!-- Download Button -->
                            <a href="<?php echo $file->download_link; ?>" 
                               class="flex-1 flex items-center justify-center px-3 py-2 bg-gradient-purple-orange hover:shadow-lg text-white rounded-lg text-xs font-semibold transition-all duration-200 transform hover:scale-105">
                                <span class="material-icons mr-1 text-sm">download</span>
                                <span><?php echo __('Download', 'dark_cards_template'); ?></span>
                            </a>
                        </div>
                    </div>
                </div>
                
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php 
        if (isset($files['pagination']) && isset($files['pagination']['total']) && $files['pagination']['total'] > TEMPLATE_RESULTS_PER_PAGE) {
            $pagination = new \ProjectSend\Classes\Layout\Pagination;
            echo '<div class="mt-12 flex justify-center">';
            echo '<div class="pagination-wrapper">';
            echo $pagination->make([
                'link' => 'public.php',
                'current' => $pagination_page,
                'item_count' => $files['pagination']['total'],
                'items_per_page' => TEMPLATE_RESULTS_PER_PAGE,
            ]);
            echo '</div>';
            echo '</div>';
        }
        ?>
        
        <?php } else { ?>
        <!-- Empty State -->
        <div class="empty-state text-center py-16">
            <div class="w-24 h-24 bg-gradient-purple-orange rounded-full flex items-center justify-center mx-auto mb-6">
                <span class="material-icons text-white text-4xl">folder_open</span>
            </div>
            <h3 class="text-xl font-bold text-text-primary mb-4">
                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                    <?php echo __('No files found for your search', 'dark_cards_template'); ?>
                <?php else: ?>
                    <?php echo __('No files available', 'dark_cards_template'); ?>
                <?php endif; ?>
            </h3>
            <p class="text-text-muted max-w-md mx-auto">
                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                    <?php echo __('Try adjusting your search terms or browse available groups above.', 'dark_cards_template'); ?>
                <?php else: ?>
                    <?php echo __('There are no public files available at this time.', 'dark_cards_template'); ?>
                <?php endif; ?>
            </p>
            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                <a href="<?php echo BASE_URI; ?>public.php" 
                   class="inline-flex items-center px-6 py-3 bg-gradient-purple-orange text-white font-semibold rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-200 mt-6">
                    <span class="material-icons mr-2">clear</span>
                    <?php echo __('Clear Search', 'dark_cards_template'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php } ?>
        
    </main>

    <!-- Footer -->
    <footer class="mt-16 py-8 border-t border-dark-surface-light">
        <div class="max-w-content mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="text-text-muted text-sm">
                <?php render_footer_text(); ?>
            </div>
        </div>
    </footer>

    <!-- Custom JavaScript -->
    <script src="<?php echo BASE_URI; ?>templates/dark-cards/js/dark-cards.js"></script>
    
    <?php render_custom_assets('body_bottom'); ?>
</body>
</html>