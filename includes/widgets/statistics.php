<?php
    if ( isset( $_GET['ajax_call'] ) ) {
        require_once '../../bootstrap.php';
    }

    $allowed_news = array(9,8,7);
    if (!in_array(CURRENT_USER_LEVEL,$allowed_news)) {
        exit;
    }
    $days_buttons = array(15, 30, 60);
    $default_days = $days_buttons[0];
?>
    <div class="widget" id="widget_statistics">
        <h4><?php _e('Statistics','cftp_admin'); ?></h4>
        <div class="widget_int">
            <div class="stats_change_days">
                <?php
                    foreach ( $days_buttons as $days ) {
                        $class = ($days == $default_days) ? 'btn-primary' : 'btn-pslight';
                ?>
                        <button class="get_statistics btn btn-md <?php echo $class; ?>" data-days="<?php echo $days; ?>">
                            <?php echo sprintf(__('%d days','cftp_admin'), $days); ?>
                        </button>
                <?php
                    }
                ?>
            </div>

            <div class="loading-icon none">
                <img src="<?php echo ASSETS_IMG_URL; ?>/loading.svg" alt="Loading" />
            </div>

            <div id="chart_container"></div>
        </div>
    </div>
