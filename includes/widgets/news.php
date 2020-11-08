<?php
	if ( isset( $_GET['ajax_call'] ) ) {
		require_once '../../bootstrap.php';
	}

	$allowed_news = array(9,8,7);
	if (in_array(CURRENT_USER_LEVEL,$allowed_news)) {
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
<?php
	}