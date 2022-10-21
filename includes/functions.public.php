<?php
function get_public_files($args = [])
{
    global $dbh;

    $return = [
        'files_ids' => [],
        'pagination' => [
            'total' => 0,
        ],
    ];

    // Get all public groups
    $public_groups_ids = [];
    $groups = get_groups([
        'public' => true,
    ]);
    if ( !empty( $groups ) ) {
        foreach ($groups as $group) {
            $public_groups_ids[$group['id']] = [
                'id' => $group['id'],
                'files_assigned' => [],
            ];
        }

        // Get distinct files relations for these groups ids.
        // On the front page, we then ignore this IDs since these are assigned files.
        $search_public_groups_ids = implode(',', array_keys($public_groups_ids));
        $files_in_groups = array_unique(get_groups_assigned_files($search_public_groups_ids));
    }

    // Start the main query
    $params = [];

    $files_sql = "SELECT * FROM " . TABLE_FILES;

    // All files or just the public ones?
    $limit_to_public = true;
    if (!empty($args['group_id'])) {
        $assigned_files = implode(',', get_group_assigned_files($args['group_id']));
        $files_sql .= " WHERE FIND_IN_SET(id, :ids)";
        $params[':ids'] = $assigned_files;

        if ( get_option('public_listing_show_all_files') == 1) {
            $limit_to_public = false;
        }
    } else {
        if (empty($files_in_groups)) {
            $files_in_groups = [0];
        }
        // On the front page, we need files that are NOT assgined to any public group
        $files_sql .= " WHERE NOT FIND_IN_SET(id, :ids)";
        $params[':ids'] = implode(',', $files_in_groups);
    }

    // Make sure to only get public files
    if ($limit_to_public == true) {
        $files_sql .= " AND public_allow = :public";
        $params[':public'] = 1;
    }

    // Search
    if (!empty($_GET['search'])) {
        $files_sql .= ' AND (filename LIKE :title OR description LIKE :description)';
        $params[':title'] = '%' . $_GET['search'] . '%';
        $params[':description'] = '%' . $_GET['search'] . '%';
    }

    // Show we hide expired files?
    if (get_option('expired_files_hide') == 1) {
        $files_sql .= " AND ((expires = :expires AND expiry_date >= CURDATE()) OR expires = :not_expires)";
        $params[':expires'] = 1;
        $params[':not_expires'] = 0;
    }

    // Pre-query to count the total results
    $count_sql = $dbh->prepare( $files_sql );
    $count_sql->execute($params);
    $return['pagination']['total'] = ($count_sql->rowCount());

    // Add the order.
    $files_sql .= sql_add_order( TABLE_FILES, 'id', 'desc' );

    // Repeat the query but this time, limited by pagination
    $files_sql .= " LIMIT :limit_start, :limit_number";
    $sql = $dbh->prepare( $files_sql );

    $params[':limit_start'] = $args['pagination']['start'];
    $params[':limit_number'] = $args['pagination']['per_page'];

    $sql = $dbh->prepare($files_sql);
    $sql->execute($params);
    $sql->setFetchMode(PDO::FETCH_ASSOC);
    while ( $row = $sql->fetch() ) {
        $return['files_ids'][] = $row['id'];
    }

    return $return;
}
