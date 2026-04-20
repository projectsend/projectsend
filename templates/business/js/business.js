/**
 * Business Template JavaScript
 * Enhanced functionality for professional document center
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize theme
    initializeTheme();
    
    // Setup search functionality
    setupSearch();
    
    // Setup mobile menu handling
    setupMobileMenu();
    
    // Setup file cards interaction
    setupFileCards();
    
    // Setup accessibility features
    setupAccessibility();
    
});

/**
 * Theme Management
 */
function initializeTheme() {
    // Check for saved theme preference or default to system preference
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
        document.documentElement.classList.add('dark');
    }
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (!localStorage.getItem('theme')) {
            if (e.matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
    });
}

function toggleTheme() {
    const isDark = document.documentElement.classList.contains('dark');
    
    if (isDark) {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    } else {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    }
    
    // Announce theme change to screen readers
    announceToScreenReader(isDark ? 'Light mode activated' : 'Dark mode activated');
}

/**
 * Search Enhancement
 */
function setupSearch() {
    const searchForm = document.querySelector('form[action*="index.php"]');
    const searchInput = document.querySelector('input[name="search"]');
    const categorySelect = document.querySelector('select[name="category"]');
    
    if (!searchInput) return;
    
    // Auto-submit on category change
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    
    // Search input enhancements
    searchInput.addEventListener('input', debounce(function() {
        // Could implement live search here if desired
        // For now, we'll keep it simple with form submission
    }, 300));
    
    // Clear search functionality
    if (searchInput.value) {
        addClearSearchButton(searchInput);
    }
}

function addClearSearchButton(searchInput) {
    const clearButton = document.createElement('button');
    clearButton.type = 'button';
    clearButton.className = 'absolute inset-y-0 right-0 pr-3 flex items-center';
    clearButton.innerHTML = '<i class="fas fa-times text-gray-400 hover:text-gray-600"></i>';
    clearButton.setAttribute('aria-label', 'Clear search');
    
    clearButton.addEventListener('click', function() {
        searchInput.value = '';
        searchInput.focus();
        // Submit form to show all results
        searchInput.form.submit();
    });
    
    // Insert the clear button
    const inputContainer = searchInput.parentElement;
    if (inputContainer.classList.contains('relative')) {
        inputContainer.appendChild(clearButton);
        searchInput.style.paddingRight = '2.5rem';
    }
}

/**
 * Mobile Menu Handling
 */
function setupMobileMenu() {
    // Handle dropdown menu on mobile devices
    const dropdownTrigger = document.querySelector('.group button');
    const dropdownMenu = document.querySelector('.group > div:last-child');
    
    if (!dropdownTrigger || !dropdownMenu) return;
    
    // Toggle dropdown on click for mobile
    if (window.innerWidth <= 768) {
        dropdownTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            dropdownMenu.classList.toggle('opacity-0');
            dropdownMenu.classList.toggle('invisible');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdownTrigger.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.add('opacity-0');
                dropdownMenu.classList.add('invisible');
            }
        });
    }
}

/**
 * File Cards Enhancement
 */
function setupFileCards() {
    const fileCards = document.querySelectorAll('.file-card');
    
    fileCards.forEach(function(card) {
        const checkbox = card.querySelector('.batch_checkbox');
        const isExpired = card.dataset.expired === 'true';
        
        // Make entire card clickable for checkbox
        if (checkbox && !isExpired) {
            card.addEventListener('click', function(e) {
                // Don't toggle if clicking on download button or links
                if (e.target.closest('a')) {
                    return;
                }
                
                e.preventDefault();
                checkbox.checked = !checkbox.checked;
                
                // Trigger change event for batch selection
                const event = new Event('change', { bubbles: true });
                checkbox.dispatchEvent(event);
            });
        }
        
        // Add keyboard navigation
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                if (!isExpired && checkbox) {
                    e.preventDefault();
                    checkbox.checked = !checkbox.checked;
                    const event = new Event('change', { bubbles: true });
                    checkbox.dispatchEvent(event);
                }
            }
        });
        
        // Add focus management
        card.setAttribute('tabindex', '0');
        card.setAttribute('role', 'article');
    });

    // Setup batch selection
    setupBatchSelection();
}

/**
 * Batch Selection for Downloads
 */
