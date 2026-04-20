<?php
/**
 * Class that generates a modern card-based layout to replace traditional tables.
 * Maintains API compatibility with Table class for easy migration.
 */

namespace ProjectSend\Classes\Layout;

class CardList
{
    private $output;
    private $contents;
    private $current_row;
    private $origin;
    private $attributes;
    private $content;
    private $is_checkbox;
    private $value;
    private $columns;
    private $current_row_data;
    private $current_row_cells;
    private $sort_columns;

    function __construct($attributes)
    {
        $this->contents = $this->open($attributes);
        $this->current_row = 1;
        $this->current_row_data = [];
        $this->current_row_cells = [];
        $this->columns = [];
        $this->sort_columns = [];

        if (isset($attributes['origin'])) {
            $this->origin = $attributes['origin'];
        }
    }

    /**
     * Create the card list container
     */
    public function open($attributes)
    {
        $classes = 'card-list';
        if (isset($attributes['class'])) {
            $classes .= ' ' . $attributes['class'];
        }

        $this->output = '<div class="' . $classes . '"';
        foreach ($attributes as $tag => $value) {
            if ($tag !== 'class') {
                $this->output .= ' ' . $tag . '="' . $value . '"';
            }
        }
        $this->output .= ">\n";

        // Note: Control header is now handled by shared control bar

        return $this->output;
    }

    /**
     * Build sortable URL for column headers
     */
    private function buildSortableUrl($sort_url, $is_current_sorted)
    {
        $url_parse = parse_url($_SERVER['REQUEST_URI']);
        $url_parts = explode('/', $url_parse['path']);
        array_shift($url_parts);

        if (!empty($_GET)) {
            $new_url_parameters = $_GET;
        }
        $new_url_parameters['orderby'] = $sort_url;

        $order = 'desc';
        if (!empty($new_url_parameters['order'])) {
            $order = ($new_url_parameters['order'] == 'asc') ? 'desc' : 'asc';
            if ($is_current_sorted != true) {
                $order = ($order == 'asc') ? 'desc' : 'asc';
            }
        }
        $new_url_parameters['order'] = $order;

        foreach ($new_url_parameters as $param => $value) {
            if ($param != 'page') {
                $params[$param] = $value;
            }
        }
        $query = http_build_query($params);

        $url = (!empty($this->origin)) ? BASE_URI . $this->origin : BASE_URI;
        return $url . '?' . $query;
    }

    /**
     * Process column headers (thead equivalent)
     */
    public function thead($columns)
    {
        $this->columns = $columns;
        $sort_options = [];

        // Build sort dropdown options
        foreach ($columns as $column) {
            $continue = (!isset($column['condition']) || !empty($column['condition'])) ? true : false;
            if ($continue && !empty($column['sortable']) && !empty($column['sort_url'])) {
                $content = (!empty($column['content'])) ? $column['content'] : '';
                $sort_url = $column['sort_url'];
                $order = (!empty($_GET['order'])) ? html_output($_GET['order']) : 'desc';

                $is_current_sorted = false;
                $is_active = '';
                if (isset($_GET['orderby']) && $_GET['orderby'] == $sort_url) {
                    $is_current_sorted = true;
                    $is_active = ' active';
                }

                $url = $this->buildSortableUrl($sort_url, $is_current_sorted);
                $sort_options[] = [
                    'url' => $url,
                    'label' => $content,
                    'active' => $is_active,
                    'order' => $order
                ];
            }
        }

        // Note: Sort and select all controls are now handled by the shared control bar above both views

        // Add batch operations if select_all column exists
        $has_select_all = false;
        foreach ($columns as $column) {
            if (!empty($column['select_all'])) {
                $has_select_all = true;
                break;
            }
        }

        if ($has_select_all) {
            $batch_html = '<div class="card-list-batch-bar" style="display: none;">';
            $batch_html .= '<div class="batch-controls">';
            $batch_html .= '<span class="batch-count">0 ' . __('selected', 'cftp_admin') . '</span>';
            $batch_html .= '<div class="batch-actions"></div>';
            $batch_html .= '</div>';
            $batch_html .= '</div>';
            $this->contents .= $batch_html;
        }

        // Start cards container
        $this->contents .= '<div class="card-list-items">' . "\n";
    }

