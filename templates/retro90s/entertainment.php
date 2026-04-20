<?php
/**
 * Entertainment Section for Retro 90s Template
 * Shared entertainment content that can be included in templates
 */
require_once dirname(__FILE__) . '/csv_helper.php'; // include 90s entertainment CSV helper

// Get theme settings for entertainment section
$entertainment_items_count = get_theme_option('retro90s', 'entertainment_items_count', 3);
$entertainment_title = get_theme_option('retro90s', 'entertainment_title', '');
$retro_color_scheme = get_theme_option('retro90s', 'retro_color_scheme', 'neon');
$show_grid_animation = get_theme_option('retro90s', 'show_grid_animation', true);

// Get random entertainment content for this page load
$random_movies = getRandomMovies($entertainment_items_count * 2); // Double for movies since they split into columns
$random_music = getRandomMusic($entertainment_items_count);
$random_videogames = getRandomVideoGames($entertainment_items_count);
?>

<!-- Retro Separator -->
<div style="margin-top: 50px; margin-bottom: 50px;">
    <center>
        <table width="90%" cellpadding="2" cellspacing="0" border="0">
            <tr>
                <td bgcolor="#808080" height="3" style="border-top: 1px solid #ffffff; border-left: 1px solid #ffffff;"></td>
            </tr>
            <tr>
                <td bgcolor="#c0c0c0" height="2" style="border-bottom: 1px solid #000000; border-right: 1px solid #000000;"></td>
            </tr>
        </table>
    </center>
</div>

<!-- Entertainment Section (shared between templates) -->
<table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
    <tr>
        <td bgcolor="#c0c0c0">
            <center>
                <br>
                <font face="Arial, sans-serif" color="#666666" size="1">
                    <blink>★ ★ ★</blink> <?php echo !empty($entertainment_title) ? htmlspecialchars($entertainment_title) : 'ENTERTAINMENT ZONE'; ?> <blink>★ ★ ★</blink>
                </font>
            </center>
        </td>
    </tr>
</table>

