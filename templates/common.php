<?php
/**
 * Common information used on all clients templates.
 * Avoids the need to define all of this when creating a new template.
 */
global $dbh;

/**
 * Since the header.php file is shared between the back-end and the
 * templates, it's necessary to define the allowed levels, or else
 * the files list will not be available.
 */
$allowed_levels = array(9, 8, 7, 0);

if (!current_user_can_view_files_list()) {
    ps_redirect(BASE_URI);
}

$this_template_slug = get_option('selected_clients_template');
$this_template_url = BASE_URI . 'templates/' . $this_template_slug . '/';

// Load language
template_load_translation($this_template_slug);

// Load default template's CSS file
if (file_exists(ROOT_DIR . DS . "templates" . DS . $this_template_slug . DS . "main.css")) {
    add_asset('css', 'ps_theme_css', BASE_URI . 'templates/' . $this_template_slug . '/main.css', get_option('database_version'), 'head');
}

/**
 * Get all the client's information
 */
$client_info = get_client_by_username($view_files_as);

/**
 * Get the list of different groups the client belongs to.
 */
$get_groups = new ProjectSend\Classes\GroupsMemberships;
$found_groups = $get_groups->getGroupsByClient([
    'client_id' => $client_info['id'],
    'return' => 'list',
]);

// Folders
$current_folder = (isset($_GET['folder_id'])) ? (int)$_GET['folder_id'] : null;
// Check permissions for current folder
if (!empty($current_folder)) {
    $folder = new \ProjectSend\Classes\Folder($current_folder);
    if (!$folder->userCanNavigate($client_info['id'])) {
        exit_with_error_code(403);
    }
}

$folders_arguments = [
    'parent' => $current_folder,
];
if (get_option('clients_files_list_include_public')) {
    $folders_arguments['public_or_client'] = true;
    $folders_arguments['client_id'] = $client_info['id'];
} else {
    $folders_arguments['user_id'] = $client_info['id'];
}
if (!empty($_GET['search'])) {
    $folders_arguments['search'] = $_GET['search'];
}

$folders_obj = new \ProjectSend\Classes\Folders;
$folders = $folders_obj->getFolders($folders_arguments);

/**
 * Define the arrays so they can't be empty
 */
$found_all_files_array = [];

/**
 * Get files uploaded by this user
 */
$files_query = "SELECT * FROM " . TABLE_FILES . " WHERE user_id=:user_id";
$files_sql = $dbh->prepare($files_query);
$files_sql->bindParam(':user_id', $client_info['id'], PDO::PARAM_INT);
$files_sql->execute();
$files_sql->setFetchMode(PDO::FETCH_ASSOC);
while ($row_files = $files_sql->fetch()) {
    $found_all_files_array[] = $row_files['id'];
}

/**
 * Get files assigned directly to the client
 * Construct the query first.
 */
$files_query = "SELECT id, file_id, client_id, group_id FROM " . TABLE_FILES_RELATIONS . " WHERE (client_id = :id";
if (!empty($found_groups)) {
    $files_query .= " OR FIND_IN_SET(group_id, :groups)";
}
$files_query .= ") AND hidden = '0'";

$files_sql = $dbh->prepare($files_query);

$files_sql->bindParam(':id', $client_info['id'], PDO::PARAM_INT);
if (!empty($found_groups)) {
    $files_sql->bindParam(':groups', $found_groups);
}

$files_sql->execute();
$files_sql->setFetchMode(PDO::FETCH_ASSOC);

while ($row_files = $files_sql->fetch()) {
    if (!is_null($row_files['client_id'])) {
        $found_all_files_array[] = $row_files['file_id'];
    }
    if (!is_null($row_files['group_id'])) {
        $found_all_files_array[] = $row_files['file_id'];
    }
}

$found_unique_files_ids = implode(',', array_unique($found_all_files_array));

/**
 * Make an array of the categories containing the
 * files found for this account.
 */
$cat_ids = [];
$file_ids = [];
$files_keep = [];

$sql_sentence = "SELECT file_id, cat_id FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE FIND_IN_SET(file_id, :files)";
$sql_client_categories = $dbh->prepare($sql_sentence);
$sql_client_categories->bindParam(':files', $found_unique_files_ids);
$sql_client_categories->execute();
$sql_client_categories->setFetchMode(PDO::FETCH_ASSOC);

while ($row = $sql_client_categories->fetch()) {
    $cat_ids[$row['cat_id']] = $row['cat_id'];
    $files_keep[$row['file_id']][] = $row['cat_id'];
}

