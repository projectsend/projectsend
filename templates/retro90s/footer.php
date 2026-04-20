<?php
/**
 * Footer for Retro 90s Template
 * Can be used by both template.php and public.php
 */

// Default footer type is 'client' for template.php
$footer_type = isset($footer_type) ? $footer_type : 'client';

// Check if entertainment section should be shown based on theme settings
$show_entertainment = get_theme_option('retro90s', 'show_entertainment', true);
if ($show_entertainment) {
    require_once dirname(__FILE__) . '/entertainment.php'; // include 90s entertainment CSV helper
}

// Include visitor counter
require_once dirname(__FILE__) . '/visitor_counter.php';
?>

<?php if ($footer_type === 'public'): ?>
    <!-- Public Footer -->
    <table width="100%" cellpadding="4" cellspacing="1" border="0" bgcolor="#000080">
        <tr>
            <td align="center">
                <font face="Arial, sans-serif" color="#00ffff" size="2">
                    <marquee behavior="alternate" width="80%">
                        ★ Welcome to the radical world of public file sharing! ★
                    </marquee>
                </font>
            </td>
        </tr>
        <tr>
            <td align="center">
                <font face="Arial, sans-serif" color="#ffffff" size="1">
                    <?php render_footer_text(); ?>
                </font>
            </td>
        </tr>
    </table>
<?php else: ?>
    <!-- Client Footer -->
    <table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
        <tr>
            <td bgcolor="#c0c0c0" align="center">
                <font face="Arial, sans-serif" size="1" color="#666666">
                    <?php render_footer_text(); ?>
                    <br>
                    <blink>★ GEOCITIES STYLE ★</blink>
                </font>
            </td>
        </tr>
    </table>
<?php endif; ?>