<!-- MUST WATCH MOVIES Section (Very 90s!) -->
<table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
    <tr>
        <td bgcolor="#c0c0c0">
            <table width="100%" cellpadding="2" cellspacing="1" border="0">
                <tr bgcolor="#000080">
                    <td>
                        <font face="Arial, sans-serif" color="#ffff00" size="3">
                            <b>🎬 MUST WATCH MOVIES! 🎬</b>
                        </font>
                    </td>
                </tr>
                <tr bgcolor="#ffff00">
                    <td>
                        <marquee behavior="scroll" direction="left" bgcolor="#ffff00">
                            <font face="Arial, sans-serif" color="#ff0000" size="2">
                                <b>*** COMING SOON TO VHS! *** RENT NOW AT BLOCKBUSTER! *** GET YOUR COPY BEFORE THEY'RE GONE! ***</b>
                            </font>
                        </marquee>
                    </td>
                </tr>
                <tr bgcolor="#c0c0c0">
                    <td>
                        <table width="100%" cellpadding="3" cellspacing="1" border="0">
                            <tr bgcolor="#808080">
                                <td width="50%">
                                    <font face="Arial, sans-serif" color="#ffffff" size="2">
                                        <b>📼 RECENT BLOCKBUSTERS!</b>
                                    </font>
                                </td>
                                <td width="50%">
                                    <font face="Arial, sans-serif" color="#ffffff" size="2">
                                        <b>🌟 COMING ATTRACTIONS!</b>
                                    </font>
                                </td>
                            </tr>
                            <tr bgcolor="#f0f0f0">
                                <td valign="top">
                                    <font face="Arial, sans-serif" size="2">
                                        <?php
                                        $half = ceil(count($random_movies) / 2);
                                        for ($i = 0; $i < $half; $i++) {
                                            $movie = $random_movies[$i];
                                            echo '<b>• ' . htmlspecialchars($movie['title']) . '</b> (' . htmlspecialchars($movie['year']) . ')<br>';
                                            echo '<font size="1">' . htmlspecialchars($movie['icon']) . ' ' . htmlspecialchars($movie['description']) . '</font>';
                                            if ($i < $half - 1) echo '<br><br>';
                                        }
                                        ?>
                                    </font>
                                </td>
                                <td valign="top">
                                    <font face="Arial, sans-serif" size="2">
                                        <?php
                                        for ($i = $half; $i < count($random_movies); $i++) {
                                            $movie = $random_movies[$i];
                                            echo '<b>• ' . htmlspecialchars($movie['title']) . '</b> (' . htmlspecialchars($movie['year']) . ')<br>';
                                            echo '<font size="1">' . htmlspecialchars($movie['icon']) . ' ' . htmlspecialchars($movie['description']) . '</font>';
                                            if ($i < count($random_movies) - 1) echo '<br><br>';
                                        }
                                        ?>
                                    </font>
                                </td>
                            </tr>
                            <tr bgcolor="#e0e0e0">
                                <td colspan="2" align="center">
                                    <font face="Arial, sans-serif" size="2">
                                        <b>🎭 CLASSIC MUST-SEES:</b>
                                        <blink>Titanic</blink> •
                                        <blink>The Silence of the Lambs</blink> •
                                        <blink>Goodfellas</blink> •
                                        <blink>Dances with Wolves</blink> •
                                        <blink>Pretty Woman</blink>
                                    </font>
                                </td>
                            </tr>
                            <tr bgcolor="#ffcccc">
                                <td colspan="2" align="center">
                                    <font face="Arial, sans-serif" size="2" color="#ff0000">
                                        <b>⚠️ WARNING: Please be kind, rewind! ⚠️</b><br>
                                        <font size="1">Late fees apply after 3 days! No exceptions!</font>
                                    </font>
                                </td>
                            </tr>
                            <tr bgcolor="#ccffcc">
                                <td colspan="2" align="center">
                                    <font face="Arial, sans-serif" size="1">
                                        💰 <b>SPECIAL OFFER:</b> Rent 2 movies, get 1 FREE popcorn! 🍿<br>
                                        Valid only at participating Blockbuster stores. Expires Dec 31, 1999.
                                    </font>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr bgcolor="#000080">
                    <td align="center">
                        <font face="Arial, sans-serif" color="#00ffff" size="1">
                            <blink>*** Visit your local video store today! ***</blink><br>
                            Powered by the latest VHS technology!
                        </font>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Retro Separator -->
<div style="margin-top: 50px; margin-bottom: 50px;">
    <center>
        <table width="90%" cellpadding="2" cellspacing="0" border="0">
            <tr>
                <td bgcolor="#808080" height="3" style="border-top: 1px solid #ffffff; border-left: 1px solid #ffffff;"></td>
            </tr>
            <tr>
                <td bgcolor="#c0c0c0" height="2" style="border-bottom: 1px solid #000000; border-right: 1px solid #000000;"></td>
            </tr>
        </table>
    </center>
</div>

