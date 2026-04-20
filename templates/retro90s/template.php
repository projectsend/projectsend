<?php
/*
Template name: Retro 90s
URI: https://www.projectsend.org/templates/retro90s
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: A nostalgic throwback to the golden era of the web - complete with tables, marquees, and that classic 90s aesthetic!
*/

$ld = 'retro90s_template'; // specify the language domain for this template

define('TEMPLATE_RESULTS_PER_PAGE', get_option('pagination_results_per_page'));
define('TEMPLATE_THUMBNAILS_WIDTH', '80');
define('TEMPLATE_THUMBNAILS_HEIGHT', '60');

$filter_by_category = isset($_GET['category']) ? $_GET['category'] : null;

$current_url = get_form_action_with_existing_parameters('index.php');

include_once ROOT_DIR . '/templates/common.php'; // include the required functions for every template

$window_title = __('File downloads', 'retro90s_template');

$page_id = 'retro90s_template';

$body_class = array('template', 'retro90s-template', 'hide_title');

// Flash errors
if (!$count) {
    if (isset($no_results_error)) {
        switch ($no_results_error) {
            case 'search':
                $flash->error(__('Your search keywords returned no results.', 'cftp_admin'));
                break;
            case 'filter':
                $flash->error(__('The filters you selected returned no results.', 'cftp_admin'));
                break;
        }
    } else {
        $flash->warning(__('There are no files available.', 'cftp_admin'));
    }
}

// Header buttons
if (current_user_can_upload()) {
    $header_action_buttons = [
        [
            'url' => BASE_URI.'upload.php',
            'label' => __('Upload file', 'cftp_admin'),
        ],
    ];
}

// Search + filters bar data
$search_form_action = 'index.php';
$filters_form = [
    'action' => '',
    'items' => [],
];

if (!empty($cat_ids)) {
    $selected_parent = (isset($_GET['category'])) ? [$_GET['category']] : [];
    $category_filter = [];
    $generate_categories_options = generate_categories_options($get_categories['arranged'], 0, $selected_parent, 'include', $cat_ids);
    $format_categories_options = format_categories_options($generate_categories_options);
    foreach ($format_categories_options as $key => $category) {
        $category_filter[$category['id']] = $category['label'];
    }
    $filters_form['items']['category'] = [
        'current' => (isset($_GET['category'])) ? $_GET['category'] : null,
        'placeholder' => [
            'value' => '0',
            'label' => __('All categories', 'cftp_admin')
        ],
        'options' => $category_filter,
    ];
}

// Results count and form actions
$elements_found_count = (isset($count_for_pagination)) ? $count_for_pagination : 0;
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'zip' => __('Download zipped', 'cftp_admin'),
];

?>
<!DOCTYPE html>
<html lang="<?php echo SITE_LANG; ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_output( $client_info['name'].' | '.$window_title . ' &raquo; ' . SYSTEM_NAME ); ?></title>
    <?php meta_favicon(); ?>

    <link rel="stylesheet" href="<?php echo $this_template_url; ?>font-awesome-4.6.3/css/font-awesome.min.css">
    <link rel="stylesheet" media="all" type="text/css" href="<?php echo $this_template_url; ?>main.css" />

    <?php
    // Dynamic CSS for theme settings
    $retro_color_scheme = get_theme_option('retro90s', 'retro_color_scheme', 'neon');
    $show_grid_animation = get_theme_option('retro90s', 'show_grid_animation', true);
    $header_bg_color = get_theme_option('retro90s', 'header_background_color', '#000080');
    ?>
    <style>
        <?php if (!$show_grid_animation): ?>
        /* Disable animations when setting is off */
        * {
            animation: none !important;
            -webkit-animation: none !important;
            -moz-animation: none !important;
            -o-animation: none !important;
            -ms-animation: none !important;
        }
        <?php endif; ?>

        <?php
        // Color scheme variables
        $color_schemes = [
            'neon' => ['primary' => '#ff00ff', 'secondary' => '#00ffff', 'accent' => '#ffff00'],
            'sunset' => ['primary' => '#ff4500', 'secondary' => '#800080', 'accent' => '#ffd700'],
            'classic' => ['primary' => '#0000ff', 'secondary' => '#ffff00', 'accent' => '#ff0000'],
            'matrix' => ['primary' => '#00ff00', 'secondary' => '#000000', 'accent' => '#008000']
        ];

        $colors = $color_schemes[$retro_color_scheme] ?? $color_schemes['neon'];
        ?>

        /* Color scheme overrides */
        .retro-primary { color: <?php echo $colors['primary']; ?> !important; }
        .retro-secondary { color: <?php echo $colors['secondary']; ?> !important; }
        .retro-accent { color: <?php echo $colors['accent']; ?> !important; }

        /* Apply color scheme to key elements */
        marquee { color: <?php echo $colors['primary']; ?> !important; }
        blink { color: <?php echo $colors['accent']; ?> !important; }

        /* Retro glow effect with current color scheme */
        .retro-glow {
            text-shadow: 0 0 5px <?php echo $colors['primary']; ?>, 0 0 10px <?php echo $colors['primary']; ?>, 0 0 15px <?php echo $colors['primary']; ?>;
        }
    </style>

    <script src="<?php echo $this_template_url; ?>js/jquery.1.11.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/js-cookie@rc/dist/js.cookie.min.js"></script>

    <script>
        window.base_url = '<?php echo BASE_URI; ?>';
    </script>

    <?php render_custom_assets('head'); ?>