    /**
     * Footer processing (not used in card layout but kept for compatibility)
     */
    public function tfoot($columns)
    {
        // Not applicable for card layout
    }

    /**
     * Add a new card (row equivalent)
     */
    public function addRow($attributes = [])
    {
        if ($this->current_row == 1) {
            // Already handled in thead
        }

        $this->current_row_data = $attributes;
        $this->current_row_cells = [];
        $this->current_row++;
    }

    /**
     * Add content to current card (cell equivalent)
     */
    public function addCell($attributes)
    {
        $continue = (!isset($attributes['condition']) || !empty($attributes['condition'])) ? true : false;


        if ($continue) {
            $this->content = (!empty($attributes['content'])) ? $attributes['content'] : '';
            $this->is_checkbox = (!empty($attributes['checkbox'])) ? true : false;
            $this->value = (!empty($attributes['value'])) ? html_output($attributes['value']) : null;

            if ($this->is_checkbox) {
                $this->content = '<input type="checkbox" class="batch_checkbox card-checkbox" name="batch[]" value="' . $this->value . '" />' . "\n";
            }

            $this->current_row_cells[] = [
                'content' => $this->content,
                'is_checkbox' => $this->is_checkbox,
                'attributes' => (!empty($attributes['attributes'])) ? $attributes['attributes'] : [],
                'card_content' => (!empty($attributes['card_content'])) ? $attributes['card_content'] : null
            ];
        }
    }

    /**
     * End current card and render it
     */
    public function end_row()
    {
        if (empty($this->current_row_cells)) {
            return;
        }

        $card_class = 'card-list-item';
        if ($this->current_row % 2 == 0) {
            $card_class .= ' even';
        }

        $this->contents .= '<div class="' . $card_class . '">' . "\n";

        // Process cells based on column configuration
        $cell_index = 0;
        $has_checkbox = false;
        $checkbox_content = '';
        $card_data = [
            'title' => '',
            'preview' => '',
            'meta' => [],
            'status' => [],
            'content' => [],
            'actions' => []
        ];

        foreach ($this->current_row_cells as $cell) {
            $column = isset($this->columns[$cell_index]) ? $this->columns[$cell_index] : [];
            $column_label = isset($column['content']) ? $column['content'] : '';

            // Check if this cell has a custom column name identifier
            $custom_column_name = isset($cell['attributes']['column_name']) ? $cell['attributes']['column_name'] : '';


            if ($cell['is_checkbox']) {
                $has_checkbox = true;
                $checkbox_content = $cell['content'];
            } elseif (!empty($column['actions']) || $cell_index >= count($this->columns) - 2 || $this->isActionCell($cell['content'])) {
                // Actions
                $card_data['actions'][] = $cell['content'];
            } else {
                // Check for custom column name first, then use column label
                $identifier = !empty($custom_column_name) ? $custom_column_name : strtolower($column_label);

                switch ($identifier) {
                    case 'title':
                    case 'filename':
                        // Use card_content if available (simplified title), otherwise use regular content
                        $card_data['title'] = !empty($cell['card_content']) ? $cell['card_content'] : $cell['content'];
                        break;

                    case 'preview':
                        $card_data['preview'] = $cell['content'];
                        break;

                    case 'added on':
                    case 'date':
                        $card_data['meta'][] = [
                            'type' => 'date',
                            'icon' => 'fa-calendar',
                            'content' => $cell['content']
                        ];
                        break;

                    case 'ext.':
                    case 'extension':
                        $card_data['meta'][] = [
                            'type' => 'extension',
                            'icon' => 'fa-file-o',
                            'content' => $cell['content']
                        ];
                        break;

                    case 'size':
                        $card_data['meta'][] = [
                            'type' => 'size',
                            'icon' => 'fa-hdd-o',
                            'content' => $cell['content']
                        ];
                        break;

                    case 'uploader':
                        $card_data['meta'][] = [
                            'type' => 'uploader',
                            'icon' => 'fa-user',
                            'content' => $cell['content']
                        ];
                        break;

                    case 'assigned':
                        $card_data['status'][] = $this->parseAssignmentStatus($cell['content']);
                        break;

                    case 'public permissions':
                        $card_data['status'][] = $this->parsePublicStatus($cell['content']);
                        break;

                    case 'expiry':
                        $card_data['status'][] = $this->parseExpiryStatus($cell['content']);
                        break;

                    case 'download count':
                    case 'total downloads':
                        if (!empty($cell['content'])) {
                            $card_data['status'][] = $this->parseDownloadCount($cell['content']);
                        }
                        break;

                    case 'card_download_count':
                        // Always include download count for card view
                        $card_data['status'][] = $this->parseDownloadCount($cell['content']);
                        break;

                    case 'encryption':
                        // Skip encryption column in card view (already shown in title badge)
                        break;

                    case 'status':
                        $card_data['status'][] = $this->parseVisibilityStatus($cell['content']);
                        break;

                    default:
                        // Other content
                        $hide_on_mobile = isset($column['hide']) ? $column['hide'] : '';
                        $card_data['content'][] = [
                            'label' => $column_label,
                            'content' => $cell['content'],
                            'hide' => $hide_on_mobile
                        ];
                        break;
                }
            }
            $cell_index++;
        }

        // Render card structure
        $this->renderCard($card_data, $has_checkbox, $checkbox_content);
    }

