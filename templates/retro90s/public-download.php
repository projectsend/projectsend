<?php
/*
Public download template for Retro 90s theme
*/
$ld = 'retro90s_template';

// Get template and branding information
$this_template = get_option('selected_clients_template');
$this_template_url = BASE_URI.'templates/'.$this_template.'/';
$logo_file_info = generate_logo_url();

$page_title = __('File Download', 'retro90s_template') . ' - ' . $file->title;

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
                                                ★ <?php echo __('File Download', 'retro90s_template'); ?> ★
                                            <?php endif; ?>
                                        </b>
                                    </font>
                                </td>
                                <td align="right">
                                    <font face="Arial, sans-serif" color="#00ffff" size="2">
                                        <blink>DOWNLOAD!</blink>
                                    </font>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <?php require_once dirname(__FILE__) . '/menu.php'; ?>

            <!-- Main Content Table -->
            <div align="center" style="margin-bottom: 20px;">
                <!-- Download Content -->
                <?php if ($can_view): ?>
                    <table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
                        <tr>
                            <td bgcolor="#c0c0c0">
                                <!-- File Information Header -->
                                <table width="100%" cellpadding="2" cellspacing="1" border="0">
                                    <tr bgcolor="<?php echo htmlspecialchars($header_bg_color); ?>">
                                        <td>
                                            <font face="Arial, sans-serif" color="#ffff00" size="3">
                                                <b>📁 <?php echo __('FILE INFORMATION', 'retro90s_template'); ?></b>
                                            </font>
                                        </td>
                                    </tr>
                                </table>

                                <!-- File Details Table -->
                                <table width="100%" cellpadding="4" cellspacing="1" border="0" bgcolor="#c0c0c0">
                                    <tr>
                                        <td bgcolor="#ffffcc" align="center" colspan="2">
                                            <font face="Arial, sans-serif" color="#000080" size="4">
                                                <b>📄 <?php echo html_output($file->filename_original); ?></b>
                                            </font>
                                            <?php if ($file->filename_original != $file->title): ?>
                                                <br>
                                                <font face="Arial, sans-serif" color="#800080" size="3">
                                                    <i><?php echo html_output($file->title); ?></i>
                                                </font>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#e0e0e0" width="30%">
                                            <font face="Arial, sans-serif" color="#000080" size="2">
                                                <b>💾 File Size:</b>
                                            </font>
                                        </td>
                                        <td bgcolor="#f0f0f0">
                                            <font face="Arial, sans-serif" color="#000000" size="2">
                                                <?php echo $file->size_formatted; ?>
                                                <?php
                                                if (file_is_image($file->full_path)) {
                                                    $dimensions = $file->getDimensions();
                                                    if (!empty($dimensions)) {
                                                        echo ' • ' . $dimensions['width'] . ' × ' . $dimensions['height'] . ' pixels';
                                                    }
                                                }
                                                ?>
                                            </font>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#e0e0e0">
                                            <font face="Arial, sans-serif" color="#000080" size="2">
                                                <b>🏷️ File Type:</b>
                                            </font>
                                        </td>
                                        <td bgcolor="#f0f0f0">
                                            <font face="Arial, sans-serif" color="#000000" size="2">
                                                <?php echo strtoupper($file->extension); ?> File
                                            </font>
                                        </td>
                                    </tr>
                                    <?php if (!empty($file->description)): ?>
                                    <tr>
                                        <td bgcolor="#e0e0e0">
                                            <font face="Arial, sans-serif" color="#000080" size="2">
                                                <b>📝 Description:</b>
                                            </font>
                                        </td>
                                        <td bgcolor="#f0f0f0">
                                            <font face="Arial, sans-serif" color="#000000" size="2">
                                                <?php echo format_description($file->description); ?>
                                            </font>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>

                                <!-- Preview Section -->
                                <?php if (get_option('public_listing_enable_preview') == 1): ?>
                                    <table width="100%" cellpadding="4" cellspacing="1" border="0" bgcolor="#c0c0c0" style="margin-top: 10px;">
                                        <tr bgcolor="<?php echo htmlspecialchars($header_bg_color); ?>">
                                            <td>
                                                <font face="Arial, sans-serif" color="#ffff00" size="3">
                                                    <b>👁️ <?php echo __('PREVIEW', 'retro90s_template'); ?></b>
                                                </font>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td bgcolor="#f0f0f0" align="center">
                                                <?php if (file_is_image($file->full_path)): ?>
                                                    <?php
                                                    $thumbnail = make_thumbnail($file->full_path, null, 400, 300);
                                                    if (!empty($thumbnail['thumbnail']['url'])): ?>
                                                        <img src="<?php echo $thumbnail['thumbnail']['url']; ?>"
                                                             alt="<?php echo html_output($file->title); ?>"
                                                             border="2" style="border-color: #000080;">
                                                    <?php else: ?>
                                                        <font face="Arial, sans-serif" color="#000080" size="2">
                                                            🖼️ Image preview not available
                                                        </font>
                                                    <?php endif; ?>
                                                <?php elseif ($file->embeddable): ?>
                                                    <input type="button" value="👁️ PREVIEW FILE" onclick="showPreview('<?php echo BASE_URI; ?>process.php?do=get_preview&file_id=<?php echo $file->id; ?>')" style="background: #ffff00; border: 2px outset #c0c0c0; font-family: Arial, sans-serif; font-size: 12px; font-weight: bold; color: #000080; padding: 4px 8px; cursor: pointer;">
                                                <?php else: ?>
                                                    <font face="Arial, sans-serif" color="#666666" size="2">
                                                        👁️ Preview not available for this file type
                                                    </font>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                <?php endif; ?>

                                <!-- Download Actions -->
                                <table width="100%" cellpadding="4" cellspacing="1" border="0" bgcolor="#c0c0c0" style="margin-top: 10px;">
                                    <tr bgcolor="<?php echo htmlspecialchars($header_bg_color); ?>">
                                        <td>
                                            <font face="Arial, sans-serif" color="#ffff00" size="3">
                                                <b>⬇️ <?php echo __('DOWNLOAD ACTIONS', 'retro90s_template'); ?></b>
                                            </font>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#f0f0f0" align="center">
                                            <?php if ($can_download): ?>
                                                <table cellpadding="8" cellspacing="0" border="0">
                                                    <tr>
                                                        <td>
                                                            <a href="<?php echo $file->public_url . '&download'; ?>">
                                                                <img src="<?php echo $this_template_url; ?>images/download.gif" alt="Download File" border="0" style="vertical-align: middle;">
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <font face="Arial, sans-serif" color="#000080" size="3">
                                                                <b>
                                                                    <a href="<?php echo $file->public_url . '&download'; ?>" style="color: #000080; text-decoration: none;">
                                                                        📥 DOWNLOAD THIS FILE NOW!
                                                                    </a>
                                                                </b>
                                                            </font>
                                                            <br>
                                                            <font face="Arial, sans-serif" color="#666666" size="2">
                                                                Click the link above to start your download!
                                                            </font>
                                                        </td>
                                                    </tr>
                                                </table>
                                            <?php else: ?>
                                                <font face="Arial, sans-serif" color="#ff0000" size="3">
                                                    <b>❌ DOWNLOAD NOT AVAILABLE</b>
                                                </font>
                                                <br>
                                                <font face="Arial, sans-serif" color="#666666" size="2">
                                                    You do not have permission to download this file.
                                                </font>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Navigation -->
                                <table width="100%" cellpadding="4" cellspacing="1" border="0" bgcolor="#c0c0c0" style="margin-top: 10px;">
                                    <tr>
                                        <td bgcolor="#ffffcc" align="center">
                                            <font face="Arial, sans-serif" color="#000080" size="2">
                                                <b>
                                                    <a href="<?php echo BASE_URI; ?>public.php" style="color: #000080;">
                                                        ⬅️ BACK TO PUBLIC FILES
                                                    </a>
                                                </b>
                                            </font>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                <?php else: ?>
                    <!-- Access Denied -->
                    <table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
                        <tr>
                            <td bgcolor="#c0c0c0">
                                <table width="100%" cellpadding="2" cellspacing="1" border="0">
                                    <tr bgcolor="#ff0000">
                                        <td>
                                            <font face="Arial, sans-serif" color="#ffffff" size="3">
                                                <b>❌ ACCESS DENIED!</b>
                                            </font>
                                        </td>
                                    </tr>
                                </table>
                                <table width="100%" cellpadding="20" cellspacing="1" border="0">
                                    <tr bgcolor="#ffcccc">
                                        <td align="center">
                                            <font face="Arial, sans-serif" size="4" color="#ff0000">
                                                <b>🚫 UNAUTHORIZED ACCESS! 🚫</b>
                                            </font>
                                            <br><br>
                                            <font face="Arial, sans-serif" size="2" color="#000080">
                                                You do not have permission to view this file.<br>
                                                Please contact the administrator if you believe this is an error.
                                            </font>
                                            <br><br>
                                            <font face="Arial, sans-serif" size="2">
                                                <b>
                                                    <a href="<?php echo BASE_URI; ?>public.php" style="color: #000080;">
                                                        ⬅️ RETURN TO PUBLIC FILES
                                                    </a>
                                                </b>
                                            </font>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>
        </td>
    </tr>
