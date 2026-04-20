(function () {
    'use strict';

    /**
     * File Information Panel Component
     * Provides detailed file information in a sliding side panel
     */
    class FileInfoPanel {
        constructor() {
            this.panel = document.getElementById('file-info-panel');
            this.overlay = document.getElementById('info-panel-overlay');
            this.loadingElement = document.getElementById('file-info-loading');
            this.dataElement = document.getElementById('file-info-data');
            this.closeButton = document.getElementById('close-info-panel');

            this.isOpen = false;
            this.currentFileId = null;

            this.init();
        }

        init() {
            if (!this.panel || !this.overlay) return;

            this.setupEventListeners();
        }

        setupEventListeners() {
            // File info button clicks
            document.addEventListener('click', (e) => {
                if (e.target.closest('.file-info-btn')) {
                    e.preventDefault();
                    e.stopPropagation();

                    const button = e.target.closest('.file-info-btn');
                    const fileId = button.getAttribute('data-file-id');
                    this.openPanel(fileId);
                }
            });

            // Close button
            if (this.closeButton) {
                this.closeButton.addEventListener('click', () => {
                    this.closePanel();
                });
            }

            // Overlay click to close
            if (this.overlay) {
                this.overlay.addEventListener('click', () => {
                    this.closePanel();
                });
            }

            // Escape key to close
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.closePanel();
                }
            });
        }

        openPanel(fileId) {
            if (!fileId) return;

            this.currentFileId = fileId;
            this.isOpen = true;

            // Show loading state
            this.showLoading();

            // Show overlay and panel
            this.overlay.style.display = 'block';
            this.panel.style.display = 'flex';

            // Trigger animations
            setTimeout(() => {
                this.overlay.classList.add('show');
                this.panel.classList.add('show');
            }, 10);

            // Load file information
            this.loadFileInfo(fileId);
        }

        closePanel() {
            this.isOpen = false;

            // Hide panel with animation
            this.overlay.classList.remove('show');
            this.panel.classList.remove('show');

            // Hide elements after animation
            setTimeout(() => {
                this.overlay.style.display = 'none';
                this.panel.style.display = 'none';
            }, 300);
        }

        showLoading() {
            this.loadingElement.style.display = 'flex';
            this.dataElement.style.display = 'none';
        }

        showData() {
            this.loadingElement.style.display = 'none';
            this.dataElement.style.display = 'block';
        }

        showError(message) {
            this.dataElement.innerHTML = `
                <div style="padding: 40px 24px; text-align: center; color: var(--color-danger);">
                    <i class="fa fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 16px;"></i>
                    <p style="margin: 0;">${message}</p>
                </div>
            `;
            this.showData();
        }

        loadFileInfo(fileId) {
            // Use jQuery for AJAX to maintain compatibility
            $.ajax({
                url: json_strings.uri.base + 'process.php',
                method: 'GET',
                data: {
                    do: 'get_file_info',
                    file_id: fileId
                },
                dataType: 'json'
            }).done((response) => {
                if (response.success && response.file) {
                    this.renderFileInfo(response.file);
                } else {
                    this.showError(response.error || 'Error loading file information');
                }
            }).fail(() => {
                this.showError('Failed to load file information');
            });
        }

        renderFileInfo(file) {
            const html = this.buildFileInfoHTML(file);
            this.dataElement.innerHTML = html;
            this.showData();
        }

        buildFileInfoHTML(file) {
            let html = '';

            // File preview section
            html += '<div class="file-preview-section">';
            if (file.is_image && file.thumbnail) {
                html += `<img src="${file.thumbnail}" alt="${file.title}" class="file-thumbnail-large">`;
            } else {
                const colorClass = this.getExtensionColorClass(file.extension);
                html += `<div class="file-icon-large ${colorClass}">${file.extension ? file.extension.toUpperCase() : 'FILE'}</div>`;
            }
            html += `<h4 class="file-title">${file.title}</h4>`;
            html += '</div>';

            // File details section
            html += '<div class="file-details-section">';

            // Basic information
            html += '<div class="detail-group">';
            html += '<div class="detail-group-title">Basic Information</div>';

            html += '<div class="detail-item">';
            html += '<span class="detail-label">Original filename</span>';
            html += `<span class="detail-value">${file.filename_original || file.title}</span>`;
            html += '</div>';

            if (file.description) {
                html += '<div class="detail-item">';
                html += '<span class="detail-label">Description</span>';
                html += `<span class="detail-value${file.description.length > 100 ? ' truncate' : ''}">${file.description}</span>`;
                html += '</div>';
            }

            html += '<div class="detail-item">';
            html += '<span class="detail-label">File size</span>';
            html += `<span class="detail-value">${file.size_formatted}</span>`;
            html += '</div>';

            html += '<div class="detail-item">';
            html += '<span class="detail-label">File type</span>';
            html += `<span class="detail-value">${file.extension ? file.extension.toUpperCase() + ' file' : 'Unknown'}</span>`;
            html += '</div>';

            html += '<div class="detail-item">';
            html += '<span class="detail-label">Upload date</span>';
            html += `<span class="detail-value">${file.uploaded_date}</span>`;
            html += '</div>';

            if (file.uploaded_by) {
                html += '<div class="detail-item">';
                html += '<span class="detail-label">Uploaded by</span>';
                html += `<span class="detail-value">${file.uploaded_by}</span>`;
                html += '</div>';
            }
            html += '</div>';

            // Image metadata (for images)
            if (file.image_metadata) {
                html += '<div class="detail-group">';
                html += '<div class="detail-group-title">Image Information</div>';

                if (file.image_metadata.width && file.image_metadata.height) {
                    html += '<div class="detail-item">';
                    html += '<span class="detail-label">Dimensions</span>';
                    html += `<span class="detail-value">${file.image_metadata.width} × ${file.image_metadata.height} pixels</span>`;
                    html += '</div>';
                }

                if (file.image_metadata.exif) {
                    const exif = file.image_metadata.exif;
                    if (exif.Make || exif.Model) {
                        html += '<div class="detail-item">';
                        html += '<span class="detail-label">Camera</span>';
                        html += `<span class="detail-value">${exif.Make || ''} ${exif.Model || ''}</span>`;
                        html += '</div>';
                    }
                    if (exif.DateTime) {
                        html += '<div class="detail-item">';
                        html += '<span class="detail-label">Taken on</span>';
                        html += `<span class="detail-value">${exif.DateTime}</span>`;
                        html += '</div>';
                    }
                    if (exif.ExposureTime) {
                        html += '<div class="detail-item">';
                        html += '<span class="detail-label">Exposure</span>';
                        html += `<span class="detail-value">${exif.ExposureTime}</span>`;
                        html += '</div>';
                    }
                    if (exif.FNumber) {
                        html += '<div class="detail-item">';
                        html += '<span class="detail-label">F-stop</span>';
                        html += `<span class="detail-value">f/${exif.FNumber}</span>`;
                        html += '</div>';
                    }
                    if (exif.ISOSpeedRatings) {
                        html += '<div class="detail-item">';
                        html += '<span class="detail-label">ISO</span>';
                        html += `<span class="detail-value">${exif.ISOSpeedRatings}</span>`;
                        html += '</div>';
                    }
                }
                html += '</div>';
            }

            // Status information
            html += '<div class="detail-group">';
            html += '<div class="detail-group-title">Status & Permissions</div>';

            html += '<div class="detail-item">';
            html += '<span class="detail-label">Privacy</span>';
            const publicStatus = file.public == 1 ? 'success' : 'warning';
            const publicText = file.public == 1 ? 'Public' : 'Private';
            const publicIcon = file.public == 1 ? 'fa-globe' : 'fa-lock';
            html += `<span class="detail-value"><span class="status-badge ${publicStatus}"><i class="fa ${publicIcon}"></i> ${publicText}</span></span>`;
            html += '</div>';

            if (file.public == 1 && file.public_url) {
                html += '<div class="detail-item">';
                html += '<span class="detail-label">Public URL</span>';
                html += `<span class="detail-value"><a href="${file.public_url}" target="_blank" class="text-primary"><i class="fa fa-external-link"></i> View public page</a></span>`;
                html += '</div>';
            }

            if (file.expires == 1) {
                html += '<div class="detail-item">';
                html += '<span class="detail-label">Expiry</span>';
                const isExpired = file.expired;
                const expiryStatus = isExpired ? 'danger' : (file.days_until_expiry <= 7 ? 'warning' : 'success');
                const expiryIcon = isExpired ? 'fa-clock-o' : 'fa-calendar-check-o';
                let expiryText = file.expiry_date_formatted || 'Set to expire';
                if (!isExpired && file.days_until_expiry !== undefined) {
                    expiryText += ` (${file.days_until_expiry} days)`;
                }
                html += `<span class="detail-value"><span class="status-badge ${expiryStatus}"><i class="fa ${expiryIcon}"></i> ${expiryText}</span></span>`;
                html += '</div>';
            }

            if (file.download_count !== undefined) {
                html += '<div class="detail-item">';
                html += '<span class="detail-label">Downloads</span>';
                html += `<span class="detail-value"><span class="status-badge info"><i class="fa fa-download"></i> ${file.download_count}</span></span>`;
                html += '</div>';
            }

            html += '</div>';

            // Categories (if available)
            if (file.categories && file.categories.length > 0) {
                html += '<div class="detail-group">';
                html += '<div class="detail-group-title">Categories</div>';
                html += '<div class="detail-item">';
                html += '<div class="detail-value">';
                file.categories.forEach(category => {
                    html += `<span class="status-badge info" style="margin: 2px;"><i class="fa fa-tag"></i> ${category.name}</span>`;
                });
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }

            // Assignments (if available and user has permission)
            if (file.assignments && (file.assignments.clients.length > 0 || file.assignments.groups.length > 0)) {
                html += '<div class="detail-group">';
                html += '<div class="detail-group-title">Assignments</div>';

                if (file.assignments.clients.length > 0) {
                    html += '<div class="detail-item">';
                    html += '<span class="detail-label">Clients</span>';
                    html += `<span class="detail-value">${file.assignments.clients.length} client(s)</span>`;
                    html += '</div>';
                }

                if (file.assignments.groups.length > 0) {
                    html += '<div class="detail-item">';
                    html += '<span class="detail-label">Groups</span>';
                    html += `<span class="detail-value">${file.assignments.groups.length} group(s)</span>`;
                    html += '</div>';
                }

                html += '</div>';
            }

            html += '</div>';

            // Actions section
            html += '<div class="file-actions-section">';
            html += '<div class="action-buttons">';

            if (file.edit_url) {
                html += `<a href="${file.edit_url}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>`;
            }

            if (file.download_url) {
                html += `<a href="${file.download_url}" class="btn btn-success" target="_blank"><i class="fa fa-download"></i> Download</a>`;
            }

            html += '</div>';
            html += '</div>';

            return html;
        }

        getExtensionColorClass(extension) {
            if (!extension) return 'badge-default';

            const ext = extension.toUpperCase();
            const colorMap = {
                'PDF': 'badge-red',
                'DOC': 'badge-blue', 'DOCX': 'badge-blue',
                'XLS': 'badge-green', 'XLSX': 'badge-green',
                'PPT': 'badge-orange', 'PPTX': 'badge-orange',
                'ZIP': 'badge-purple', 'RAR': 'badge-purple', '7Z': 'badge-purple',
                'MP3': 'badge-pink', 'WAV': 'badge-pink', 'FLAC': 'badge-pink',
                'MP4': 'badge-indigo', 'AVI': 'badge-indigo', 'MOV': 'badge-indigo',
                'TXT': 'badge-gray', 'RTF': 'badge-gray',
            };

            return colorMap[ext] || 'badge-default';
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new FileInfoPanel();
        });
    } else {
        new FileInfoPanel();
    }

    // Export for external use
    window.FileInfoPanel = FileInfoPanel;

})();