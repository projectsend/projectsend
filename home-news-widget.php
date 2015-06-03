<?php
	require_once('sys.includes.php');

	$allowed_news = array(9,8,7);
	if (in_array(CURRENT_USER_LEVEL,$allowed_news)) {
?>
		<div class="widget">
			<h4><?php _e('ProjectSend news','cftp_admin'); ?></h4>
			<div class="widget_int">
				<ul class="home_news">
					<?php
						$feed = simplexml_load_file(NEWS_FEED_URI);
						$max_news = 3;
						$n = 0;
						foreach ($feed->channel->item as $item) {
							if ($n < $max_news) {
						?>
								<li>
									<span class="date"><?php echo date(TIMEFORMAT_USE,strtotime($item->pubDate)); ?></span>
									<a href="<?php echo $item->link; ?>" target="_blank">
										<h5><?php echo $item->title; ?></h5>
									</a>
									<p><?php echo make_excerpt(strip_tags($item->description, '<br />'),200); ?>
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
?>