if (!empty($cat_ids)) {
    $get_categories = get_categories([
        'id' => $cat_ids,
        'is_tree' => true
    ]);
}
/**
 * With the categories generated, keep only the files
 * that are assigned to the selected one.
 */
if (isset($filter_by_category) && $filter_by_category != '0') {
    $filtered_file_ids = [];
    foreach ($files_keep as $keep_file_id => $keep_cat_ids) {
        if (in_array($filter_by_category, $keep_cat_ids)) {
            $filtered_file_ids[] = $keep_file_id;
        }
    }
    $ids_to_search = implode(',', $filtered_file_ids);
} else {
    $ids_to_search = $found_unique_files_ids;
}

/** Create the files list */
$available_files = [];
$my_files = [];
$count = 0;

if (!empty($found_all_files_array)) {
    $f = 0;
    $files_query = "SELECT * FROM " . TABLE_FILES . " WHERE (FIND_IN_SET(id,:search_ids)";

    $params = [
        ':search_ids' => $ids_to_search,
    ];

    // Should it include public files as well?
    if (get_option('clients_files_list_include_public') == '1') {
        $files_query .= " OR public_allow = :public";
        $params[':public'] = '1';
    }

    $files_query .= ')';

    /** Add the search terms */
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $files_query .= " AND (filename LIKE :title OR description LIKE :description)";
        $no_results_error = 'search';

        $params[':title'] = '%' . $_GET['search'] . '%';
        $params[':description'] = '%' . $_GET['search'] . '%';
    }

    // Filter by folders
    if (!empty($current_folder)) {
        $files_query .= " AND folder_id = :folder_id";
        $params[':folder_id'] = (int)$current_folder;
    } else {
        $files_query .= " AND folder_id is null";
    }
    
    /**
     * Add the order.
     * Defaults to order by: timestamp, order: DESC (shows last uploaded files first)
     */
    $files_query .= sql_add_order(TABLE_FILES, 'timestamp', 'desc');

    /**
     * Pre-query to count the total results
     */
    $count_sql = $dbh->prepare($files_query);
    $count_sql->execute($params);

    $count_for_pagination = 0;
    while ($data = $count_sql->fetch()) {
        /** Does it expire? */
        $counts = true;
        if ($data['expires'] == '1') {
            if (time() > strtotime($data['expiry_date'])) {
                if (get_option('expired_files_hide') == '1') {
                    $counts = false;
                }
            }
        }
        if ($counts) {
            $count_for_pagination++;
        }
    }

    //$count_for_pagination = $count_sql->rowCount();

    /**
     * Repeat the query but this time, limited by pagination
     */
    if (TEMPLATE_RESULTS_PER_PAGE > 0) {
        $files_query .= " LIMIT :limit_start, :limit_number";
    }
    $sql_files = $dbh->prepare($files_query);

    if (TEMPLATE_RESULTS_PER_PAGE > 0) {
        $pagination_page = (isset($_GET["page"])) ? $_GET["page"] : 1;
        $pagination_start = ($pagination_page - 1) * TEMPLATE_RESULTS_PER_PAGE;
        $params[':limit_start'] = $pagination_start;
        $params[':limit_number'] = TEMPLATE_RESULTS_PER_PAGE;
    }

    $sql_files->execute($params);
    $count = $sql_files->rowCount();

    $sql_files->setFetchMode(PDO::FETCH_ASSOC);
    while ($data = $sql_files->fetch()) {
        $add_file = true;
        $expired = false;

        /** Does it expire? */
        if ($data['expires'] == '1') {
            if (time() > strtotime($data['expiry_date'])) {
                if (get_option('expired_files_hide') == '1') {
                    $add_file = false;
                }
                $expired = true;
            }
        }

        /** Make the list of files */
        if ($add_file == true) {
            $available_files[] = $data['id'];

            // Leaving this here in case a  custom template is using this array
            $pathinfo = pathinfo($data['url']);
            $my_files[$f] = [
                //'origin' => $origin,
                'id' => $data['id'],
                'url' => $data['url'],
                'save_as' => (!empty($data['original_url'])) ? $data['original_url'] : $data['url'],
                'extension' => strtolower($pathinfo['extension']),
                'name' => $data['filename'],
                'description' => $data['description'],
                'timestamp' => $data['timestamp'],
                'expires' => $data['expires'],
                'expiry_date' => $data['expiry_date'],
                'expired' => $expired,
            ];
            // End deprecated array block
            $f++;
        }
    }
}

/** Get the url for the logo from "Branding" */
$logo_file_info = generate_logo_url();
