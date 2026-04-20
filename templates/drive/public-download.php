<?php
/*
Public download template for Google Drive theme
*/
$ld = 'drive_template';

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

$page_title = __('File Download', 'drive_template') . ' - ' . $file->title;

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

<body class="h-full bg-google-gray-50 dark:bg-google-gray-900 font-sans">
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
            </div>

            <!-- Right Side: User Account or Login -->
            <div class="flex items-center space-x-4">
                <!-- Login Link (icon only to match public.php) -->
                <a href="<?php echo BASE_URI; ?>index.php" class="w-8 h-8 bg-google-green rounded-full flex items-center justify-center text-white text-sm font-medium hover:shadow-md transition-shadow">
                    <span class="material-icons text-sm">person</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 py-8">
        <div class="max-w-2xl mx-auto px-6">
            <?php if ($can_view): ?>
                <!-- File Information Card -->
                <div class="bg-white dark:bg-google-gray-800 rounded-lg shadow border border-google-gray-200 dark:border-google-gray-700 overflow-hidden">
                    <!-- Card Header -->
                    <div class="px-6 py-4 bg-google-gray-50 dark:bg-google-gray-800 border-b border-google-gray-200 dark:border-google-gray-700">
                        <div class="flex items-center">
                            <span class="material-icons text-google-gray-500 dark:text-google-gray-400 mr-3">description</span>
                            <h2 class="text-lg font-medium text-google-gray-900 dark:text-google-gray-100">
                                <?php _e('File Information', 'drive_template'); ?>
                            </h2>
                        </div>
                    </div>

                    <!-- Card Content -->
                    <div class="p-6">
                        <!-- File Name -->
                        <div class="mb-6">
                            <h3 class="text-xl font-medium text-google-gray-900 dark:text-google-gray-100 mb-2">
                                <?php echo html_output($file->filename_original); ?>
                            </h3>
                            <?php if ($file->filename_original != $file->title): ?>
                                <p class="text-lg text-google-gray-600 dark:text-google-gray-400">
                                    <?php echo html_output($file->title); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- File Details -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <!-- File Size -->
                            <div class="flex items-center">
                                <span class="material-icons text-google-gray-500 dark:text-google-gray-400 mr-3">storage</span>
                                <div>
                                    <p class="text-sm font-medium text-google-gray-900 dark:text-google-gray-100">
                                        <?php _e('File Size', 'drive_template'); ?>
                                    </p>
                                    <p class="text-sm text-google-gray-600 dark:text-google-gray-400">
                                        <?php echo $file->size_formatted; ?>
                                        <?php
                                        if (file_is_image($file->full_path)) {
                                            $dimensions = $file->getDimensions();
                                            if (!empty($dimensions)) {
                                                echo ' • ' . $dimensions['width'] . ' × ' . $dimensions['height'] . ' px';
                                            }
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>

                            <!-- File Type -->
                            <div class="flex items-center">
                                <span class="material-icons text-google-gray-500 dark:text-google-gray-400 mr-3">category</span>
                                <div>
                                    <p class="text-sm font-medium text-google-gray-900 dark:text-google-gray-100">
                                        <?php _e('File Type', 'drive_template'); ?>
                                    </p>
                                    <p class="text-sm text-google-gray-600 dark:text-google-gray-400">
                                        <?php echo strtoupper($file->extension); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <?php if (!empty($file->description)): ?>
                            <div class="mb-6">
                                <h4 class="text-sm font-medium text-google-gray-900 dark:text-google-gray-100 mb-2">
                                    <?php _e('Description', 'drive_template'); ?>
                                </h4>
                                <p class="text-sm text-google-gray-600 dark:text-google-gray-400">
                                    <?php echo format_description($file->description); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <!-- Preview Section -->
                        <?php if (get_option('public_listing_enable_preview') == 1): ?>
                            <div class="mb-6">
                                <?php if (file_is_image($file->full_path)): ?>
                                    <?php
                                    $thumbnail = make_thumbnail($file->full_path, null, 300, 300);
                                    if (!empty($thumbnail['thumbnail']['url'])): ?>
                                        <div class="text-center">
                                            <img src="<?php echo $thumbnail['thumbnail']['url']; ?>"
                                                 alt="<?php echo html_output($file->title); ?>"
                                                 class="max-w-full h-auto rounded-lg border border-google-gray-200 dark:border-google-gray-600 mx-auto">
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($file->embeddable): ?>
                                    <div class="text-center">
                                        <button onclick="showPreview('<?php echo BASE_URI; ?>process.php?do=get_preview&file_id=<?php echo $file->id; ?>')"
                                                class="inline-flex items-center px-4 py-2 border border-google-gray-300 dark:border-google-gray-600 rounded-md shadow-sm bg-white dark:bg-google-gray-800 text-sm font-medium text-google-gray-700 dark:text-google-gray-200 hover:bg-google-gray-50 dark:hover:bg-google-gray-700 transition-colors">
                                            <span class="material-icons text-sm mr-2">visibility</span>
                                            <?php _e('Preview', 'drive_template'); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Download Action -->
                        <?php if ($can_download): ?>
                            <div class="flex justify-center">
                                <a href="<?php echo $file->public_url . '&download'; ?>"
                                   class="inline-flex items-center px-6 py-3 bg-google-green hover:bg-google-green-dark text-white font-medium rounded-md shadow-sm transition-colors">
                                    <span class="material-icons mr-2">download</span>
                                    <?php _e('Download File', 'drive_template'); ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center">
                                <p class="text-red-600 dark:text-red-400 font-medium">
                                    <?php _e('Download not available', 'drive_template'); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Back Link -->
                <div class="mt-6 text-center">
                    <a href="<?php echo BASE_URI; ?>public.php"
                       class="inline-flex items-center text-sm text-google-green hover:text-google-green-dark font-medium">
                        <span class="material-icons text-sm mr-1">arrow_back</span>
                        <?php _e('Back to Public Files', 'drive_template'); ?>
                    </a>
                </div>

            <?php else: ?>
                <!-- Access Denied -->
                <div class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-800 rounded-lg p-6 text-center">
                    <span class="material-icons text-red-600 dark:text-red-400 text-4xl mb-4">block</span>
                    <h2 class="text-lg font-medium text-red-800 dark:text-red-200 mb-2">
                        <?php _e('Access Denied', 'drive_template'); ?>
                    </h2>
                    <p class="text-red-600 dark:text-red-400">
                        <?php _e('You do not have permission to view this file.', 'drive_template'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </main>

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

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closePreview()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-google-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-full overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b border-google-gray-200 dark:border-google-gray-700">
                    <h3 class="text-lg font-medium text-google-gray-900 dark:text-google-gray-100">
                        <?php _e('File Preview', 'drive_template'); ?>
                    </h3>
                    <button onclick="closePreview()" class="p-2 hover:bg-google-gray-100 dark:hover:bg-google-gray-700 rounded-md">
                        <span class="material-icons text-google-gray-500 dark:text-google-gray-400">close</span>
                    </button>
                </div>
                <div id="previewContent" class="p-4 overflow-auto max-h-96">
                    <!-- Preview content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function showPreview(url) {
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewContent');

            content.innerHTML = '<div class="text-center py-8"><span class="material-icons animate-spin text-google-green text-2xl">refresh</span></div>';
            modal.classList.remove('hidden');

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    let previewHtml = '';

                    switch(data.type) {
                        case 'image':
                            previewHtml = `<img src="${data.file_url}" alt="${data.name}" class="max-w-full h-auto mx-auto rounded-lg">`;
                            break;
                        case 'video':
                            previewHtml = `<video controls class="max-w-full h-auto mx-auto rounded-lg">
                                            <source src="${data.file_url}" type="${data.mime_type}">
                                            Your browser does not support video playback.
                                          </video>`;
                            break;
                        case 'audio':
                            previewHtml = `<audio controls class="w-full">
                                            <source src="${data.file_url}" type="${data.mime_type}">
                                            Your browser does not support audio playback.
                                          </audio>`;
                            break;
                        case 'pdf':
                            previewHtml = `<iframe src="${data.file_url}" class="w-full h-96 border border-google-gray-200 dark:border-google-gray-600 rounded-lg"></iframe>`;
                            break;
                        default:
                            previewHtml = `<div class="text-center py-8">
                                            <span class="material-icons text-google-gray-400 text-4xl mb-4">description</span>
                                            <p class="text-google-gray-600 dark:text-google-gray-400">Preview not available for this file type.</p>
                                          </div>`;
                    }

                    content.innerHTML = previewHtml;
                })
                .catch(error => {
                    content.innerHTML = '<div class="text-center py-8 text-red-600 dark:text-red-400">Error loading preview.</div>';
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
</body>
</html>