    /**
     * Render complete card with new structure
     */
    private function renderCard($data, $has_checkbox, $checkbox_content)
    {
        // Extract file information for enhanced preview
        $file_info = $this->extractFileInfo($data);

        // Checkbox
        if ($has_checkbox) {
            $this->contents .= '<div class="card-checkbox-container">' . $checkbox_content . '</div>' . "\n";
        }

        // File Preview Section (Enhanced)
        $this->contents .= '<div class="card-preview">' . "\n";
        $this->contents .= $this->generateFilePreview($file_info);
        $this->contents .= '</div>' . "\n";

        // Card Header (Title only)
        $this->contents .= '<div class="card-header">' . "\n";

        // Title
        if (!empty($data['title'])) {
            $this->contents .= '<h3 class="card-title">' . $data['title'] . '</h3>' . "\n";
        }

        $this->contents .= '</div>' . "\n"; // End card-header

        // Card Content
        $this->contents .= '<div class="card-content">' . "\n";

        // Meta information (date, size, etc.)
        if (!empty($data['meta'])) {
            $this->contents .= '<div class="card-meta">' . "\n";
            foreach ($data['meta'] as $meta) {
                if ($meta['type'] === 'extension') {
                    // Skip extension here since it's now in the preview badge
                    continue;
                }
                $class = isset($meta['type']) ? ' ' . $meta['type'] : '';
                $this->contents .= '<div class="meta-item' . $class . '">';
                if (isset($meta['icon'])) {
                    $this->contents .= '<i class="fa ' . $meta['icon'] . '"></i>';
                }
                $this->contents .= $meta['content'] . '</div>' . "\n";
            }
            $this->contents .= '</div>' . "\n";
        }

        // Status indicators
        if (!empty($data['status'])) {
            $this->contents .= '<div class="status-indicators">' . "\n";
            foreach ($data['status'] as $status) {
                if (!empty($status)) {
                    $this->contents .= $status . "\n";
                }
            }
            $this->contents .= '</div>' . "\n";
        }

        // Additional information (exclude description and categories)
        if (!empty($data['content'])) {
            $filtered_content = array_filter($data['content'], function($item) {
                $label = strtolower($item['label'] ?? '');
                return !in_array($label, ['description', 'categories', 'category']);
            });

            if (!empty($filtered_content)) {
                $this->contents .= '<div class="additional-info">' . "\n";
                foreach ($filtered_content as $item) {
                    $hide_class = '';
                    if (!empty($item['hide'])) {
                        $hide_class = ' hide-on-' . str_replace(',', ' hide-on-', $item['hide']);
                    }

                    $this->contents .= '<div class="info-item' . $hide_class . '">' . "\n";
                    if (!empty($item['label'])) {
                        $this->contents .= '<span class="info-label">' . $item['label'] . ':</span>';
                    }
                    $this->contents .= '<span class="info-value">' . $item['content'] . '</span>' . "\n";
                    $this->contents .= '</div>' . "\n";
                }
                $this->contents .= '</div>' . "\n";
            }
        }

        $this->contents .= '</div>' . "\n"; // End card-content

        // Actions (Enhanced with More Info button)
        $this->contents .= '<div class="card-actions">' . "\n";

        // Add Details button (shorter name)
        $this->contents .= '<button type="button" class="btn btn-outline-primary btn-sm file-info-btn" data-file-id="' . $file_info['id'] . '" title="' . __('View file details', 'cftp_admin') . '">' . "\n";
        $this->contents .= '<i class="fa fa-info-circle"></i> ' . __('Details', 'cftp_admin') . "\n";
        $this->contents .= '</button>' . "\n";

        // Original actions
        if (!empty($data['actions'])) {
            foreach ($data['actions'] as $action) {
                $this->contents .= $action . "\n";
            }
        }

        $this->contents .= '</div>' . "\n"; // End card-actions

        $this->contents .= '</div>' . "\n"; // End card
    }

