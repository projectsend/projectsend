/**
 * Google Drive Template JavaScript
 * Enhanced functionality for Google Drive list view
 */

$(document).ready(function() {
    // Initialize theme
    initializeTheme();
    
    // Setup search functionality
    setupSearch();
    
    // Setup file selection and bulk actions
    setupFileSelection();
    
    // Setup view controls
    setupViewControls();
    
    // Setup file info panel
    setupFileInfoPanel();
    
    // Setup accessibility features
    setupAccessibility();
});

/**
 * Theme Management
 */
function initializeTheme() {
    const themeToggle = document.getElementById('theme-toggle');
    const darkIcon = document.getElementById('theme-toggle-dark-icon');
    const lightIcon = document.getElementById('theme-toggle-light-icon');

    // Check for saved theme preference or default to system preference
    const savedTheme = localStorage.getItem('color-theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
        document.documentElement.classList.add('dark');
        if (lightIcon) lightIcon.classList.remove('hidden');
        if (darkIcon) darkIcon.classList.add('hidden');
    } else {
        if (darkIcon) darkIcon.classList.remove('hidden');
        if (lightIcon) lightIcon.classList.add('hidden');
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            toggleTheme();
        });
    }
}

function toggleTheme() {
    const isDark = document.documentElement.classList.contains('dark');
    const darkIcon = document.getElementById('theme-toggle-dark-icon');
    const lightIcon = document.getElementById('theme-toggle-light-icon');
    
    if (isDark) {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('color-theme', 'light');
        if (darkIcon) darkIcon.classList.remove('hidden');
        if (lightIcon) lightIcon.classList.add('hidden');
    } else {
        document.documentElement.classList.add('dark');
        localStorage.setItem('color-theme', 'dark');
        if (lightIcon) lightIcon.classList.remove('hidden');
        if (darkIcon) darkIcon.classList.add('hidden');
    }
}

/**
 * Search Enhancement
 */