<!-- MUSIC & GAMES Section (More 90s Fun!) -->
<table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
    <tr>
        <td bgcolor="#c0c0c0">
            <table width="100%" cellpadding="2" cellspacing="1" border="0">
                <tr bgcolor="#800080">
                    <td colspan="2">
                        <font face="Arial, sans-serif" color="#ffff00" size="3">
                            <b>🎵 HOTTEST CDs & 🎮 COOLEST GAMES! 🎵</b>
                        </font>
                    </td>
                </tr>
                <tr bgcolor="#ff00ff">
                    <td colspan="2">
                        <marquee behavior="scroll" direction="right" bgcolor="#ff00ff">
                            <font face="Arial, sans-serif" color="#ffffff" size="2">
                                <b>*** NOW AT TOWER RECORDS & ELECTRONICS BOUTIQUE! *** GET THE LATEST HITS! ***</b>
                            </font>
                        </marquee>
                    </td>
                </tr>
                <tr bgcolor="#c0c0c0">
                    <td width="50%" valign="top">
                        <table width="100%" cellpadding="2" cellspacing="1" border="0">
                            <tr bgcolor="#800080">
                                <td>
                                    <font face="Arial, sans-serif" color="#ffffff" size="2">
                                        <b>💿 TOP ALBUMS ON CD!</b>
                                    </font>
                                </td>
                            </tr>
                            <tr bgcolor="#ffffcc">
                                <td>
                                    <font face="Arial, sans-serif" size="2">
                                        <?php
                                        foreach ($random_music as $index => $album) {
                                            echo '<b>• ' . htmlspecialchars($album['artist']) . ' - ' . htmlspecialchars($album['album']) . '</b> (' . htmlspecialchars($album['year']) . ')<br>';
                                            echo '<font size="1">' . htmlspecialchars($album['icon']) . ' ' . htmlspecialchars($album['description']) . '</font>';
                                            if ($index < count($random_music) - 1) echo '<br><br>';
                                        }
                                        ?>
                                    </font>
                                </td>
                            </tr>
                            <tr bgcolor="#ccffff">
                                <td align="center">
                                    <font face="Arial, sans-serif" size="1">
                                        <b>🎧 NEW!</b> Portable CD Players with ANTI-SKIP!<br>
                                        <blink>Perfect for jogging!</blink>
                                    </font>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td width="50%" valign="top">
                        <table width="100%" cellpadding="2" cellspacing="1" border="0">
                            <tr bgcolor="#800080">
                                <td>
                                    <font face="Arial, sans-serif" color="#ffffff" size="2">
                                        <b>🕹️ EPIC VIDEOGAMES!</b>
                                    </font>
                                </td>
                            </tr>
                            <tr bgcolor="#ccffcc">
                                <td>
                                    <font face="Arial, sans-serif" size="2">
                                        <?php
                                        foreach ($random_videogames as $index => $game) {
                                            echo '<b>• ' . htmlspecialchars($game['title']) . '</b> (' . htmlspecialchars($game['year']) . ')<br>';
                                            echo '<font size="1">' . htmlspecialchars($game['icon']) . ' ' . htmlspecialchars($game['description']) . '</font>';
                                            if ($index < count($random_videogames) - 1) echo '<br><br>';
                                        }
                                        ?>
                                    </font>
                                </td>
                            </tr>
                            <tr bgcolor="#ffccff">
                                <td align="center">
                                    <font face="Arial, sans-serif" size="1">
                                        <b>🎮 NEW!</b> 32-bit graphics! CD-quality sound!<br>
                                        <blink>The future is HERE!</blink>
                                    </font>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr bgcolor="#ffff00">
                    <td colspan="2" align="center">
                        <font face="Arial, sans-serif" size="2" color="#ff0000">
                            <b>🔥 HOT DEALS:</b>
                            <blink>Buy 2 CDs, get 1 cassette FREE!</blink> •
                            <blink>Rent 3 games, keep 1 extra day!</blink> •
                            <blink>Trade-ins accepted!</blink>
                        </font>
                    </td>
                </tr>
                <tr bgcolor="#e0e0e0">
                    <td width="50%" align="center">
                        <font face="Arial, sans-serif" size="1">
                            💿 <b>Also available on:</b> Cassette Tape<br>
                            🎵 For your Walkman or Boom Box!
                        </font>
                    </td>
                    <td width="50%" align="center">
                        <font face="Arial, sans-serif" size="1">
                            🕹️ <b>Compatible systems:</b> NES, SNES, Genesis<br>
                            🎮 Game Boy, PC (DOS), Arcade!
                        </font>
                    </td>
                </tr>
                <tr bgcolor="#800080">
                    <td colspan="2" align="center">
                        <font face="Arial, sans-serif" color="#00ffff" size="1">
                            <blink>*** Experience the digital revolution! ***</blink><br>
                            CD players and 16-bit consoles - Technology at its finest!
                        </font>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>