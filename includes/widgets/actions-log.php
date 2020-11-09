<?php
    if ( isset( $_GET['ajax_call'] ) ) {
        require_once '../../bootstrap.php';
    }

    $allowed_news = array(9,8,7);
    if (!in_array(CURRENT_USER_LEVEL,$allowed_news)) {
        exit;
    }
?>
    <div class="widget" id="widget_actions_log">
        <h4><?php _e('Recent activities','cftp_admin'); ?></h4>
        <div class="widget_int">
            <div class="log_change_action">
                <select name="action" id="widget_actions_log_change" class="form-control">
                    <option value="all"><?php _e('All activities','cftp_admin'); ?></option>
                    <?php
                        $logger = new \ProjectSend\Classes\ActionsLog;
                        $activities_references = $logger->getActivitiesReferences();
                        foreach ($activities_references as $number => $name) {
                    ?>
                            <option value="<?php echo $number; ?>"><?php echo $name; ?></option>
                    <?php
                        }
                    ?>
                </select>
            </div>
            <div id="log_container"></div>

            <div class="loading-icon none">
                <img src="<?php echo ASSETS_IMG_URL; ?>/loading.svg" alt="Loading" />
            </div>

            <div class="view_full_log">
                <a href="<?php echo BASE_URI; ?>actions-log.php" class="btn btn-primary btn-wide"><?php _e('View all','cftp_admin'); ?></a>
            </div>
        </div>
    </div>
