<?php
/**
 * Shows the list of public groups and files
 */
define('IS_PUBLIC_VIEW', true);

$allowed_levels = array(9, 8, 7, 0);

// If the option to show this page is not enabled, redirect
if (get_option('public_listing_page_enable') != 1) {
    ps_redirect(BASE_URI . "index.php");
}

// Check the option to show the page to logged in users only
if (get_option('public_listing_logged_only') == 1) {
    redirect_if_not_logged_in();
}

$current_url = get_form_action_with_existing_parameters(basename(__FILE__));

$mode = 'files';

// If viewing a particular group, make sure it's public
if (!empty($_GET['group'])) {
    if (empty($_GET['token'])) {
        ps_redirect(BASE_URI . "index.php");
    }

    if (!can_view_public_group($_GET['group'], $_GET['token'])) {
        ps_redirect(BASE_URI . "index.php");
    }

    $mode = 'group';
    $current_group = new \ProjectSend\Classes\Groups($_GET['group']);
    $group_props = $current_group->getProperties();
}

$page_id = 'public_files_list';

$show_page_title = true;
$page_title = ($mode == 'files') ? __('Public files (not assigned to any group)', 'cftp_admin') : sprintf(__('Files in group: %s', 'cftp_admin'), $group_props['name']);

$dont_redirect_if_logged = 1;

// Pagination
$per_page = get_option('pagination_results_per_page');
$pagination_page = (isset($_GET["page"])) ? $_GET["page"] : 1;
$pagination_start = ($pagination_page - 1) * $per_page;
$args = [
    'group' => null,
    'pagination' => [
        'page' => $pagination_page,
        'start' => $pagination_start,
        'per_page' => $per_page, //get_option('pagination_results_per_page')
    ]
];
if (!empty($_GET['group'])) {
    $args['group_id'] = $_GET['group'];
}

$files = get_public_files($args);

require get_template_file_location('public-list.php');