    /**
     * Parse assignment status and convert to icon
     */
    private function parseAssignmentStatus($content)
    {
        $content = strip_tags($content);
        if (strpos($content, 'Yes') !== false || strpos($content, 'success') !== false) {
            return '<div class="status-icon assigned-yes"><i class="fa fa-users"></i> Assigned</div>';
        } else {
            return '<div class="status-icon assigned-no"><i class="fa fa-exclamation-triangle"></i> Unassigned</div>';
        }
    }

    /**
     * Parse public status and convert to icon
     */
    private function parsePublicStatus($content)
    {
        if (strpos($content, 'Download') !== false || strpos($content, 'btn-primary') !== false) {
            // Extract data attributes from the original content
            $data_attrs = '';

            // Extract data-public-url
            if (preg_match('/data-public-url="([^"]*)"/', $content, $matches)) {
                $data_attrs .= ' data-public-url="' . htmlspecialchars($matches[1]) . '"';
            }

            // Extract data-title
            if (preg_match('/data-title="([^"]*)"/', $content, $matches)) {
                $data_attrs .= ' data-title="' . htmlspecialchars($matches[1]) . '"';
            }

            return '<div class="status-icon public-yes public_link" data-type="file"' . $data_attrs . '><i class="fa fa-globe"></i> Public</div>';
        } else {
            return '<div class="status-icon public-no"><i class="fa fa-lock"></i> Private</div>';
        }
    }

    /**
     * Parse expiry status and convert to icon
     */
    private function parseExpiryStatus($content)
    {
        $content_text = strip_tags($content);
        $content_lower = strtolower($content_text);

        if (strpos($content_lower, 'expired') !== false) {
            return '<div class="status-icon expires-expired"><i class="fa fa-times-circle"></i> Expired</div>';
        } elseif (strpos($content_lower, 'expires') !== false) {
            // Extract the date from the content (everything after "Expires ")
            $date_part = trim(str_ireplace('Expires', '', $content_text));
            return '<div class="status-icon expires-soon"><i class="fa fa-clock-o"></i> Expires ' . $date_part . '</div>';
        } else {
            return '<div class="status-icon expires-never"><i class="fa fa-infinity"></i> No expiry</div>';
        }
    }

