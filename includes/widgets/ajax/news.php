<?php
require_once '../../../bootstrap.php';

header("Content-type: application/json");

if (!defined('CURRENT_USER_LEVEL') or CURRENT_USER_LEVEL != 9) {
    ps_redirect(BASE_URI);
}

// $feed = simplexml_load_file(NEWS_FEED_URI);
$feed = getJson(NEWS_FEED_URI, '-1 days');
$news = json_decode($feed);

$return = [
    'items' => [],
];

$max_news = 5;
$n = 0;
foreach ($news as $item) {
    if ($n < $max_news) {
        $return['items'][] = [
            'date' => format_date($item->date),
            'url' => $item->link,
            'title' => $item->title,
            'content' => make_excerpt(html_output(strip_tags($item->content, '<br />')),200),
        ];
        $n++;
    }
}

echo json_encode($return);
exit;
