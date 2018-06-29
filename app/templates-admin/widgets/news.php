<?php
	$render_container = true;
	if ( isset( $_GET['ajax_call'] ) ) {
        require_once '../../../bootstrap.php';
		$render_container = false;
	}

	$allowed_news = array(9,8,7);
	if (in_array(CURRENT_USER_LEVEL,$allowed_news)) {
		if ( $render_container == true ) {
?>
			<div class="widget">
				<h4><?php _e('ProjectSend news','cftp_admin'); ?></h4>
				<div class="widget_int widget_news">
				</div>
			</div>
<?php
		}
		else {
            $news_get = file_get_contents(NEWS_JSON_URI);
			if ( !empty( $news_get ) ) {
?>
				<ul class="home_news">
                    <?php
                        $news_get = file_get_contents(NEWS_JSON_URI);
                        $news_json = json_decode( $news_get );
                        foreach ( $news_json as $item ) {
						?>
                            <li>
                                <span class="date"><?php echo date(TIMEFORMAT,strtotime($item->date)); ?></span>
                                <a href="<?php echo html_output($item->link); ?>" target="_blank">
                                    <h5><?php echo html_output($item->title); ?></h5>
                                </a>
                                <p><?php echo make_excerpt(html_output(strip_tags($item->content, '<br />')),200); ?>
                            </li>
						<?php
						}
					?>
				</ul>
<?php
			}
			else {
?>
				<div class="alert alert-warning">
					<?php echo sprintf(__('News cannot be loaded.', 'cftp_admin'), 'simplexml_load_file'); ?>
				</div>
<?php
			}
		}
	}