    /**
     * Parse download count and convert to icon
     */
    private function parseDownloadCount($content)
    {
        // Extract number from content
        preg_match('/(\d+)/', strip_tags($content), $matches);
        $count = isset($matches[1]) ? intval($matches[1]) : 0;

        $class = $count > 0 ? 'downloads' : 'downloads no-downloads';
        $icon = $count > 0 ? 'fa-download' : 'fa-download';
        $text = $count . ' downloads';

        if (strpos($content, '<a') !== false) {
            // Extract href from original link
            preg_match('/href="([^"]*)"/', $content, $href_matches);
            $href = isset($href_matches[1]) ? $href_matches[1] : '#';
            return '<a href="' . $href . '" class="status-icon ' . $class . '"><i class="fa ' . $icon . '"></i> ' . $text . '</a>';
        } else {
            return '<div class="status-icon ' . $class . '"><i class="fa ' . $icon . '"></i> ' . $text . '</div>';
        }
    }

    /**
     * Parse visibility status and convert to icon
     */
    private function parseVisibilityStatus($content)
    {
        $content_lower = strtolower(strip_tags($content));
        if (strpos($content_lower, 'hidden') !== false) {
            return '<div class="status-icon hidden"><i class="fa fa-eye-slash"></i> Hidden</div>';
        } else {
            return '<div class="status-icon visible"><i class="fa fa-eye"></i> Visible</div>';
        }
    }

    /**
     * Enhance preview content with better styling
     */
    /**
     * Extract file information from card data for enhanced preview
     */
    private function extractFileInfo($data)
    {
        $file_info = [
            'id' => '',
            'title' => $data['title'] ?? '',
            'extension' => '',
            'filename' => '',
            'preview_url' => '',
            'is_image' => false
        ];

        // Extract file ID from current row data (more reliable)
        if (isset($this->current_row_data['data-attributes']['file-id'])) {
            $file_info['id'] = $this->current_row_data['data-attributes']['file-id'];
        } elseif (isset($this->current_row_data['id'])) {
            $file_info['id'] = $this->current_row_data['id'];
        } elseif (!empty($data['actions'])) {
            // Fallback: Extract from actions
            foreach ($data['actions'] as $action) {
                if (preg_match('/files-edit\.php\?ids=(\d+)/', $action, $matches) ||
                    preg_match('/process\.php\?.*id=(\d+)/', $action, $matches)) {
                    $file_info['id'] = $matches[1];
                    break;
                }
            }
        }

        // Extract extension from meta data
        if (!empty($data['meta'])) {
            foreach ($data['meta'] as $meta) {
                if ($meta['type'] === 'extension') {
                    $file_info['extension'] = strtolower(strip_tags($meta['content']));
                    break;
                }
            }
        }

        // Extract extension from title as fallback
        if (empty($file_info['extension'])) {
            $file_info['extension'] = strtolower(pathinfo($file_info['title'], PATHINFO_EXTENSION));
        }

        // Check if it's an image
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $file_info['is_image'] = in_array($file_info['extension'], $image_extensions);

        // Look for existing preview/thumbnail in original preview data
        if (!empty($data['preview'])) {
            if (preg_match('/<img[^>]+src="([^"]+)"/', $data['preview'], $matches)) {
                $file_info['preview_url'] = $matches[1];
            }
        }

        return $file_info;
    }

