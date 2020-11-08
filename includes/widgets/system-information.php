<?php
	if ( isset( $_GET['ajax_call'] ) ) {
		require_once '../../bootstrap.php';
    }
    
    $allowed_news = array(9);
    if (!in_array(CURRENT_USER_LEVEL,$allowed_news)) {
        exit;
    }
?>
<div class="widget widget_system_info">
	<h4><?php _e('System information','cftp_admin'); ?></h4>
	<div class="widget_int">
		<h3><?php _e('Software','cftp_admin'); ?></h3>
		<dl class="dl-horizontal">
			<dt><?php _e('Version','cftp_admin'); ?></dt>
			<dd>
				<?php echo CURRENT_VERSION; ?> <?php
                    $update_data = get_latest_version_data();
                    $update_data = json_decode($update_data);
                    if ($update_data->update_available == '1') {
						echo ' - <strong>'; _e('New version available','cftp_admin'); echo ':</strong> <a href="'. $update_data->url . '">' . $update_data->latest_version . '</a>';
					}
				?>
			</dd>

			<dt><?php _e('Default upload max. size','cftp_admin'); ?></dt>
			<dd><?php echo MAX_FILESIZE; ?> mb.</dd>

			<dt><?php _e('Template','cftp_admin'); ?></dt>
			<dd><?php echo ucfirst(get_option('selected_clients_template')); ?> <a href="<?php echo BASE_URI; ?>templates.php">[<?php _e('Change','cftp_admin'); ?>]</a></dd>
		</dl>

		<h3><?php _e('System','cftp_admin'); ?></h3>
		<dl class="dl-horizontal">
			<dt><?php _e('Server','cftp_admin'); ?></dt>
			<dd><?php echo $_SERVER["SERVER_SOFTWARE"]; ?>

			<dt><?php _e('PHP version','cftp_admin'); ?></dt>
			<dd><?php echo PHP_VERSION; ?></dd>

			<dt><?php _e('Memory limit','cftp_admin'); ?></dt>
			<dd><?php echo ini_get('memory_limit'); ?></dd>

			<dt><?php _e('Max execution time','cftp_admin'); ?></dt>
			<dd><?php echo ini_get('max_execution_time'); ?></dd>

			<dt><?php _e('Post max size','cftp_admin'); ?></dt>
			<dd><?php echo ini_get('post_max_size'); ?></dd>
		</dl>
		
		<h3><?php _e('Database','cftp_admin'); ?></h3>
		<dl class="dl-horizontal">
			<dt><?php _e('Driver','cftp_admin'); ?></dt>
			<dd><?php echo $dbh->getAttribute(PDO::ATTR_DRIVER_NAME); ?></dd>

			<dt><?php _e('Version','cftp_admin'); ?></dt>
			<dd><?php echo $dbh->query('select version()')->fetchColumn(); ?></dd>
		</dl>
	</div>
</div>