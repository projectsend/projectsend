<?php
	if ( isset( $_GET['ajax_call'] ) ) {
		require_once '../../bootstrap.php';
	}

	if (!current_user_can('view_news')) {
        exit;
    }
?>
    <div class="widget" id="widget_projectsend_news">
        <h4><?php _e('ProjectSend news','cftp_admin'); ?></h4>
        <div class="widget_int">
            <div class="loading-icon none">
                <img src="<?php echo ASSETS_IMG_URL; ?>/loading.svg" alt="Loading" />
            </div>

            <div id="news_container"></div>
        </div>
    </div>