    /**
     * Generate enhanced file preview with image thumbnails or extension badges
     */
    private function generateFilePreview($file_info)
    {
        $preview_html = '<div class="file-preview-container">';

        if ($file_info['is_image']) {
            // First check for existing preview URL
            if (!empty($file_info['preview_url'])) {
                $preview_html .= '<div class="image-preview">';
                $preview_html .= '<img src="' . $file_info['preview_url'] . '" alt="' . htmlspecialchars($file_info['title']) . '" class="file-thumbnail" />';
                $preview_html .= '</div>';
            } elseif (!empty($file_info['id'])) {
                // Try to get/create thumbnail for images
                $thumbnail_url = $this->getFileThumbnail($file_info['id']);

                if ($thumbnail_url) {
                    $preview_html .= '<div class="image-preview">';
                    $preview_html .= '<img src="' . $thumbnail_url . '" alt="' . htmlspecialchars($file_info['title']) . '" class="file-thumbnail" />';
                    $preview_html .= '</div>';
                } else {
                    // Fallback to extension badge for images without thumbnails
                    $preview_html .= $this->generateExtensionBadge($file_info['extension']);
                }
            } else {
                // No file ID, use extension badge
                $preview_html .= $this->generateExtensionBadge($file_info['extension']);
            }
        } else {
            // Non-image files get extension badges
            $preview_html .= $this->generateExtensionBadge($file_info['extension']);
        }

        $preview_html .= '</div>';
        return $preview_html;
    }

    /**
     * Generate extension badge for non-image files
     */
    private function generateExtensionBadge($extension)
    {
        $extension = strtoupper($extension);
        $color_class = $this->getExtensionColorClass($extension);

        return '<div class="extension-badge ' . $color_class . '">' .
               '<span class="extension-text">' . $extension . '</span>' .
               '</div>';
    }

    /**
     * Get color class for extension badge
     */
    private function getExtensionColorClass($extension)
    {
        $color_map = [
            'PDF' => 'badge-red',
            'DOC' => 'badge-blue', 'DOCX' => 'badge-blue',
            'XLS' => 'badge-green', 'XLSX' => 'badge-green',
            'PPT' => 'badge-orange', 'PPTX' => 'badge-orange',
            'ZIP' => 'badge-purple', 'RAR' => 'badge-purple', '7Z' => 'badge-purple',
            'MP3' => 'badge-pink', 'WAV' => 'badge-pink', 'FLAC' => 'badge-pink',
            'MP4' => 'badge-indigo', 'AVI' => 'badge-indigo', 'MOV' => 'badge-indigo',
            'TXT' => 'badge-gray', 'RTF' => 'badge-gray',
        ];

        return $color_map[$extension] ?? 'badge-default';
    }

    /**
     * Get thumbnail URL for a file
     */
    private function getFileThumbnail($file_id)
    {
        if (empty($file_id)) {
            return null;
        }

        // Try to use existing thumbnail generation with higher quality
        try {
            $file = new \ProjectSend\Classes\Files($file_id);
            if ($file && $file->exists()) {
                // For external files, we can't generate thumbnails since files aren't local
                if ($file->storage_type !== 'local') {
                    return null; // Will fall back to extension badge
                }

                // Use larger dimensions and higher quality for card previews
                $thumbnail = make_thumbnail($file->full_path, 'proportional', 300, 300, 90);
                if (!empty($thumbnail['thumbnail']['url'])) {
                    return $thumbnail['thumbnail']['url'];
                }
            }
        } catch (Exception $e) {
            // Fallback - return null to use extension badge
        }

        return null;
    }

    private function enhancePreviewContent($content)
    {
        // Legacy method - kept for compatibility
        return $content;
    }

    /**
     * Render the complete card list
     */
    /**
     * Check if a cell content is an action button
     */
    private function isActionCell($content)
    {
        // Check for common action button patterns
        $action_patterns = [
            'files-edit.php',           // Edit button
            'btn btn-primary',          // Primary buttons
            'btn btn-danger',           // Delete buttons
            'btn btn-success',          // Success buttons
        ];

        foreach ($action_patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public function render()
    {
        $this->contents .= '</div>' . "\n"; // Close card-list-items
        $this->contents .= '</div>' . "\n"; // Close card-list
        return $this->contents;
    }
}