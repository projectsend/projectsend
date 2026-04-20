<?php
/*
Public files template for Retro 90s theme
*/
$ld = 'retro90s_template';

$count = $files['pagination']['total'];

if ($count == 0) {
    if (isset($_GET['search'])) {
        $no_results_message = __('Your search keywords returned no results.', 'retro90s_template');
    } else {
        $no_results_message = __('There are no files available.', 'retro90s_template');
    }
}

$groups = get_groups([
    'public' => true,
]);

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

$page_title = ($mode == 'files') ? __('Public Files', 'retro90s_template') : sprintf(__('Files in group: %s', 'retro90s_template'), $group_props['name']);

require_once dirname(__FILE__) . '/csv_helper.php'; // include 90s entertainment CSV helper

// Get random entertainment content for this page load
$random_movies = getRandomMovies(3);
$random_music = getRandomMusic(3);
$random_videogames = getRandomVideoGames(3);
?>
<!DOCTYPE html>
<html lang="<?php echo SITE_LANG; ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_output($page_title . ' - ' . get_option('this_install_title')); ?></title>
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
                                            <?php if ($logo_file_info): ?>
                                                <?php echo get_branding_layout(true); ?>
                                            <?php else: ?>
                                                ★ <?php echo html_output($page_title); ?> ★
                                            <?php endif; ?>
                                        </b>
                                    </font>
                                </td>
                                <td align="right">
                                    <font face="Arial, sans-serif" color="#00ffff" size="2">
                                        <blink>PUBLIC!</blink>
                                    </font>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <?php require_once dirname(__FILE__) . '/menu.php'; ?>

            <!-- Main Content Table -->
            <!-- Files Section -->
            <div align="center" style="margin-bottom: 20px;">
                <table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
                    <tr>
                        <td bgcolor="#c0c0c0">
                            <table width="100%" cellpadding="2" cellspacing="1" border="0">
                                <tr bgcolor="<?php echo htmlspecialchars($header_bg_color); ?>">
                                    <td>
                                        <font face="Arial, sans-serif" color="#ffff00" size="3">
                                            <b>📁 <?php echo html_output($page_title); ?></b>
                                            <?php if ($count > 0): ?>
                                                <font size="2">(<?php echo $count; ?> files)</font>
                                            <?php endif; ?>
                                        </font>
                                    </td>
                                </tr>
                                <tr bgcolor="#c0c0c0">
                                    <td>
                                        <!-- Group Description -->
                                        <?php if ($mode == 'group' && !empty($group_props['description'])): ?>
                                        <div style="background: #ffffcc; padding: 8px; margin-bottom: 8px;">
                                            <font face="Arial, sans-serif" color="#000080" size="2">
                                                <b><?php _e('About this group:', 'retro90s_template'); ?></b><br>
                                                <?php echo htmlentities_allowed($group_props['description']); ?>
                                            </font>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Group Filter -->
                                        <?php if (!empty($groups) && $mode !== 'group'): ?>
                                        <div style="background: #ffccff; padding: 8px; margin-bottom: 8px;">
                                            <font face="Arial, sans-serif" color="#000080" size="2">
                                                <b>🔍 Filter by group:</b>&nbsp;
                                                <form action="<?php echo BASE_URI; ?>public.php" method="get" style="display: inline;">
                                                    <select name="group" onchange="this.form.submit()" style="background: #ffffff; color: #000080; font-family: Arial;">
                                                        <option value=""><?php _e('All files', 'retro90s_template'); ?> (<?php echo count_public_files_not_in_groups(); ?>)</option>
                                                        <?php foreach ($groups as $group): ?>
                                                            <option value="<?php echo $group['id']; ?>"
                                                                    data-token="<?php echo $group['public_token']; ?>"
                                                                    <?php if (isset($_GET['group']) && $_GET['group'] == $group['id']) echo 'selected'; ?>>
                                                                <?php echo html_output($group['name']); ?> (<?php echo count_public_files_in_group($group['id']); ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            </font>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Search Box -->
                                        <?php if ($count > 0): ?>
                                        <div style="background: #ccffcc; padding: 8px; margin-bottom: 8px;">
                                            <font face="Arial, sans-serif" color="#000080" size="2">
                                                <b>🔍 Search files:</b>&nbsp;
                                                <form action="<?php echo BASE_URI; ?>public.php" method="get" style="display: inline;">
                                                    <?php if (isset($_GET['group'])): ?>
                                                        <input type="hidden" name="group" value="<?php echo htmlspecialchars($_GET['group']); ?>">
                                                    <?php endif; ?>
                                                    <?php if (isset($_GET['token'])): ?>
                                                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                                                    <?php endif; ?>
                                                    <input type="text"
                                                           name="search"
                                                           value="<?php echo isset($_GET['search']) ? html_output($_GET['search']) : ''; ?>"
                                                           placeholder="Search for files..."
                                                           style="background: #ffffff; color: #000080; font-family: Arial; padding: 2px;">
                                                    <input type="submit" value="Search!" style="background: #800080; color: #ffffff; font-family: Arial; padding: 2px 8px;">
                                                </form>
                                            </font>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Files List Table -->
                                        <?php if ($count > 0): ?>
                                        <table width="100%" cellpadding="3" cellspacing="1" border="0">
                                            <tr bgcolor="<?php echo htmlspecialchars($header_bg_color); ?>">
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
                                            foreach ($files['files_ids'] as $file_id):
                                                $file = new \ProjectSend\Classes\Files($file_id);
                                                $is_expired = $file->expired;
                                                $bg_color = ($row_color % 2 == 0) ? '#e0e0e0' : '#f0f0f0';
                                                if ($is_expired) $bg_color = '#ffcccc';
                                                $row_color++;
                                            ?>
                                            <tr bgcolor="<?php echo $bg_color; ?>">
                                                <td width="60" align="center">
                                            <?php if (!$is_expired && $file->isImage()): ?>
                                                <?php $thumbnail = make_thumbnail($file->full_path, null, 32, 32); ?>
                                                <?php if (!empty($thumbnail['thumbnail']['url'])): ?>
                                                    <img src="<?php echo $thumbnail['thumbnail']['url']; ?>"
                                                         alt="<?php echo html_output($file->title); ?>"
                                                         width="32" height="32" border="1" style="border-color: #000080;">
                                                <?php else: ?>
                                                    <font face="Arial, sans-serif" color="#000080" size="4">📄</font>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php
                                                // Get appropriate icon based on file extension
                                                $ext_icons = [
                                                    'pdf' => '📕', 'doc' => '📘', 'docx' => '📘', 'xls' => '📗', 'xlsx' => '📗',
                                                    'ppt' => '📙', 'pptx' => '📙', 'txt' => '📄', 'zip' => '📦', 'rar' => '📦',
                                                    'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'bmp' => '🖼️',
                                                    'mp3' => '🎵', 'wav' => '🎵', 'mp4' => '🎬', 'avi' => '🎬', 'mov' => '🎬'
                                                ];
                                                $icon = isset($ext_icons[strtolower($file->extension)]) ? $ext_icons[strtolower($file->extension)] : '📄';
                                                ?>
                                                <font face="Arial, sans-serif" size="4"><?php echo $icon; ?></font>
                                            <?php endif; ?>
                                                </td>
                                                <td>
                                                    <font face="Arial, sans-serif" color="#000080" size="2">
                                                        <b><?php echo html_output($file->title); ?></b><br>
                                                        <?php if ($file->title != $file->filename_original): ?>
                                                            <font size="1"><i><?php echo html_output($file->filename_original); ?></i></font><br>
                                                        <?php endif; ?>
                                                        <?php if (!empty($file->description)): ?>
                                                            <font size="1"><?php echo format_description($file->description); ?></font><br>
                                                        <?php endif; ?>
                                                    </font>
                                                </td>
                                                <td width="80" align="center">
                                                    <font face="Arial, sans-serif" color="#000080" size="1">
                                                        <?php echo $file->size_formatted; ?>
                                                    </font>
                                                </td>
                                                <td width="100" align="center">
                                                    <font face="Arial, sans-serif" color="#000080" size="1">
                                                        <?php echo format_date($file->uploaded_date); ?>
                                                    </font>
                                                </td>
                                                <td width="120" align="center">
                                            <?php if (!$is_expired): ?>
                                                <?php if (get_option('public_listing_use_download_link') == 1 && $file->isPublic()): ?>
                                                <a href="<?php echo $file->download_link; ?>" target="_blank" class="retro-button">
                                                    <font face="Arial, sans-serif" size="1"><b>📥 DOWNLOAD</b></font>
                                                </a>
                                                <?php endif; ?>
                                                <br>
                                                <a href="<?php echo $file->public_url; ?>" target="_blank" class="retro-button">
                                                    <font face="Arial, sans-serif" size="1"><b>🔗 LINK</b></font>
                                                </a>
                                            <?php else: ?>
                                                <font face="Arial, sans-serif" color="#ff0000" size="2">
                                                    <b>❌ EXPIRED</b>
                                                </font>
                                            <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>

                                            <!-- Pagination -->
                                            <?php if ($files['pagination']['total'] > $per_page): ?>
                                            <tr bgcolor="#ccccff">
                                                <td colspan="5" align="center">
                                        <font face="Arial, sans-serif" color="#000080" size="2">
                                            <b>📄 Pages:</b>&nbsp;
                                            <?php
                                            $pagination = new \ProjectSend\Classes\Layout\Pagination;
                                            echo $pagination->make([
                                                'link' => 'public.php',
                                                'current' => $pagination_page,
                                                'item_count' => $files['pagination']['total'],
                                                'items_per_page' => $per_page,
                                            ]);
                                            ?>
                                                    </font>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>

                                        <?php else: ?>
                                        <!-- Empty State -->
                                        <div align="center" style="padding: 20px;">
                                            <font face="Arial, sans-serif" color="#800000" size="3">
                                                <b>📂 <?php echo $no_results_message; ?> 📂</b><br><br>
                                                <?php if (!empty($_GET['search'])): ?>
                                                    <font size="2">Try different search terms or
                                                    <a href="<?php echo BASE_URI; ?>public.php" style="color: #000080;">[browse all files]</a></font>
                                                <?php endif; ?>
                                            </font>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<!-- JavaScript for group selection -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const groupSelect = document.querySelector('select[name="group"]');
    if (groupSelect) {
        groupSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const form = this.closest('form');

            if (this.value && selectedOption.dataset.token) {
                // Add token as hidden input
                let tokenInput = form.querySelector('input[name="token"]');
                if (!tokenInput) {
                    tokenInput = document.createElement('input');
                    tokenInput.type = 'hidden';
                    tokenInput.name = 'token';
                    form.appendChild(tokenInput);
                }
                tokenInput.value = selectedOption.dataset.token;
            } else {
                // Remove token input
                const tokenInput = form.querySelector('input[name="token"]');
                if (tokenInput) {
                    tokenInput.remove();
                }
            }

            form.submit();
        });
    }
});
</script>

<?php require_once dirname(__FILE__) . '/footer.php'; ?>

<?php render_custom_assets('body_bottom'); ?>
</body>
</html>