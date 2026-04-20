<?php
/**
 * Home page for logged in system users.
 */
require_once 'bootstrap.php';
// Dashboard is accessible to all logged-in non-client users
redirect_if_not_logged_in();

// Clients should use their own dashboard
if (current_role_in(['Client'])) {
    ps_redirect(BASE_URI . 'my_files/');
}

$page_title = __('Dashboard', 'cftp_admin');

$active_nav = 'dashboard';

$body_class = array('dashboard', 'home', 'hide_title');
$page_id = 'dashboard';

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

define('CAN_INCLUDE_FILES', true);

if (current_user_can('view_dashboard_counters')) {
    include_once WIDGETS_FOLDER . 'counters.php';
}
?>
<div class="dashboard-widgets-container" id="dashboard-widgets">
    <?php if (current_user_can('view_statistics')) { ?>
        <div class="widget-container" data-widget="statistics">
            <?php include_once WIDGETS_FOLDER . 'statistics.php'; ?>
        </div>
    <?php } ?>

    <?php if (current_user_can('view_news')) { ?>
        <div class="widget-container" data-widget="news">
            <?php include_once WIDGETS_FOLDER . 'news.php'; ?>
        </div>
    <?php } ?>

    <?php if (current_user_can('view_system_info')) { ?>
        <div class="widget-container" data-widget="system-info">
            <?php include_once WIDGETS_FOLDER . 'system-information.php'; ?>
        </div>
    <?php } ?>

    <?php if (current_user_can('view_actions_log')) { ?>
        <div class="widget-container" data-widget="actions-log">
            <?php include_once WIDGETS_FOLDER . 'actions-log.php'; ?>
        </div>
    <?php } ?>


    <?php if (current_user_can('view_storage_analytics')) { ?>
        <div class="widget-container" data-widget="storage-analytics">
            <?php include_once WIDGETS_FOLDER . 'storage-analytics.php'; ?>
        </div>
    <?php } ?>


    <?php if (current_user_can('view_download_analytics')) { ?>
        <div class="widget-container" data-widget="download-analytics">
            <?php include_once WIDGETS_FOLDER . 'download-analytics.php'; ?>
        </div>
    <?php } ?>



</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