</head>

<body style="background:url('<?php echo $this_template_url; ?>images/pattern.jpeg')">
    <?php render_custom_assets('body_top'); ?>

<!-- Main Container Table -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#c0c0c0">
    <tr>
        <td>
            <!-- Header Table -->
            <table width="100%" cellpadding="8" cellspacing="2" border="0" bgcolor="#008080">
                <tr>
                    <td bgcolor="#c0c0c0">
                        <table width="100%" cellpadding="4" cellspacing="1" border="0">
                            <tr bgcolor="<?php echo htmlspecialchars($header_bg_color); ?>">
                                <td>
                                    <font face="Arial, sans-serif" color="#ffff00" size="5">
                                        <b>
                                            <?php if ($logo_file_info['exists'] === true) { ?>
                                                <?php echo get_branding_layout(true); ?>
                                            <?php } else { ?>
                                                ★ <?php echo SYSTEM_NAME; ?> ★
                                            <?php } ?>
                                        </b>
                                    </font>
                                </td>
                                <td align="right">
                                    <font face="Arial, sans-serif" color="#00ffff" size="2">
                                        <blink>ONLINE!</blink>
                                    </font>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td bgcolor="#c0c0c0">
                        <marquee behavior="scroll" direction="left" bgcolor="#ffff00">
                            <font face="Arial, sans-serif" color="#ff0000" size="3">
                                <b>Welcome <?php echo htmlspecialchars($client_info['name']); ?>! You have <?php echo $elements_found_count; ?> files available for download!</b>
                            </font>
                        </marquee>
                    </td>
                </tr>
            </table>

            <?php require_once dirname(__FILE__) . '/menu.php'; ?>

            <!-- Files Table -->
            <form action="" name="files_list" method="get" class="batch_actions">
                <table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
                    <tr>
                        <td bgcolor="#c0c0c0">
                            <?php if (isset($count) && $count > 0) { ?>
                                <!-- Files Header -->
                                <table width="100%" cellpadding="2" cellspacing="1" border="0">
                                    <tr bgcolor="#808080">
                                        <td>
                                            <font face="Arial, sans-serif" color="#ffffff" size="2">
                                                <b>💾 YOUR FILES (<?php echo $elements_found_count; ?> total)</b>
                                            </font>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Bulk Actions -->
                                <table width="100%" cellpadding="2" cellspacing="1" border="0" id="bulk-actions-table" style="display: none;">
                                    <tr bgcolor="#ffff00">
                                        <td>
                                            <font face="Arial, sans-serif" size="2">
                                                <b>Selected: <span id="selected-count">0</span> files</b>
                                                <input type="button" value="Clear All" onclick="clearSelection()" />
                                                <input type="button" value="Download Zip" onclick="downloadSelected()" />
                                            </font>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Files List Table -->
                                <table width="100%" cellpadding="3" cellspacing="1" border="0">
                                    <tr bgcolor="<?php echo htmlspecialchars($header_bg_color); ?>">
                                        <td width="30">
                                            <font face="Arial, sans-serif" color="#ffffff" size="1">
                                                <b>✓</b>
                                            </font>
                                        </td>
                                        <td width="60">
                                            <font face="Arial, sans-serif" color="#ffffff" size="1">
                                                <b>TYPE</b>
                                            </font>
                                        </td>
                                        <td>
                                            <font face="Arial, sans-serif" color="#ffffff" size="1">
                                                <b>FILE NAME</b>
                                            </font>
                                        </td>
                                        <td width="80">
                                            <font face="Arial, sans-serif" color="#ffffff" size="1">
                                                <b>SIZE</b>
                                            </font>
                                        </td>
                                        <td width="100">
                                            <font face="Arial, sans-serif" color="#ffffff" size="1">
                                                <b>DATE</b>
                                            </font>
                                        </td>
                                        <td width="120">
                                            <font face="Arial, sans-serif" color="#ffffff" size="1">
                                                <b>ACTIONS</b>
                                            </font>
                                        </td>
                                    </tr>
                                    <?php
                                    $row_color = 0;
                                    foreach ($available_files as $file_id) {
                                        $file = new \ProjectSend\Classes\Files($file_id);
                                        $bg_color = ($row_color % 2 == 0) ? '#e0e0e0' : '#f0f0f0';
                                        $row_color++;

                                        // File type icon
                                        $file_icon = '📄';
                                        if ($file->isImage()) {
                                            $file_icon = '🖼️';
                                        } elseif (in_array(strtolower($file->extension), ['pdf'])) {
                                            $file_icon = '📕';
                                        } elseif (in_array(strtolower($file->extension), ['doc', 'docx'])) {
                                            $file_icon = '📝';
                                        } elseif (in_array(strtolower($file->extension), ['xls', 'xlsx'])) {
                                            $file_icon = '📊';
                                        } elseif (in_array(strtolower($file->extension), ['zip', 'rar', '7z'])) {
                                            $file_icon = '📦';
                                        } elseif (in_array(strtolower($file->extension), ['mp3', 'wav', 'ogg'])) {
                                            $file_icon = '🎵';
                                        } elseif (in_array(strtolower($file->extension), ['mp4', 'avi', 'mov'])) {
                                            $file_icon = '🎬';
                                        }

                                        if ($file->expired) {
                                            $bg_color = '#ffcccc';
                                        }
                                        ?>
                                        <tr bgcolor="<?php echo $bg_color; ?>">
                                            <td align="center">
                                                <?php if (!$file->expired) { ?>
                                                    <input type="checkbox" name="files[]" value="<?php echo $file->id; ?>" class="batch_checkbox" onchange="updateBulkActions()" />
                                                <?php } else { ?>
                                                    ❌
                                                <?php } ?>
                                            </td>
                                            <td align="center">
                                                <font size="3"><?php echo $file_icon; ?></font><br>
                                                <font face="Arial, sans-serif" size="1">
                                                    <b><?php echo strtoupper($file->extension); ?></b>
                                                </font>
                                            </td>
                                            <td>
                                                <font face="Arial, sans-serif" size="2">
                                                    <b><?php echo htmlspecialchars($file->title); ?></b>
                                                    <?php if ($file->title != $file->filename_original) { ?>
                                                        <br><font size="1" color="#666666">(<?php echo htmlspecialchars($file->filename_original); ?>)</font>
                                                    <?php } ?>
                                                    <?php if (!empty($file->description)) { ?>
                                                        <br><font size="1"><?php echo format_description($file->description); ?></font>
                                                    <?php } ?>
                                                    <?php if ($file->expires == '1') { ?>
                                                        <br>
                                                        <?php if ($file->expired) { ?>
                                                            <font color="#ff0000" size="1"><b>⚠️ EXPIRED</b></font>
                                                        <?php } else { ?>
                                                            <font color="#ff6600" size="1"><b>⏰ Expires: <?php echo date('M j, Y', strtotime($file->expiry_date)); ?></b></font>
                                                        <?php } ?>
                                                    <?php } ?>
                                                </font>
                                            </td>
                                            <td align="center">
                                                <font face="Arial, sans-serif" size="1">
                                                    <b><?php echo $file->size_formatted; ?></b>
                                                </font>
                                            </td>
                                            <td align="center">
                                                <font face="Arial, sans-serif" size="1">
                                                    <?php echo date('M j, Y', strtotime($file->uploaded_date)); ?>
                                                </font>
                                            </td>
                                            <td align="center">
                                                <?php if ($file->expired) { ?>
                                                    <font face="Arial, sans-serif" size="1" color="#ff0000">
                                                        <b>EXPIRED</b>
                                                    </font>
                                                <?php } else { ?>
                                                    <a href="<?php echo $file->download_link; ?>" target="_blank" class="retro-button">
                                                        <font face="Arial, sans-serif" size="1"><b>📥 DOWNLOAD</b></font>
                                                    </a>
                                                    <?php if ($file->embeddable) { ?>
                                                        <br>
                                                        <a href="#" class="preview-link retro-button" data-url="<?php echo BASE_URI; ?>process.php?do=get_preview&file_id=<?php echo $file->id; ?>">
                                                            <font face="Arial, sans-serif" size="1"><b>👁️ VIEW</b></font>
                                                        </a>
                                                    <?php } ?>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </table>

                                <!-- Pagination -->
                                <?php
                                if (isset($count) && $count > 0) {
                                    $pagination = new \ProjectSend\Classes\Layout\Pagination;
                                    echo '<br><center>';
                                    echo $pagination->make([
                                        'link' => 'my_files/index.php',
                                        'current' => $pagination_page,
                                        'item_count' => $count_for_pagination,
                                        'items_per_page' => TEMPLATE_RESULTS_PER_PAGE,
                                    ]);
                                    echo '</center>';
                                }
                                ?>

                            <?php } else { ?>
                                <!-- No Files Message -->
                                <table width="100%" cellpadding="20" cellspacing="1" border="0">
                                    <tr bgcolor="#ffff00">
                                        <td align="center">
                                            <font face="Arial, sans-serif" size="4" color="#ff0000">
                                                <b>📁 NO FILES FOUND! 📁</b>
                                            </font>
                                            <br><br>
                                            <font face="Arial, sans-serif" size="2">
                                                <?php echo __('There are currently no files to display.', 'retro90s_template'); ?>
                                            </font>
                                            <?php if (current_user_can_upload()) { ?>
                                                <br><br>
                                                <a href="<?php echo BASE_URI; ?>upload.php" class="retro-button big-button">
                                                    <font face="Arial, sans-serif" size="3"><b>📤 UPLOAD FILES! 📤</b></font>
                                                </a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                </table>
                            <?php } ?>
                        </td>
                    </tr>
                </table>
            </form>
        </td>
    </tr>
</table>

<?php
    $footer_type = 'client';
    require_once dirname(__FILE__) . '/footer.php';
?>

<!-- Preview Modal (90s Style) -->
<div id="previewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999;">
    <div style="display: flex; justify-content: center; align-items: flex-start; height: 100%; padding: 20px; box-sizing: border-box;">
        <table id="previewModalContent" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080" style="width: 95%; max-width: 1200px; max-height: 90%;">
            <tr>
                <td bgcolor="#c0c0c0" style="height: 100%;">
                    <table width="100%" cellpadding="2" cellspacing="1" border="0" style="height: 100%;">
                        <tr bgcolor="#000080">
                            <td>
                                <font face="Arial, sans-serif" color="#ffff00" size="3">
                                    <b>📺 PREVIEW</b>
                                </font>
                            </td>
                            <td align="right">
                                <a href="#" onclick="closePreview()" style="color: #ff0000; text-decoration: none;" title="Close (ESC)">
                                    <font face="Arial, sans-serif" size="2"><b>[CLOSE]</b></font>
                                </a>
                                <font face="Arial, sans-serif" color="#ffffff" size="1">
                                    &nbsp;(ESC or click outside)
                                </font>
                            </td>
                        </tr>
                        <tr bgcolor="#ffffff" style="height: 100%;">
                            <td colspan="2" id="previewContent" style="padding: 5px; height: 100%; vertical-align: top; overflow: auto;">
                                <!-- Preview content will be loaded here -->
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- JavaScript -->
<script src="<?php echo $this_template_url; ?>js/template.js"></script>

<?php render_custom_assets('body_bottom'); ?>

</body>
</html>