function setupSearch() {
    const searchForm = $('#search-form');
    const searchInput = $('input[name="search"]');
    const categorySelect = $('#category-filter');
    
    // Auto-submit on category change
    if (categorySelect.length) {
        categorySelect.on('change', function() {
            var category = $(this).val();
            var currentUrl = new URL(window.location);
            if (category && category !== 'all') {
                currentUrl.searchParams.set('category', category);
            } else {
                currentUrl.searchParams.delete('category');
            }
            currentUrl.searchParams.delete('page'); // Reset to first page
            window.location.href = currentUrl.toString();
        });
    }
    
    // Search form submission
    if (searchForm.length) {
        searchForm.on('submit', function(e) {
            var searchTerm = searchInput.val().trim();
            if (searchTerm === '') {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Files per page functionality
    $('#files-per-page').on('change', function() {
        var perPage = $(this).val();
        var currentUrl = new URL(window.location);
        currentUrl.searchParams.set('results_per_page', perPage);
        currentUrl.searchParams.delete('page'); // Reset to first page
        window.location.href = currentUrl.toString();
    });
}

/**
 * File Selection and Bulk Actions
 */
function setupFileSelection() {
    var total_files = 0;
    var selected_files = 0;
    var files_array = [];

    // Initialize file count
    $('.file-row').each(function() {
        total_files++;
    });

    // Single file selection via checkbox
    $('.file-checkbox').on('change', function() {
        var file_id = $(this).data('file-id');
        var row = $(this).closest('.file-row');
        
        if ($(this).is(':checked')) {
            selected_files++;
            files_array.push(file_id);
            row.addClass('selected bg-google-green-50 dark:bg-google-green-900/20');
            row.find('.selection-indicator').removeClass('hidden');
        } else {
            selected_files--;
            var index = files_array.indexOf(file_id);
            if (index > -1) {
                files_array.splice(index, 1);
            }
            row.removeClass('selected bg-google-green-50 dark:bg-google-green-900/20');
            row.find('.selection-indicator').addClass('hidden');
        }
        
        updateSelectionUI();
    });

    // Click anywhere on the row to select (except on links and buttons)
    $('.file-row').on('click', function(e) {
        if (!$(e.target).is('a, button, input[type="checkbox"], .material-icons') && 
            !$(e.target).closest('a, button').length) {
            var checkbox = $(this).find('.file-checkbox');
            if (checkbox.length) {
                checkbox.prop('checked', !checkbox.is(':checked')).trigger('change');
            }
        }
    });

    // Select all functionality
    $('#select_all').on('change', function() {
        var isChecked = $(this).is(':checked');
        
        $('.file-checkbox').each(function() {
            var file_id = $(this).data('file-id');
            var row = $(this).closest('.file-row');
            
            $(this).prop('checked', isChecked);
            
            if (isChecked) {
                if (files_array.indexOf(file_id) === -1) {
                    files_array.push(file_id);
                }
                row.addClass('selected bg-google-green-50 dark:bg-google-green-900/20');
                row.find('.selection-indicator').removeClass('hidden');
            } else {
                var index = files_array.indexOf(file_id);
                if (index > -1) {
                    files_array.splice(index, 1);
                }
                row.removeClass('selected bg-google-green-50 dark:bg-google-green-900/20');
                row.find('.selection-indicator').addClass('hidden');
            }
        });
        
        selected_files = isChecked ? total_files : 0;
        updateSelectionUI();
    });

    function updateSelectionUI() {
        var selectionCount = $('#selection-count');
        var bulkActions = $('#bulk-actions');
        
        if (selected_files > 0) {
            selectionCount.text(selected_files + ' selected');
            if (bulkActions.length) {
                bulkActions.removeClass('hidden');
            }
        } else {
            selectionCount.text('');
            if (bulkActions.length) {
                bulkActions.addClass('hidden');
            }
        }
        
        // Update select all checkbox state
        if (selected_files === 0) {
            $('#select_all').prop('indeterminate', false).prop('checked', false);
        } else if (selected_files === total_files) {
            $('#select_all').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#select_all').prop('indeterminate', true);
        }
    }

    // Download ZIP functionality
    $('#download-zip-btn').on('click', function(e) {
        e.preventDefault();
        
        if (files_array.length === 0) {
            alert('Please select files to download.');
            return;
        }
        
        // Prepare the checkbox data like the system expects
        var checkboxes = [];
        $.each(files_array, function(index, file_id) {
            checkboxes.push({name: 'files[]', value: file_id});
        });
        
        // First get the file IDs in the correct format
        $.ajax({
            method: 'GET',
            url: window.base_url + 'process.php',
            data: { do: "return_files_ids", files: checkboxes }
        }).done(function(rsp) {
            // Create iframe for download
            var url = window.base_url + 'process.php?do=download_zip&files=' + rsp;
            var iframe = $('<iframe>', {
                style: 'display:none',
                src: url
            });
            $('body').append(iframe);
            
            // Remove iframe after a delay
            setTimeout(function() {
                iframe.remove();
            }, 5000);
        }).fail(function() {
            alert('Error preparing files for download. Please try again.');
        });
    });

    // Hover effects for file rows
    $('.file-row').hover(
        function() {
            $(this).find('.row-actions').removeClass('opacity-0').addClass('opacity-100');
        },
        function() {
            if (!$(this).find('.file-checkbox').is(':checked')) {
                $(this).find('.row-actions').removeClass('opacity-100').addClass('opacity-0');
            }
        }
    );
}

/**
 * View Controls
 */
function setupViewControls() {
    // Restore saved view preference
    var savedView = localStorage.getItem('file-view-preference') || 'list';
    var filesContainer = $('#files-container');
    
    if (savedView === 'grid') {
        $('#view-list').removeClass('bg-white dark:bg-google-gray-700 shadow-sm').addClass('opacity-50');
        $('#view-list .material-icons').removeClass('text-google-gray-600 dark:text-google-gray-400').addClass('text-google-gray-400');
        
        $('#view-grid').addClass('bg-white dark:bg-google-gray-700 shadow-sm').removeClass('opacity-50');
        $('#view-grid .material-icons').addClass('text-google-gray-600 dark:text-google-gray-400').removeClass('text-google-gray-400');
        
        filesContainer.addClass('grid-view');
    } else {
        filesContainer.addClass('list-view');
    }
    
    // View toggle functionality (list/grid)
    $('.view-toggle').on('click', function(e) {
        e.preventDefault();
        
        // Remove active state from all buttons
        $('.view-toggle').removeClass('bg-white dark:bg-google-gray-700 shadow-sm').addClass('opacity-50');
        $('.view-toggle .material-icons').removeClass('text-google-gray-600 dark:text-google-gray-400').addClass('text-google-gray-400');
        
        // Add active state to clicked button
        $(this).addClass('bg-white dark:bg-google-gray-700 shadow-sm').removeClass('opacity-50');
        $(this).find('.material-icons').addClass('text-google-gray-600 dark:text-google-gray-400').removeClass('text-google-gray-400');
        
        // Get the view type
        var viewType = $(this).data('view');
        
        // Store preference
        localStorage.setItem('file-view-preference', viewType);
        
        // Switch view layout
        var filesContainer = $('#files-container');
        if (viewType === 'grid') {
            filesContainer.removeClass('list-view').addClass('grid-view');
        } else {
            filesContainer.removeClass('grid-view').addClass('list-view');
        }
    });
    
    // Sort functionality (if implemented in backend)
    $('.sort-header').on('click', function() {
        var sortBy = $(this).data('sort');
        if (sortBy) {
            var currentUrl = new URL(window.location);
            var currentSort = currentUrl.searchParams.get('sort');
            var currentDirection = currentUrl.searchParams.get('direction') || 'asc';
            
            // Toggle direction if same column, otherwise default to asc
            if (currentSort === sortBy) {
                currentDirection = currentDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentDirection = 'asc';
            }
            
            currentUrl.searchParams.set('sort', sortBy);
            currentUrl.searchParams.set('direction', currentDirection);
            window.location.href = currentUrl.toString();
        }
    });
}

/**
 * Accessibility Features
 */
function setupAccessibility() {
    // Add ARIA labels where needed
    addAriaLabels();
    
    // Handle reduced motion preference
    handleReducedMotion();
    
    // Keyboard navigation for file rows
    $('.file-row').on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            var checkbox = $(this).find('.file-checkbox');
            if (checkbox.length) {
                checkbox.prop('checked', !checkbox.is(':checked')).trigger('change');
            }
        }
    });
    
    // Add tabindex to file rows for keyboard navigation
    $('.file-row').attr('tabindex', '0');
}

function addAriaLabels() {
    // Add aria-labels to icon-only buttons
    $('button:not([aria-label])').each(function() {
        var $button = $(this);
        var icon = $button.find('.material-icons').text();
        
        if (icon.includes('dark_mode') || icon.includes('light_mode')) {
            $button.attr('aria-label', 'Toggle dark mode');
        } else if (icon.includes('search')) {
            $button.attr('aria-label', 'Search');
        } else if (icon.includes('download')) {
            $button.attr('aria-label', 'Download selected files');
        }
    });
    
    // Add aria-labels to download links
    $('a[href*="download"]').each(function() {
        var $link = $(this);
        var fileName = $link.closest('.file-row').find('.file-name').text().trim();
        if (fileName) {
            $link.attr('aria-label', 'Download ' + fileName);
        }
    });
    
    // Add proper labels to form controls
    $('select, input').each(function() {
        var $control = $(this);
        if (!$control.attr('aria-label') && !$control.attr('id')) {
            var placeholder = $control.attr('placeholder');
            var name = $control.attr('name');
            
            if (placeholder) {
                $control.attr('aria-label', placeholder);
            } else if (name) {
                $control.attr('aria-label', name.replace(/[_-]/g, ' '));
            }
        }
    });
}

function handleReducedMotion() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        // Disable animations for users who prefer reduced motion
        $('<style>').text(`
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        `).appendTo('head');
    }
}

