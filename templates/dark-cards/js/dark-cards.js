$(document).ready(function() {
    let selectedFiles = [];
    
    // File selection functionality
    $('.file-card').on('click', function(e) {
        e.preventDefault();
        
        const fileId = $(this).data('file-id');
        const checkbox = $(this).find('.file-checkbox');
        const isSelected = $(this).hasClass('selected');
        
        if (isSelected) {
            // Deselect file
            $(this).removeClass('selected');
            checkbox.prop('checked', false);
            selectedFiles = selectedFiles.filter(id => id !== fileId.toString());
        } else {
            // Select file
            $(this).addClass('selected');
            checkbox.prop('checked', true);
            selectedFiles.push(fileId.toString());
        }
        
        updateSelectionUI();
    });
    
    // Prevent checkbox clicks from propagating
    $('.file-checkbox').on('click', function(e) {
        e.stopPropagation();
    });
    
    // Update selection UI
    function updateSelectionUI() {
        const count = selectedFiles.length;
        
        if (count > 0) {
            $('#zip-download-fab').removeClass('hidden');
            $('#selection-count').text(count);
        } else {
            $('#zip-download-fab').addClass('hidden');
        }
        
        // Update aria labels for accessibility
        $('.file-card').each(function() {
            const isSelected = $(this).hasClass('selected');
            $(this).attr('aria-selected', isSelected);
        });
    }
    
    // ZIP Download functionality
    $('#download-zip-btn').on('click', function(e) {
        e.preventDefault();
        
        if (selectedFiles.length === 0) {
            showNotification('Please select files to download.', 'warning');
            return;
        }
        
        // Show loading state
        const button = $(this);
        const originalIcon = button.find('.material-icons').text();
        button.find('.material-icons').text('hourglass_empty').addClass('animate-spin');
        button.prop('disabled', true);
        
        // Prepare the checkbox data like the system expects
        const checkboxes = selectedFiles.map(fileId => ({
            name: 'files[]',
            value: fileId
        }));
        
        // First get the file IDs in the correct format
        $.ajax({
            method: 'GET',
            url: window.base_url + 'process.php',
            data: { 
                do: "return_files_ids", 
                files: checkboxes 
            }
        }).done(function(response) {
            // Create iframe for download
            const downloadUrl = window.base_url + 'process.php?do=download_zip&files=' + response;
            const iframe = $('<iframe>', {
                style: 'display:none',
                src: downloadUrl
            });
            $('body').append(iframe);
            
            // Show success notification
            showNotification(`Downloading ${selectedFiles.length} file(s)...`, 'success');
            
            // Remove iframe after a delay
            setTimeout(function() {
                iframe.remove();
            }, 5000);
            
            // Reset selection after successful download
            setTimeout(function() {
                clearSelection();
            }, 1000);
            
        }).fail(function() {
            showNotification('Error preparing download. Please try again.', 'error');
        }).always(function() {
            // Reset button state
            button.find('.material-icons').text(originalIcon).removeClass('animate-spin');
            button.prop('disabled', false);
        });
    });
    
    // Clear all selections
    function clearSelection() {
        selectedFiles = [];
        $('.file-card').removeClass('selected');
        $('.file-checkbox').prop('checked', false);
        updateSelectionUI();
    }
    
    // Show notification
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        $('.notification').remove();
        
        const typeColors = {
            'success': 'from-green-500 to-green-600',
            'error': 'from-red-500 to-red-600',
            'warning': 'from-yellow-500 to-yellow-600',
            'info': 'from-purple-primary to-orange-primary'
        };
        
        const notification = $(`
            <div class="notification fixed top-4 right-4 z-50 max-w-sm">
                <div class="bg-gradient-to-r ${typeColors[type]} text-white px-6 py-4 rounded-xl shadow-lg transform translate-x-full transition-transform duration-300 ease-out">
                    <div class="flex items-center">
                        <span class="material-icons mr-3">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'info'}</span>
                        <span class="font-medium">${message}</span>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(notification);
        
        // Animate in
        setTimeout(function() {
            notification.find('div').removeClass('translate-x-full');
        }, 100);
        
        // Auto remove after 4 seconds
        setTimeout(function() {
            notification.find('div').addClass('translate-x-full');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 4000);
    }
    
    // Keyboard navigation
    $(document).on('keydown', function(e) {
        // Escape key to clear selection
        if (e.key === 'Escape') {
            clearSelection();
        }
        
        // Ctrl/Cmd + A to select all visible files
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            e.preventDefault();
            selectAllFiles();
        }
        
        // Delete key to clear selection
        if (e.key === 'Delete' || e.key === 'Backspace') {
            clearSelection();
        }
    });
    
    // Select all visible files
    function selectAllFiles() {
        $('.file-card').each(function() {
            const fileId = $(this).data('file-id');
            if (!$(this).hasClass('selected')) {
                $(this).addClass('selected');
                $(this).find('.file-checkbox').prop('checked', true);
                selectedFiles.push(fileId.toString());
            }
        });
        updateSelectionUI();
        showNotification(`Selected ${selectedFiles.length} file(s)`, 'info');
    }
    
    // Search functionality enhancements
    let searchTimeout;
    $('input[name="search"]').on('input', function() {
        clearTimeout(searchTimeout);
        const form = $(this).closest('form');
        
        searchTimeout = setTimeout(function() {
            // Auto-submit search after 500ms of no typing
            // form.submit();
        }, 500);
    });
    
    // Add loading animation to search
    $('form').on('submit', function() {
        const searchInput = $(this).find('input[name="search"]');
        const icon = $(this).find('.material-icons');
        
        if (searchInput.val().trim()) {
            icon.addClass('animate-spin');
        }
    });
    
    // Folder card hover effects
    $('.group').on('mouseenter', function() {
        $(this).find('.material-icons').addClass('animate-pulse');
    }).on('mouseleave', function() {
        $(this).find('.material-icons').removeClass('animate-pulse');
    });
    
    // File card keyboard accessibility
    $('.file-card').attr('tabindex', '0').on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).click();
        }
    });
    
    // Add ripple effect to cards
    $('.file-card, .group > div').on('click', function(e) {
        const ripple = $('<div class="ripple"></div>');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.css({
            position: 'absolute',
            borderRadius: '50%',
            background: 'rgba(139, 92, 246, 0.3)',
            transform: 'scale(0)',
            left: x + 'px',
            top: y + 'px',
            width: size + 'px',
            height: size + 'px',
            pointerEvents: 'none',
            animation: 'ripple-animation 0.6s ease-out'
        });
        
        $(this).css('position', 'relative').append(ripple);
        
        setTimeout(function() {
            ripple.remove();
        }, 600);
    });
    
    // Add ripple animation CSS
    if (!$('#ripple-styles').length) {
        $('head').append(`
            <style id="ripple-styles">
                @keyframes ripple-animation {
                    to {
                        transform: scale(2);
                        opacity: 0;
                    }
                }
            </style>
        `);
    }
    
    // Performance optimization: Intersection Observer for lazy loading
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('loading');
                    observer.unobserve(img);
                }
            });
        });
        
        // Observe all images with data-src
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // Initialize tooltips for file info
    $('.file-card').each(function() {
        const fileId = $(this).data('file-id');
        $(this).attr('title', `File ID: ${fileId} - Click to select`);
    });
    
    // Add smooth scroll to top button (if page is long)
    if ($(document).height() > $(window).height() * 2) {
        const scrollToTop = $(`
            <button id="scroll-to-top" class="fixed bottom-24 right-6 w-12 h-12 bg-dark-surface border border-dark-surface-light rounded-full shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-110 hidden z-40">
                <span class="material-icons text-purple-primary">keyboard_arrow_up</span>
            </button>
        `);
        
        $('body').append(scrollToTop);
        
        $(window).on('scroll', function() {
            if ($(this).scrollTop() > 300) {
                $('#scroll-to-top').removeClass('hidden');
            } else {
                $('#scroll-to-top').addClass('hidden');
            }
        });
        
        $('#scroll-to-top').on('click', function() {
            $('html, body').animate({ scrollTop: 0 }, 600);
        });
    }
});

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        // Refresh selection state when page becomes visible again
        console.log('Page visible again');
    }
});

// Handle browser back/forward buttons
window.addEventListener('popstate', function(e) {
    // Clear selection when navigating
    if (typeof clearSelection === 'function') {
        clearSelection();
    }
});