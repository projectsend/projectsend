<?php
require_once '../../../bootstrap.php';

if (!defined('CURRENT_USER_LEVEL') or !in_array(CURRENT_USER_LEVEL, array(9,8,7))) {
    header("Location: ".BASE_URI);
    exit;
}

if (!is_numeric($_GET['days'])) {
    exit;
}

global $dbh;

$first_day = new DateTime();
$sub = ((int)$_GET['days']-1)*(-1). ' days';
$first_day->modify($sub);

$period = new DatePeriod(
    new DateTime($first_day->format('Y-m-d')),
    new DateInterval('P1D'),
    new DateTime()
);

// Labels = dates
$labels = [];
$dates = []; // To have one item per day, per data item
foreach ($period as $key => $date) {
    $labels[] = $date->format(get_option('timeformat'));
    $dates[$date->format('Y-m-d')] = 0; // 0 By default
}

// Make data array data
$data = [
    'uploads_users' => $dates,
    'uploads_clients' => $dates,
    'downloads' => $dates,
    'downloads_public' => $dates,
];

// Get data from action log and add to that days's count for each action
$actions = implode(',', [
    5, // User uploads file 
    6, // Client uploads file
    7, // User downloads
    8, // Client downloads
    37, // Anonymous download
]);
$statement = $dbh->prepare(
    "SELECT action, DATE(timestamp) as statsDate, COUNT(*) as total FROM " . TABLE_LOG . " WHERE timestamp >= DATE_SUB( CURDATE(),INTERVAL :max_days DAY) AND action IN (".$actions.") GROUP BY statsDate, action"
);
$statement->bindParam(':max_days', $_GET['days']);
$statement->execute();
if ( $statement->rowCount() > 0 ) {
    while ( $row = $statement->fetch() ) {
        switch ($row['action']) {
            case 5:
                $type = 'uploads_users';
            break;
            case 6:
                $type = 'uploads_clients';
            break;
            case 7:
            case 8:
                $type = 'downloads';
            break;
            case 37:
                $type = 'downloads_public';
            break;
        }
        $data[$type][$row['statsDate']] = $row['total'];
    }
}

$datasets = [
    [
        'label' => __('Uploads by users'),
        'fill' => false,
        'borderColor' => "#0094bb",
        'data' => array_values($data['uploads_users']),
    ],
    [
        'label' => __('Uploads by clients'),
        'fill' => false,
        'borderColor' => "#86ae00",
        'data' => array_values($data['uploads_clients']),
    ],
    [
        'label' => __('Downloads by known users'),
        'fill' => false,
        'borderColor' => "#f2b705",
        'data' => array_values($data['downloads']),
    ],
    [
        'label' => __('Public Downloads'),
        'fill' => false,
        'borderColor' => "#1ec4a7",
        'data' => array_values($data['downloads_public']),
    ],
];

$response = [
    'chart' => [
        'labels' => $labels,
        'datasets' => $datasets,
    ]
];

echo json_encode($response);
exit;