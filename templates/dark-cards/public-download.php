<?php
/*
Public download template for Dark Cards theme
Based on the Dark Cards template with purple and orange gradients
*/
$ld = 'dark_cards_template'; // specify the language domain for this template

// The $file variable is already set by the main download.php
// $can_view and $can_download are already set by the main download.php

// Generate CSRF token
$csrf_token = getCsrfToken();

// Get the logo information
$logo_file_info = generate_logo_url();

$window_title = __('File Download', 'dark_cards_template') . ' - ' . $file->title;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo html_output($window_title . ' - ' . get_option('this_install_title')); ?></title>

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
                    },
                    maxWidth: {
                        'content': '1200px'
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

                    <!-- Document Access Badge -->
                    <div class="px-3 py-1 bg-gradient-purple-orange rounded-full text-white text-sm font-medium">
                        <?php echo __('Document Access', 'dark_cards_template'); ?>
                    </div>
                </div>

                <!-- Navigation Actions -->
                <div class="flex items-center space-x-4">
                    <a href="<?php echo BASE_URI; ?>" class="p-2 text-text-secondary hover:text-text-primary transition-colors duration-200" title="<?php echo __('Home', 'dark_cards_template'); ?>">
                        <span class="material-icons">home</span>
                    </a>

                    <a href="<?php echo BASE_URI; ?>public.php" class="p-2 text-text-secondary hover:text-text-primary transition-colors duration-200" title="<?php echo __('Public Files', 'dark_cards_template'); ?>">
                        <span class="material-icons">public</span>
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

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-gradient-purple-orange rounded-xl flex items-center justify-center">
                    <span class="material-icons text-white text-2xl">download</span>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-text-primary"><?php echo __('Document Download', 'dark_cards_template'); ?></h1>
                    <p class="text-text-secondary"><?php echo __('Download and view document information', 'dark_cards_template'); ?></p>
                </div>
            </div>
        </div>

        <!-- Download Content -->
        <?php if ($can_view): ?>
            <!-- File Information Card -->
            <div class="bg-gradient-card rounded-2xl border border-dark-surface-light overflow-hidden backdrop-blur-sm">
                <!-- Card Header with Gradient -->
                <div class="bg-gradient-purple-orange p-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-white bg-opacity-20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                            <span class="material-icons text-white text-3xl">
                                <?php
                                // Get appropriate Material Icon based on file extension
                                $material_icons = [
                                    'pdf' => 'picture_as_pdf',
                                    'doc' => 'description', 'docx' => 'description',
                                    'xls' => 'grid_on', 'xlsx' => 'grid_on',
                                    'ppt' => 'slideshow', 'pptx' => 'slideshow',
                                    'txt' => 'description',
                                    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image',
                                    'mp3' => 'audiotrack', 'wav' => 'audiotrack',
                                    'mp4' => 'movie', 'avi' => 'movie',
                                    'zip' => 'archive', 'rar' => 'archive',
                                    'html' => 'code', 'css' => 'code', 'js' => 'code'
                                ];
                                echo $material_icons[strtolower($file->extension)] ?? 'insert_drive_file';
                                ?>
                            </span>
                        </div>
                        <div class="flex-1">
                            <h2 class="text-2xl font-bold text-white mb-1"><?php echo html_output($file->filename_original); ?></h2>
                            <?php if ($file->filename_original != $file->title): ?>
                                <p class="text-white text-opacity-80"><?php echo html_output($file->title); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Card Content -->
                <div class="p-6">
                    <!-- File Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <!-- File Size -->
                        <div class="bg-dark-surface rounded-xl p-4 border border-dark-surface-light">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-purple-primary bg-opacity-20 rounded-lg flex items-center justify-center">
                                    <span class="material-icons text-purple-primary">storage</span>
                                </div>
                                <div>
                                    <p class="text-text-muted text-sm font-medium"><?php echo __('File Size', 'dark_cards_template'); ?></p>
                                    <p class="text-text-primary text-lg font-semibold"><?php echo $file->size_formatted; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- File Type -->
                        <div class="bg-dark-surface rounded-xl p-4 border border-dark-surface-light">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-orange-primary bg-opacity-20 rounded-lg flex items-center justify-center">
                                    <span class="material-icons text-orange-primary">label</span>
                                </div>
                                <div>
                                    <p class="text-text-muted text-sm font-medium"><?php echo __('File Type', 'dark_cards_template'); ?></p>
                                    <p class="text-text-primary text-lg font-semibold"><?php echo strtoupper($file->extension); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Dimensions (if image) -->
                        <?php if (file_is_image($file->full_path)): ?>
                            <?php $dimensions = $file->getDimensions(); ?>
                            <?php if (!empty($dimensions)): ?>
                                <div class="bg-dark-surface rounded-xl p-4 border border-dark-surface-light">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-purple-secondary bg-opacity-20 rounded-lg flex items-center justify-center">
                                            <span class="material-icons text-purple-secondary">photo_size_select_large</span>
                                        </div>
                                        <div>
                                            <p class="text-text-muted text-sm font-medium"><?php echo __('Dimensions', 'dark_cards_template'); ?></p>
                                            <p class="text-text-primary text-lg font-semibold"><?php echo $dimensions['width'] . ' × ' . $dimensions['height']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($file->description)): ?>
                        <div class="mb-8">
                            <h3 class="text-xl font-semibold text-text-primary mb-4 flex items-center">
                                <span class="material-icons text-purple-primary mr-2">description</span>
                                <?php echo __('Description', 'dark_cards_template'); ?>
                            </h3>
                            <div class="bg-dark-surface rounded-xl p-4 border border-dark-surface-light">
                                <div class="text-text-secondary leading-relaxed"><?php echo format_description($file->description); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Preview Section -->
                    <?php if (get_option('public_listing_enable_preview') == 1): ?>
                        <div class="mb-8">
                            <?php if (file_is_image($file->full_path)): ?>
                                <?php
                                $thumbnail = make_thumbnail($file->full_path, null, 500, 400);
                                if (!empty($thumbnail['thumbnail']['url'])): ?>
                                    <div class="text-center">
                                        <div class="inline-block bg-dark-surface rounded-xl p-4 border border-dark-surface-light">
                                            <img src="<?php echo $thumbnail['thumbnail']['url']; ?>"
                                                 alt="<?php echo html_output($file->title); ?>"
                                                 class="max-w-full h-auto rounded-lg">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($file->embeddable): ?>
                                <div class="text-center">
                                    <button onclick="showPreview('<?php echo BASE_URI; ?>process.php?do=get_preview&file_id=<?php echo $file->id; ?>')"
                                            class="inline-flex items-center px-6 py-3 bg-dark-surface hover:bg-dark-surface-light border border-dark-surface-light hover:border-purple-primary rounded-xl text-text-primary transition-all duration-300 transform hover:scale-105">
                                        <span class="material-icons mr-2">visibility</span>
                                        <?php echo __('Preview Document', 'dark_cards_template'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Download Actions -->
                    <div class="border-t border-dark-surface-light pt-6">
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <?php if ($can_download): ?>
                                <a href="<?php echo $file->public_url . '&download'; ?>"
                                   class="inline-flex items-center justify-center px-8 py-4 bg-gradient-purple-orange text-white font-semibold rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl">
                                    <span class="material-icons mr-2">download</span>
                                    <?php echo __('Download Document', 'dark_cards_template'); ?>
                                </a>
                            <?php else: ?>
                                <div class="inline-flex items-center justify-center px-8 py-4 bg-red-600 bg-opacity-20 text-red-400 font-semibold rounded-xl border border-red-600 border-opacity-30">
                                    <span class="material-icons mr-2">block</span>
                                    <?php echo __('Download Not Available', 'dark_cards_template'); ?>
                                </div>
                            <?php endif; ?>

                            <a href="<?php echo BASE_URI; ?>public.php"
                               class="inline-flex items-center justify-center px-8 py-4 bg-dark-surface hover:bg-dark-surface-light border border-dark-surface-light hover:border-text-muted text-text-primary font-semibold rounded-xl transition-all duration-300 transform hover:scale-105">
                                <span class="material-icons mr-2">arrow_back</span>
                                <?php echo __('Back to Documents', 'dark_cards_template'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Access Denied -->
            <div class="bg-gradient-card rounded-2xl border border-dark-surface-light p-8 text-center backdrop-blur-sm">
                <div class="w-20 h-20 bg-red-600 bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <span class="material-icons text-red-400 text-4xl">block</span>
                </div>
                <h2 class="text-2xl font-bold text-text-primary mb-4"><?php echo __('Access Denied', 'dark_cards_template'); ?></h2>
                <p class="text-text-secondary mb-8"><?php echo __('You do not have permission to view this document.', 'dark_cards_template'); ?></p>
                <a href="<?php echo BASE_URI; ?>public.php"
                   class="inline-flex items-center px-6 py-3 bg-gradient-purple-orange text-white font-semibold rounded-xl transition-all duration-300 transform hover:scale-105">
                    <span class="material-icons mr-2">arrow_back</span>
                    <?php echo __('Back to Documents', 'dark_cards_template'); ?>
                </a>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="mt-16 py-8 border-t border-dark-surface-light">
        <div class="max-w-content mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="text-text-muted text-sm">
                <?php render_footer_text(); ?>
            </div>
        </div>
    </footer>

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="closePreview()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-gradient-card rounded-2xl border border-dark-surface-light overflow-hidden max-w-4xl w-full max-h-full backdrop-blur-sm">
                <div class="flex items-center justify-between p-6 border-b border-dark-surface-light">
                    <h3 class="text-xl font-semibold text-text-primary flex items-center">
                        <span class="material-icons text-purple-primary mr-2">visibility</span>
                        <?php echo __('Document Preview', 'dark_cards_template'); ?>
                    </h3>
                    <button onclick="closePreview()" class="p-2 hover:bg-dark-surface-light rounded-lg transition-colors duration-200">
                        <span class="material-icons text-text-muted hover:text-text-primary">close</span>
                    </button>
                </div>
                <div id="previewContent" class="p-6 overflow-auto max-h-96">
                    <!-- Preview content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function showPreview(url) {
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewContent');

            content.innerHTML = `
                <div class="text-center py-8">
                    <div class="w-12 h-12 bg-gradient-purple-orange rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
                        <span class="material-icons text-white">hourglass_empty</span>
                    </div>
                    <p class="text-text-secondary">Loading preview...</p>
                </div>
            `;
            modal.classList.remove('hidden');

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    let previewHtml = '';

                    switch(data.type) {
                        case 'image':
                            previewHtml = `<div class="text-center"><img src="${data.file_url}" alt="${data.name}" class="max-w-full h-auto rounded-lg"></div>`;
                            break;
                        case 'video':
                            previewHtml = `<div class="text-center"><video controls class="max-w-full h-auto rounded-lg">
                                            <source src="${data.file_url}" type="${data.mime_type}">
                                            Your browser does not support video playback.
                                          </video></div>`;
                            break;
                        case 'audio':
                            previewHtml = `<div class="text-center"><audio controls class="w-full">
                                            <source src="${data.file_url}" type="${data.mime_type}">
                                            Your browser does not support audio playback.
                                          </audio></div>`;
                            break;
                        case 'pdf':
                            previewHtml = `<iframe src="${data.file_url}" class="w-full h-96 rounded-lg border border-dark-surface-light"></iframe>`;
                            break;
                        default:
                            previewHtml = `
                                <div class="text-center py-8">
                                    <div class="w-16 h-16 bg-dark-surface rounded-xl flex items-center justify-center mx-auto mb-4">
                                        <span class="material-icons text-text-muted text-2xl">description</span>
                                    </div>
                                    <p class="text-text-secondary">Preview not available for this document type.</p>
                                </div>
                            `;
                    }

                    content.innerHTML = previewHtml;
                })
                .catch(error => {
                    content.innerHTML = `
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-red-600 bg-opacity-20 rounded-xl flex items-center justify-center mx-auto mb-4">
                                <span class="material-icons text-red-400 text-2xl">error</span>
                            </div>
                            <p class="text-red-400">Error loading preview.</p>
                        </div>
                    `;
                });
        }

        function closePreview() {
            document.getElementById('previewModal').classList.add('hidden');
            document.getElementById('previewContent').innerHTML = '';
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreview();
            }
        });
    </script>

    <!-- Custom JavaScript -->
    <script src="<?php echo BASE_URI; ?>templates/dark-cards/js/dark-cards.js"></script>

    <?php render_custom_assets('body_bottom'); ?>
</body>
</html>