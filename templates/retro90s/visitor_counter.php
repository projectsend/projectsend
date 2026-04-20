<?php
/**
 * Retro 90s Visitor Counter Component
 * Displays a nostalgic visitor counter with Comic Sans font
 * Generates a random number between 500k and 1 million each page load
 */

// Check if visitor counter should be shown based on theme settings
$show_visitor_counter = get_theme_option('retro90s', 'show_visitor_counter', true);

if ($show_visitor_counter):
    // Generate a random visitor count between 500,000 and 1,000,000
    $visitor_count = rand(500000, 999999);

    // Format the number with commas
    $formatted_count = number_format($visitor_count);

    // Generate individual digit images for authentic 90s look
    $digits = str_split(str_replace(',', '', $visitor_count));
?>

<!-- 90s VISITOR COUNTER -->
<div style="margin-top: 30px; margin-bottom: 30px;">
    <center>
        <table cellpadding="5" cellspacing="2" border="0" bgcolor="#000000">
            <tr>
                <td bgcolor="#808080">
                    <table cellpadding="3" cellspacing="1" border="0" bgcolor="#c0c0c0">
                        <tr>
                            <td bgcolor="#ffffff" align="center">
                                <font face="Comic Sans MS, cursive" size="2" color="#0000ff">
                                    <b>☆ You are visitor number ☆</b>
                                </font>
                            </td>
                        </tr>
                        <tr>
                            <td bgcolor="#000000" align="center" style="padding: 5px;">
                                <!-- Digital counter display -->
                                <table cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <?php foreach ($digits as $digit): ?>
                                        <td style="background: linear-gradient(to bottom, #00ff00, #008000); border: 1px solid #004000; padding: 2px 4px;">
                                            <font face="Courier New, monospace" size="4" color="#000000">
                                                <b><?php echo $digit; ?></b>
                                            </font>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td bgcolor="#e0e0e0" align="center">
                                <font face="Comic Sans MS, cursive" size="1" color="#666666">
                                    <i>Since January 1, 1996!</i>
                                </font>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Animated sparkles around the counter -->
        <table width="300" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td align="center">
                    <font size="1" color="#ff00ff">
                        <marquee behavior="alternate" width="100" scrollamount="3">★</marquee>
                        <blink>✨</blink>
                        <marquee behavior="alternate" width="100" scrollamount="2" direction="right">★</marquee>
                        <blink>✨</blink>
                        <marquee behavior="alternate" width="100" scrollamount="4">★</marquee>
                    </font>
                </td>
            </tr>
        </table>
    </center>
</div>

<!-- Add CSS for Comic Sans fallback and digital counter styling -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Comic+Neue:wght@700&display=swap');

    /* Fallback for Comic Sans MS */
    font[face*="Comic Sans"] {
        font-family: "Comic Sans MS", "Comic Neue", cursive, sans-serif !important;
    }

    /* Rainbow text effect for the counter title */
    @keyframes rainbow {
        0% { color: #ff0000; }
        17% { color: #ff8800; }
        33% { color: #ffff00; }
        50% { color: #00ff00; }
        67% { color: #0088ff; }
        83% { color: #ff00ff; }
        100% { color: #ff0000; }
    }

    .rainbow-text {
        animation: rainbow 3s linear infinite;
    }
</style>

<?php endif; ?>