function setupBatchSelection() {
    const selectAllCheckbox = document.getElementById('select_all');
    const batchCheckboxes = document.querySelectorAll('.batch_checkbox');
    // Find the download button by ID
    const downloadButton = document.getElementById('zip-download-btn');
    
    // Flag to prevent recursive event triggering
    let isUpdatingSelectAll = false;
    
    if (!selectAllCheckbox || !downloadButton) {
        console.log('Batch selection elements not found');
        return;
    }
    
    // Select all functionality
    selectAllCheckbox.addEventListener('change', function() {
        if (isUpdatingSelectAll) return;
        
        const shouldCheck = selectAllCheckbox.checked;
        
        // Get fresh list of checkboxes each time
        const currentCheckboxes = document.querySelectorAll('.batch_checkbox');
        
        currentCheckboxes.forEach(function(checkbox) {
            if (checkbox.checked !== shouldCheck) {
                checkbox.checked = shouldCheck;
                updateVisualState(checkbox);
            }
        });
        
        updateDownloadButton();
    });
    
    // Individual checkbox functionality
    batchCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            updateVisualState(checkbox);
            updateSelectAllState();
            updateDownloadButton();
        });
    });
    
    function updateVisualState(checkbox) {
        const card = checkbox.closest('.file-card');
        if (!card) return;
        
        if (checkbox.checked) {
            card.classList.add('selected');
            // Add visual checkbox indicator
            if (!card.querySelector('.selection-indicator')) {
                const indicator = document.createElement('div');
                indicator.className = 'selection-indicator absolute top-3 left-3 w-6 h-6 bg-primary-900 dark:bg-primary-500 rounded flex items-center justify-center';
                indicator.innerHTML = '<i class="fas fa-check text-white text-xs"></i>';
                card.appendChild(indicator);
            }
        } else {
            card.classList.remove('selected');
            // Remove visual checkbox indicator
            const indicator = card.querySelector('.selection-indicator');
            if (indicator) {
                indicator.remove();
            }
        }
    }
    
    function updateSelectAllState() {
        const allCheckboxes = document.querySelectorAll('.batch_checkbox');
        const checkedBoxes = document.querySelectorAll('.batch_checkbox:checked');
        
        if (allCheckboxes.length > 0) {
            const shouldBeChecked = checkedBoxes.length === allCheckboxes.length;
            const shouldBeIndeterminate = checkedBoxes.length > 0 && checkedBoxes.length < allCheckboxes.length;
            
            // Prevent recursive triggering
            isUpdatingSelectAll = true;
            selectAllCheckbox.checked = shouldBeChecked;
            selectAllCheckbox.indeterminate = shouldBeIndeterminate;
            isUpdatingSelectAll = false;
        }
    }
    
    function updateDownloadButton() {
        const checkedBoxes = document.querySelectorAll('.batch_checkbox:checked');
        const hasSelection = checkedBoxes.length > 0;
        const zipText = downloadButton.querySelector('.zip-text');
        
        // Show/hide button instead of disabling
        if (hasSelection) {
            downloadButton.classList.remove('hidden');
            if (zipText) {
                zipText.textContent = checkedBoxes.length === 1 ? 
                    'Download 1 file as ZIP' : 
                    `Download ${checkedBoxes.length} files as ZIP`;
            }
        } else {
            downloadButton.classList.add('hidden');
        }
    }
    
    // Handle ZIP download
    downloadButton.addEventListener('click', function(e) {
        e.preventDefault();
        
        const checkedBoxes = document.querySelectorAll('.batch_checkbox:checked');
        if (checkedBoxes.length === 0) {
            alert('Please select files to download');
            return;
        }
        
        const fileIds = Array.from(checkedBoxes).map(cb => cb.value);
        const url = window.base_url + 'process.php?do=download_zip&files=' + fileIds.join(',');
        
        // Create invisible iframe for download
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = url;
        document.body.appendChild(iframe);
        
        // Remove iframe after a delay
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 5000);
    });
    
    // Initialize visual state for all checkboxes
    const allCheckboxes = document.querySelectorAll('.batch_checkbox');
    allCheckboxes.forEach(function(checkbox) {
        updateVisualState(checkbox);
    });
    
    // Initial state
    updateSelectAllState();
    updateDownloadButton();
}

/**
 * Accessibility Features
 */
function setupAccessibility() {
    // Skip to content link
    addSkipToContentLink();
    
    // Improve focus visibility
    improveFocusVisibility();
    
    // Add ARIA labels where needed
    addAriaLabels();
    
    // Handle reduced motion preference
    handleReducedMotion();
}