</table>

<!-- Preview Modal (90s Style) -->
<div id="previewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999;">
    <div style="display: flex; justify-content: center; align-items: flex-start; height: 100%; padding: 20px; box-sizing: border-box;">
        <table cellpadding="4" cellspacing="2" border="0" bgcolor="#008080" style="width: 95%; max-width: 1200px; max-height: 90%;">
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
                        <tr>
                            <td colspan="2" bgcolor="#f0f0f0" style="height: 400px; vertical-align: top;">
                                <div id="previewContent" style="width: 100%; height: 100%; overflow: auto;">
                                    <!-- Preview content will be loaded here -->
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</div>

<script>
var isPreviewOpen = false;

function showPreview(url) {
    if (isPreviewOpen) {
        alert('A preview window is already open! Please close it first! 🖼️');
        return;
    }

    isPreviewOpen = true;

    // Show loading message in classic 90s style
    document.getElementById('previewContent').innerHTML = '<center><font face="Arial, sans-serif" size="2"><b>Loading preview...</b><br><img src="data:image/gif;base64,R0lGODlhEAAQAPIAAP///wAAAMLCwkJCQgAAAGJiYoKCgpKSkiH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAEAAQAAADMwi63P4wyklrE2MIOggZnAdOmGYJRbExwroUmcG2LmDEwnHQLVsYOd2mBzkYDAdKa+dIAAAh+QQJCgAAACwAAAAAEAAQAAADNAi63P5OjCEgG4QMu7DmikRxQlFUYDEZIGBMRVsaqHwctXXf7WEYB4Ag1xjihkMZsiUkKhIAIfkECQoAAAAsAAAAABAAEAAAAzYIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkECQoAAAAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo/IpHI5TAAAIfkECQoAAAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo/IpFKSAAAh+QQJCgAAACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh+QQJCgAAACwAAAAAEAAQAAADMgi63P7wjRLl3+" width="16" height="16" alt="Loading..."></font></center>';
    document.getElementById('previewModal').style.display = 'block';

    // Make AJAX request (but with 90s styling)
    fetch(url)
        .then(response => response.json())
        .then(data => {
            var content = '';

            switch (data.type) {
                case 'video':
                    content = '<center>';
                    content += '<video controls style="max-width: 100%; border: 2px inset #c0c0c0;">';
                    content += '<source src="' + data.file_url + '" type="' + data.mime_type + '">';
                    content += 'Your browser does not support video playback! 📹';
                    content += '</video>';
                    content += '<br><font face="Arial, sans-serif" size="1">💡 TIP: You may need to install additional codecs!</font>';
                    content += '</center>';
                    break;

                case 'audio':
                    content = '<center>';
                    content += '<audio controls style="width: 100%; border: 2px inset #c0c0c0;">';
                    content += '<source src="' + data.file_url + '" type="' + data.mime_type + '">';
                    content += 'Your browser does not support audio playback! 🎵';
                    content += '</audio>';
                    content += '<br><font face="Arial, sans-serif" size="1">🎧 Turn up your speakers for the best experience!</font>';
                    content += '</center>';
                    break;

                case 'pdf':
                    content = '<center>';
                    content += '<iframe src="' + data.file_url + '" style="width: 100%; height: 400px; border: 2px inset #c0c0c0;" title="PDF Document">';
                    content += 'Your browser cannot display PDF files! You need Adobe Acrobat Reader! 📕';
                    content += '</iframe>';
                    content += '<br><font face="Arial, sans-serif" size="1">📄 Download <a href="http://www.adobe.com" target="_blank">Adobe Acrobat Reader</a> for better PDF support!</font>';
                    content += '</center>';
                    break;

                case 'image':
                    content = '<center>';
                    content += '<img src="' + data.file_url + '" style="max-width: 100%; border: 2px inset #c0c0c0;" alt="' + data.name + '">';
                    content += '<br><font face="Arial, sans-serif" size="1">🖼️ Image loaded successfully! Right-click and "Save As" to download!</font>';
                    content += '</center>';
                    break;

                default:
                    content = '<center>';
                    content += '<font face="Arial, sans-serif" size="3" color="#ff0000"><b>⚠️ UNSUPPORTED FILE TYPE ⚠️</b></font><br><br>';
                    content += '<font face="Arial, sans-serif" size="2">This file type cannot be previewed in your browser.</font><br>';
                    content += '<font face="Arial, sans-serif" size="2">You may need to download it and open with an appropriate application.</font><br><br>';
                    content += '<a href="' + data.file_url + '" target="_blank">';
                    content += '<img src="' + window.base_url + 'templates/retro90s/images/download.gif" alt="Download File" border="0">';
                    content += '</a>';
                    content += '</center>';
            }

            document.getElementById('previewContent').innerHTML = content;
        })
        .catch(error => {
            document.getElementById('previewContent').innerHTML = '<center><font face="Arial, sans-serif" size="2" color="#ff0000"><b>NETWORK ERROR! 📡</b><br>Check your internet connection and try again!</font></center>';
        });
}

function closePreview() {
    document.getElementById('previewModal').style.display = 'none';
    document.getElementById('previewContent').innerHTML = '';
    isPreviewOpen = false;

    // Classic 90s goodbye message
    console.log("Preview closed! Thanks for viewing! 👋");
}

// ESC key to close modal
document.addEventListener('keydown', function(e) {
    if (e.keyCode === 27 && isPreviewOpen) { // ESC key
        closePreview();
    }
});

// Click background to close modal
document.getElementById('previewModal').addEventListener('click', function(e) {
    if (!e.target.closest('table')) {
        closePreview();
    }
});
</script>

<?php
$footer_type = 'public';
require_once dirname(__FILE__) . '/footer.php';
?>

<?php render_custom_assets('body_bottom'); ?>
</body>
</html>