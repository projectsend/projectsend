<?php
	if ( isset( $_GET['ajax_call'] ) ) {
		require_once '../../bootstrap.php';
	}

	$allowed_news = array(9,8,7);
	if (in_array(CURRENT_USER_LEVEL,$allowed_news)) {
?>
		<div class="widget">
			<h4><?php _e('ProjectSend news','cftp_admin'); ?></h4>
			<div class="widget_int">
                <ul class="home_news list-unstyled">
                    <?php
                        // $feed = simplexml_load_file(NEWS_FEED_URI);
                        $feed = getJson(NEWS_FEED_URI, '-1 days');
                        $news = json_decode($feed);
        
                        $max_news = 99;
                        $n = 0;
                        foreach ($news as $item) {
                            if ($n < $max_news) {
                                $published_date = format_date($item->date);
                        ?>
                                <li>
                                    <span class="date"><?php echo $published_date; ?></span>
                                    <a href="<?php echo html_output($item->link); ?>" target="_blank">
                                        <h5><?php echo html_output($item->title); ?></h5>
                                    </a>
                                    <p><?php echo make_excerpt(html_output(strip_tags($item->content, '<br />')),200); ?>
                                </li>
                        <?php
                                $n++;
                            }
                        }
                    ?>
                </ul>
            </div>
		</div>
<?php
	}