/**
 * Utility Functions
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * File Info Panel
 */
function setupFileInfoPanel() {
    // Handle info button clicks
    $('.file-info-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var fileId = $(this).data('file-id');
        openFileInfoPanel(fileId);
    });
    
    // Close panel
    $('#close-info-panel, #info-panel-overlay').on('click', function() {
        closeFileInfoPanel();
    });
    
    // Close on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFileInfoPanel();
        }
    });
}

function openFileInfoPanel(fileId) {
    // Show loading state
    $('#file-info-content').html('<div class="text-center py-8"><div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-google-green"></div><p class="mt-2 text-sm text-google-gray-500">Loading...</p></div>');
    
    // Show overlay first
    $('#info-panel-overlay').removeClass('hidden');
    
    // Small delay to ensure overlay is rendered before panel animation
    setTimeout(function() {
        $('#file-info-panel').removeClass('translate-x-full');
    }, 10);
    
    // Load file information via AJAX
    var endpoint = window.isPublicContext ? 'get_public_file_info' : 'get_file_info';
    
    $.ajax({
        url: window.base_url + 'process.php',
        method: 'GET',
        data: {
            do: endpoint,
            file_id: fileId
        },
        dataType: 'json'
    }).done(function(response) {
        if (response.success) {
            var file = response.file;
            var html = buildFileInfoHTML(file);
            $('#file-info-content').html(html);
        } else {
            var errorMsg = response.error || 'Error loading file information';
            $('#file-info-content').html('<div class="text-center py-8 text-red-500">' + errorMsg + '</div>');
        }
    }).fail(function() {
        $('#file-info-content').html('<div class="text-center py-8 text-red-500">Error loading file information</div>');
    });
}

function closeFileInfoPanel() {
    // Hide panel first
    $('#file-info-panel').addClass('translate-x-full');
    
    // Hide overlay after panel animation completes
    setTimeout(function() {
        $('#info-panel-overlay').addClass('hidden');
    }, 300); // Match the transition duration
}

