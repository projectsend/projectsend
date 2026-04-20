<?php
/**
 * RETRO 90s CSV HELPER
 * Functions to read and randomly select from entertainment CSV files
 * Compatible with ancient PHP versions (like the 90s!)
 */

/**
 * Read CSV file and return random items
 * @param string $csvFile Path to CSV file
 * @param int $count Number of random items to return
 * @return array Array of random items
 */
function getRandomItemsFromCSV($csvFile, $count = 3) {
    $items = array();
    
    // Check if file exists
    if (!file_exists($csvFile)) {
        return $items;
    }
    
    // Read CSV file
    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
        $headers = fgetcsv($handle); // Get headers
        
        // Read all data rows
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) === count($headers)) {
                $item = array_combine($headers, $data);
                $items[] = $item;
            }
        }
        fclose($handle);
    }
    
    // Shuffle and return random items
    if (count($items) > 0) {
        shuffle($items);
        return array_slice($items, 0, min($count, count($items)));
    }
    
    return array();
}

/**
 * Get random movies from CSV
 * @param int $count Number of movies to return
 * @return array Array of random movies
 */
function getRandomMovies($count = 3) {
    $csvFile = dirname(__FILE__) . '/data/movies.csv';
    return getRandomItemsFromCSV($csvFile, $count);
}

/**
 * Get random music albums from CSV
 * @param int $count Number of albums to return
 * @return array Array of random albums
 */
function getRandomMusic($count = 3) {
    $csvFile = dirname(__FILE__) . '/data/music.csv';
    return getRandomItemsFromCSV($csvFile, $count);
}

/**
 * Get random videogames from CSV
 * @param int $count Number of games to return
 * @return array Array of random games
 */
function getRandomVideoGames($count = 3) {
    $csvFile = dirname(__FILE__) . '/data/videogames.csv';
    return getRandomItemsFromCSV($csvFile, $count);
}

/**
 * Format entertainment item for display
 * @param array $item Item data from CSV
 * @param string $type Type of item (movie, music, videogame)
 * @return string Formatted HTML
 */
function formatEntertainmentItem($item, $type) {
    $html = '<td bgcolor="#f0f0f0" align="center" width="33%">';
    
    switch ($type) {
        case 'movie':
            $html .= '<font face="Arial, sans-serif" size="2">';
            $html .= '<b>' . htmlspecialchars($item['icon']) . ' ' . htmlspecialchars($item['title']) . '</b><br>';
            $html .= '<font size="1">(' . htmlspecialchars($item['year']) . ' - ' . htmlspecialchars($item['genre']) . ')</font><br>';
            $html .= htmlspecialchars($item['description']);
            $html .= '</font>';
            break;
            
        case 'music':
            $html .= '<font face="Arial, sans-serif" size="2">';
            $html .= '<b>' . htmlspecialchars($item['icon']) . ' ' . htmlspecialchars($item['artist']) . '</b><br>';
            $html .= '<font size="1">"' . htmlspecialchars($item['album']) . '" (' . htmlspecialchars($item['year']) . ')</font><br>';
            $html .= htmlspecialchars($item['description']);
            $html .= '</font>';
            break;
            
        case 'videogame':
            $html .= '<font face="Arial, sans-serif" size="2">';
            $html .= '<b>' . htmlspecialchars($item['icon']) . ' ' . htmlspecialchars($item['title']) . '</b><br>';
            $html .= '<font size="1">(' . htmlspecialchars($item['year']) . ' - ' . htmlspecialchars($item['platform']) . ')</font><br>';
            $html .= htmlspecialchars($item['description']);
            $html .= '</font>';
            break;
    }
    
    $html .= '</td>';
    return $html;
}
?>