function addSkipToContentLink() {
    const skipLink = document.createElement('a');
    skipLink.href = '#main-content';
    skipLink.textContent = 'Skip to main content';
    skipLink.className = 'sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-primary-600 text-white px-4 py-2 rounded-lg z-50';
    
    document.body.insertBefore(skipLink, document.body.firstChild);
    
    // Add id to main content
    const mainContent = document.querySelector('main');
    if (mainContent) {
        mainContent.id = 'main-content';
    }
}

function improveFocusVisibility() {
    // Add custom focus styles for better visibility
    const style = document.createElement('style');
    style.textContent = `
        .focus-visible:focus {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }
        
        .dark .focus-visible:focus {
            outline-color: #60a5fa;
        }
    `;
    document.head.appendChild(style);
}

function addAriaLabels() {
    // Add aria-labels to icon-only buttons
    const iconButtons = document.querySelectorAll('button:not([aria-label])');
    iconButtons.forEach(function(button) {
        const icon = button.querySelector('i');
        if (icon && !button.textContent.trim()) {
            if (icon.classList.contains('fa-moon')) {
                button.setAttribute('aria-label', 'Switch to dark mode');
            } else if (icon.classList.contains('fa-sun')) {
                button.setAttribute('aria-label', 'Switch to light mode');
            }
        }
    });
    
    // Add aria-labels to download links
    const downloadLinks = document.querySelectorAll('a[href*="download"]');
    downloadLinks.forEach(function(link) {
        const fileName = link.closest('.grid > div').querySelector('h3').textContent;
        link.setAttribute('aria-label', `Download ${fileName}`);
    });
}

function handleReducedMotion() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        // Disable animations for users who prefer reduced motion
        const style = document.createElement('style');
        style.textContent = `
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        `;
        document.head.appendChild(style);
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

function announceToScreenReader(message) {
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', 'polite');
    announcement.setAttribute('aria-atomic', 'true');
    announcement.className = 'sr-only';
    announcement.textContent = message;
    
    document.body.appendChild(announcement);
    
    setTimeout(() => {
        document.body.removeChild(announcement);
    }, 1000);
}

/**
 * File Type Icon Mapping
 */
function getFileTypeIcon(extension) {
    const iconMap = {
        // Documents
        'pdf': 'fas fa-file-pdf',
        'doc': 'fas fa-file-word',
        'docx': 'fas fa-file-word',
        'xls': 'fas fa-file-excel',
        'xlsx': 'fas fa-file-excel',
        'ppt': 'fas fa-file-powerpoint',
        'pptx': 'fas fa-file-powerpoint',
        'txt': 'fas fa-file-text',
        'rtf': 'fas fa-file-text',
        
        // Images
        'jpg': 'fas fa-file-image',
        'jpeg': 'fas fa-file-image',
        'png': 'fas fa-file-image',
        'gif': 'fas fa-file-image',
        'bmp': 'fas fa-file-image',
        'svg': 'fas fa-file-image',
        'webp': 'fas fa-file-image',
        
        // Audio
        'mp3': 'fas fa-file-audio',
        'wav': 'fas fa-file-audio',
        'flac': 'fas fa-file-audio',
        'aac': 'fas fa-file-audio',
        'ogg': 'fas fa-file-audio',
        
        // Video
        'mp4': 'fas fa-file-video',
        'avi': 'fas fa-file-video',
        'mov': 'fas fa-file-video',
        'wmv': 'fas fa-file-video',
        'flv': 'fas fa-file-video',
        'webm': 'fas fa-file-video',
        
        // Archives
        'zip': 'fas fa-file-archive',
        'rar': 'fas fa-file-archive',
        '7z': 'fas fa-file-archive',
        'tar': 'fas fa-file-archive',
        'gz': 'fas fa-file-archive',
        
        // Code
        'html': 'fas fa-file-code',
        'css': 'fas fa-file-code',
        'js': 'fas fa-file-code',
        'php': 'fas fa-file-code',
        'py': 'fas fa-file-code',
        'java': 'fas fa-file-code',
        'cpp': 'fas fa-file-code',
        'c': 'fas fa-file-code',
        'sql': 'fas fa-file-code'
    };
    
    return iconMap[extension.toLowerCase()] || 'fas fa-file';
}

// Make functions available globally
window.toggleTheme = toggleTheme;
window.getFileTypeIcon = getFileTypeIcon;