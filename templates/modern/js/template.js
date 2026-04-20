/**
 * Modern Template JavaScript
 * Provides interactive functionality for the modern card template
 */

(function($) {
    'use strict';

    // Template object to contain all functionality
    const ModernTemplate = {
        // Configuration
        config: {
            animationDuration: 300,
            debounceDelay: 300,
            loadMoreThreshold: 100,
        },

        // Initialize the template
        init: function() {
            this.bindEvents();
            this.initializeComponents();
            this.setupLazyLoading();
            this.initBulkActions();
            console.log('Modern Template initialized');
        },

        // Bind all event handlers
        bindEvents: function() {
            // View toggle functionality
            $(document).on('click', '.view-toggle', this.handleViewToggle.bind(this));
            
            // Preview functionality
            $(document).on('click', '.btn-preview', this.handlePreview.bind(this));
            
            // Modal close
            $(document).on('click', '#closeModal, .modal-overlay', this.closeModal.bind(this));
            $(document).on('click', '.modal-content', function(e) {
                e.stopPropagation();
            });
            
            // Checkbox selection
            $(document).on('change', '.batch_checkbox', this.handleCheckboxChange.bind(this));
            
            // Select all functionality
            $(document).on('change', '#select_all', this.handleSelectAll.bind(this));
            
            // Download tracking
            $(document).on('click', '.btn-download', this.trackDownload.bind(this));
            
            // Clear selection
            $(document).on('click', '#clear-selection', this.clearSelection.bind(this));
            
            // Bulk download
            $(document).on('click', '#bulk-download', this.handleBulkDownload.bind(this));
            
            // Sidebar toggle for mobile
            $(document).on('click', '#sidebarToggle', this.toggleSidebar.bind(this));
            
            // Close sidebar when clicking outside on mobile
            $(document).on('click', function(e) {
                if ($(window).width() <= 1024) {
                    if (!$(e.target).closest('.modern-sidebar, #sidebarToggle').length) {
                        $('.modern-sidebar').removeClass('open');
                    }
                }
            });
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts.bind(this));
            
            // Search with debounce
            let searchTimeout;
            $(document).on('input', '#search_text', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    ModernTemplate.handleSearch();
                }, ModernTemplate.config.debounceDelay);
            });

            // Window resize handler for responsive adjustments
            $(window).on('resize', this.debounce(this.handleResize.bind(this), 250));
        },

        // Initialize components
        initializeComponents: function() {
            this.initTooltips();
            this.initCardAnimations();
            this.adjustGridLayout();
            this.initInfiniteScroll();
        },

        // View toggle functionality (cards vs list)
        handleViewToggle: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const view = $button.data('view');
            
            // Update active state
            $('.view-toggle').removeClass('active');
            $button.addClass('active');
            
            // Toggle grid classes
            const $grid = $('.files-grid');
            if (view === 'list') {
                $grid.addClass('list-view').removeClass('card-view');
                this.animateToListView();
            } else {
                $grid.addClass('card-view').removeClass('list-view');
                this.animateToCardView();
            }
            
            // Store preference
            this.setViewPreference(view);
        },

        // Animate to list view
        animateToListView: function() {
            const $grid = $('.files-grid');
            const $cards = $('.file-card');
            
            // Add list view class to grid
            $grid.addClass('list-view').removeClass('card-view');
            
            $cards.each(function(index) {
                const $card = $(this);
                setTimeout(() => {
                    $card.addClass('list-item');
                }, index * 50);
            });
        },

        // Animate to card view
        animateToCardView: function() {
            const $grid = $('.files-grid');
            const $cards = $('.file-card');
            
            // Add card view class to grid  
            $grid.addClass('card-view').removeClass('list-view');
            $cards.removeClass('list-item');
        },

        // Handle preview functionality
        handlePreview: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const url = $button.data('url');
            
            if (!url) return;
            
            this.showLoadingModal();
            
            // Fetch preview content
            $.ajax({
                method: "GET",
                url: url,
                cache: false,
            }).done((response) => {
                try {
                    const obj = JSON.parse(response);
                    const content = this.createPreviewContent(obj);
                    this.showPreviewModal(content, obj.name);
                } catch (error) {
                    console.error('Preview failed:', error);
                    this.showErrorModal('Failed to load preview');
                }
            }).fail((response) => {
                console.error('Preview request failed:', response);
                this.showErrorModal('Failed to load preview');
            });
        },

        // Create preview content based on file type
        createPreviewContent: function(obj) {
            let content = '';
            
            switch (obj.type) {
                case 'video':
                    content = `
                        <div class="video-preview">
                            <video controls style="width: 100%; max-height: 70vh;">
                                <source src="${obj.file_url}" type="${obj.mime_type}">
                                Your browser does not support the video tag.
                            </video>
                        </div>`;
                    break;
                case 'audio':
                    content = `
                        <div class="audio-preview">
                            <audio controls style="width: 100%;">
                                <source src="${obj.file_url}" type="${obj.mime_type}">
                                Your browser does not support the audio tag.
                            </audio>
                        </div>`;
                    break;
                case 'pdf':
                    content = `
                        <div class="pdf-preview">
                            <iframe src="${obj.file_url}" 
                                    style="width: 100%; height: 70vh; border: none;"
                                    title="PDF Preview">
                            </iframe>
                        </div>`;
                    break;
                case 'image':
                    content = `
                        <div class="image-preview">
                            <img src="${obj.file_url}" 
                                 style="max-width: 100%; max-height: 70vh; display: block; margin: 0 auto;"
                                 alt="${obj.name}">
                        </div>`;
                    break;
                default:
                    content = `
                        <div class="preview-not-available">
                            <p>Preview not available for this file type.</p>
                            <a href="${obj.file_url}" target="_blank" class="btn btn-modern btn-primary">
                                <i class="fa fa-external-link"></i>
                                Open File
                            </a>
                        </div>`;
            }
            
            return content;
        },

        // Show loading modal
        showLoadingModal: function() {
            const loadingHtml = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading preview...</p>
                </div>
            `;
            $('#previewContent').html(loadingHtml);
            $('#previewModal').fadeIn(this.config.animationDuration);
        },

        // Show preview modal with content
        showPreviewModal: function(content, title) {
            $('#previewContent').html(content);
            if (title) {
                // Add title if modal has a title element
                const $modalTitle = $('#previewModal').find('.modal-title');
                if ($modalTitle.length) {
                    $modalTitle.text(title);
                }
            }
            $('#previewModal').fadeIn(this.config.animationDuration);
            
            // Initialize any embedded content
            this.initPreviewContent();
        },

        // Show error modal
        showErrorModal: function(message) {
            const errorHtml = `
                <div class="error-message">
                    <i class="fa fa-exclamation-triangle"></i>
                    <p>${message}</p>
                </div>
            `;
            $('#previewContent').html(errorHtml);
        },

        // Close modal
        closeModal: function(e) {
            if (e.target === e.currentTarget) {
                $('#previewModal').fadeOut(this.config.animationDuration);
            }
        },

        // Initialize preview content
        initPreviewContent: function() {
            const $modal = $('#previewModal');
            
            // Handle image galleries
            $modal.find('img').on('load', function() {
                $(this).addClass('loaded');
            });
            
            // Handle video players
            $modal.find('video').each(function() {
                this.controls = true;
            });
        },

        // Handle checkbox changes
        handleCheckboxChange: function() {
            const checkedCount = $('.batch_checkbox:checked').length;
            const totalCount = $('.batch_checkbox').length;
            
            // Update select all checkbox state
            const $selectAll = $('#select_all');
            if (checkedCount === 0) {
                $selectAll.prop('indeterminate', false).prop('checked', false);
            } else if (checkedCount === totalCount) {
                $selectAll.prop('indeterminate', false).prop('checked', true);
            } else {
                $selectAll.prop('indeterminate', true);
            }
            
            // Update bulk actions state
            this.updateBulkActions(checkedCount);
            
            // Add visual feedback to selected cards
            $('.file-card').each(function() {
                const $card = $(this);
                const $checkbox = $card.find('.batch_checkbox');
                
                if ($checkbox.is(':checked')) {
                    $card.addClass('selected');
                } else {
                    $card.removeClass('selected');
                }
            });
        },

        // Handle select all
        handleSelectAll: function(e) {
            const checked = $(e.currentTarget).is(':checked');
            $('.batch_checkbox').prop('checked', checked).trigger('change');
        },

        // Update bulk actions
        updateBulkActions: function(count) {
            const $bulkInfo = $('.bulk-selection-info');
            const $selectedCount = $('.selected-count');
            const $downloadBtn = $('#bulk-download');
            
            if (count > 0) {
                $bulkInfo.show();
                $selectedCount.text(count);
                $downloadBtn.removeClass('disabled');
            } else {
                $bulkInfo.hide();
                $downloadBtn.addClass('disabled');
            }
        },

        // Track downloads for analytics
        trackDownload: function(e) {
            const fileId = $(e.currentTarget).data('file-id');
            
            // Add visual feedback
            const $btn = $(e.currentTarget);
            $btn.addClass('downloading');
            
            setTimeout(() => {
                $btn.removeClass('downloading');
            }, 2000);
            
            // Track in analytics if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'download', {
                    'file_id': fileId,
                    'template': 'modern'
                });
            }
        },

        // Handle keyboard shortcuts
        handleKeyboardShortcuts: function(e) {
            // Escape key to close modal
            if (e.keyCode === 27) {
                this.closeModal(e);
            }
            
            // Ctrl/Cmd + A to select all
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 65) {
                e.preventDefault();
                $('#select_all').prop('checked', true).trigger('change');
            }
        },

        // Handle search
        handleSearch: function() {
            const query = $('#search_text').val().toLowerCase();
            
            if (query.length === 0) {
                $('.file-card').show();
                return;
            }
            
            $('.file-card').each(function() {
                const $card = $(this);
                const title = $card.find('.file-title').text().toLowerCase();
                const description = $card.find('.file-description').text().toLowerCase();
                
                if (title.includes(query) || description.includes(query)) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
        },

        // Initialize tooltips
        initTooltips: function() {
            $('[title]').each(function() {
                const $element = $(this);
                const title = $element.attr('title');
                
                if (title) {
                    $element.attr('data-tooltip', title).removeAttr('title');
                }
            });
        },

        // Initialize card animations
        initCardAnimations: function() {
            // Animate cards on scroll
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animate-in');
                            observer.unobserve(entry.target);
                        }
                    });
                }, {
                    threshold: 0.1,
                    rootMargin: '50px'
                });

                $('.file-card').each(function() {
                    observer.observe(this);
                });
            } else {
                // Fallback for older browsers
                $('.file-card').addClass('animate-in');
            }
        },

        // Setup lazy loading for images
        setupLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.classList.remove('lazy');
                                imageObserver.unobserve(img);
                            }
                        }
                    });
                });

                $('.card-image.lazy').each(function() {
                    imageObserver.observe(this);
                });
            }
        },

        // Initialize infinite scroll (if pagination is disabled)
        initInfiniteScroll: function() {
            if ($('.pagination').length === 0) {
                let loading = false;
                
                $(window).on('scroll', () => {
                    if (loading) return;
                    
                    const scrollTop = $(window).scrollTop();
                    const windowHeight = $(window).height();
                    const documentHeight = $(document).height();
                    
                    if (scrollTop + windowHeight >= documentHeight - this.config.loadMoreThreshold) {
                        this.loadMoreFiles();
                    }
                });
            }
        },

        // Load more files (for infinite scroll)
        loadMoreFiles: function() {
            // Implementation depends on backend API
            console.log('Loading more files...');
        },

        // Adjust grid layout based on container width
        adjustGridLayout: function() {
            const containerWidth = $('.files-grid').width();
            const cardMinWidth = 320;
            const gap = 24;
            const columns = Math.floor((containerWidth + gap) / (cardMinWidth + gap));
            
            // Apply CSS custom property for responsive grid
            document.documentElement.style.setProperty(
                '--grid-columns', 
                Math.max(1, columns)
            );
        },

        // Handle window resize
        handleResize: function() {
            this.adjustGridLayout();
        },

        // Clear selection
        clearSelection: function(e) {
            e.preventDefault();
            $('.batch_checkbox').prop('checked', false).trigger('change');
        },

        // Handle bulk download
        handleBulkDownload: function(e) {
            e.preventDefault();
            
            const selectedFiles = $('.batch_checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedFiles.length === 0) {
                alert('Please select files to download');
                return;
            }
            
            // Submit form with selected files
            this.submitBulkAction('zip', selectedFiles);
        },

        // Toggle sidebar for mobile
        toggleSidebar: function(e) {
            e.preventDefault();
            $('.modern-sidebar').toggleClass('open');
        },

        // Initialize bulk actions
        initBulkActions: function() {
            // This method is kept for compatibility but logic moved to individual handlers
        },

        // Submit bulk action
        submitBulkAction: function(action, fileIds) {
            if (action === 'zip') {
                // Handle ZIP download via GET request
                const url = window.base_url + 'process.php?do=download_zip&files=' + fileIds.join(',');
                
                // Create invisible iframe for download
                const iframe = $('<iframe>', {
                    style: 'display: none;',
                    src: url
                });
                
                $('body').append(iframe);
                
                // Remove iframe after delay
                setTimeout(() => {
                    iframe.remove();
                }, 5000);
            }
        },

        // Store view preference
        setViewPreference: function(view) {
            if (typeof Cookies !== 'undefined') {
                Cookies.set('modern_template_view', view, { expires: 30 });
            }
        },

        // Get view preference
        getViewPreference: function() {
            if (typeof Cookies !== 'undefined') {
                return Cookies.get('modern_template_view') || 'cards';
            }
            return 'cards';
        },

        // Utility: Debounce function
        debounce: function(func, wait) {
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
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ModernTemplate.init();
        
        // Restore view preference
        const savedView = ModernTemplate.getViewPreference();
        if (savedView) {
            $(`.view-toggle[data-view="${savedView}"]`).trigger('click');
        }
    });

    // Make ModernTemplate available globally for debugging
    window.ModernTemplate = ModernTemplate;

})(jQuery);