function buildFileInfoHTML(file) {
    var html = '<div class="space-y-6">';
    
    // File preview/icon
    html += '<div class="text-center">';
    if (file.is_image && file.thumbnail) {
        html += '<img src="' + file.thumbnail + '" alt="' + file.title + '" class="mx-auto max-w-full h-32 object-cover rounded-lg shadow-sm">';
    } else {
        html += '<div class="mx-auto w-16 h-16 bg-google-gray-100 dark:bg-google-gray-800 rounded-lg flex items-center justify-center">';
        html += '<span class="material-icons text-3xl text-google-gray-400">' + (file.icon || 'insert_drive_file') + '</span>';
        html += '</div>';
    }
    html += '<h4 class="mt-3 font-medium text-google-gray-900 dark:text-white">' + file.title + '</h4>';
    html += '</div>';
    
    // File details
    html += '<div class="space-y-4">';
    
    html += '<div class="grid grid-cols-1 gap-3">';
    
    if (file.description) {
        html += '<div><label class="block text-xs font-medium text-google-gray-500 dark:text-google-gray-400 uppercase tracking-wide">Description</label>';
        html += '<p class="mt-1 text-sm text-google-gray-900 dark:text-white">' + file.description + '</p></div>';
    }
    
    html += '<div><label class="block text-xs font-medium text-google-gray-500 dark:text-google-gray-400 uppercase tracking-wide">Size</label>';
    html += '<p class="mt-1 text-sm text-google-gray-900 dark:text-white">' + file.size_formatted + '</p></div>';
    
    html += '<div><label class="block text-xs font-medium text-google-gray-500 dark:text-google-gray-400 uppercase tracking-wide">Type</label>';
    html += '<p class="mt-1 text-sm text-google-gray-900 dark:text-white">' + (file.extension ? file.extension.toUpperCase() + ' file' : 'Unknown') + '</p></div>';
    
    html += '<div><label class="block text-xs font-medium text-google-gray-500 dark:text-google-gray-400 uppercase tracking-wide">Uploaded</label>';
    html += '<p class="mt-1 text-sm text-google-gray-900 dark:text-white">' + file.uploaded_date + '</p></div>';
    
    // Add image information if available
    if (file.image_info) {
        html += '<div><label class="block text-xs font-medium text-google-gray-500 dark:text-google-gray-400 uppercase tracking-wide">Dimensions</label>';
        html += '<p class="mt-1 text-sm text-google-gray-900 dark:text-white">' + file.image_info.dimensions_formatted + '</p></div>';
        
        if (file.image_info.type) {
            html += '<div><label class="block text-xs font-medium text-google-gray-500 dark:text-google-gray-400 uppercase tracking-wide">Image Type</label>';
            html += '<p class="mt-1 text-sm text-google-gray-900 dark:text-white">' + file.image_info.type + '</p></div>';
        }
        
        if (file.image_info.bits) {
            html += '<div><label class="block text-xs font-medium text-google-gray-500 dark:text-google-gray-400 uppercase tracking-wide">Color Depth</label>';
            html += '<p class="mt-1 text-sm text-google-gray-900 dark:text-white">' + file.image_info.bits + ' bits</p></div>';
        }
        
        if (file.image_info.channels) {
            var channelText = file.image_info.channels + ' channel' + (file.image_info.channels > 1 ? 's' : '');
            if (file.image_info.channels === 3) channelText += ' (RGB)';
            else if (file.image_info.channels === 4) channelText += ' (RGBA)';
            html += '<div><label class="block text-xs font-medium text-google-gray-500 dark:text-google-gray-400 uppercase tracking-wide">Channels</label>';
            html += '<p class="mt-1 text-sm text-google-gray-900 dark:text-white">' + channelText + '</p></div>';
        }
    }
    
    if (file.expires && file.expiry_date) {
        html += '<div><label class="block text-xs font-medium text-google-gray-500 dark:text-google-gray-400 uppercase tracking-wide">Expires</label>';
        html += '<p class="mt-1 text-sm text-google-gray-900 dark:text-white">' + file.expiry_date + '</p></div>';
    }
    
    html += '</div>';
    
    // Actions
    if (!file.expired && file.download_link) {
        html += '<div class="pt-4 border-t border-google-gray-200 dark:border-google-gray-700">';
        html += '<a href="' + file.download_link + '" class="inline-flex items-center px-4 py-2 bg-google-green text-white rounded-lg hover:bg-google-green-dark transition-colors">';
        html += '<span class="material-icons text-sm mr-2">download</span>Download</a>';
        html += '</div>';
    }
    
    html += '</div>';
    html += '</div>';
    
    return html;
}

// Make functions available globally
window.toggleTheme = toggleTheme;
window.debounce = debounce;