<?php
	if ( isset( $_GET['ajax_call'] ) ) {
        require_once '../../../bootstrap.php';
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
					if (defined('VERSION_NEW_NUMBER')) {
						echo ' - <strong>'; _e('New version available','cftp_admin'); echo ':</strong> <a href="'. VERSION_NEW_URL . '">' . VERSION_NEW_NUMBER . '</a>';
					}
				?>
			</dd>

			<dt><?php _e('Default upload max. size','cftp_admin'); ?></dt>
			<dd><?php echo MAX_FILESIZE; ?> mb.</dd>

			<dt><?php _e('Template','cftp_admin'); ?></dt>
			<dd><?php echo ucfirst(SELECTED_CLIENTS_TEMPLATE); ?> <a href="<?php echo BASE_URI; ?>templates.php">[<?php _e('Change','cftp_admin'); ?>]</a></dd>

			<?php
				/** Get the data to show on the bars graphic */
				$statement = $dbh->query("SELECT distinct id FROM " . TABLE_FILES );
				$total_files = $statement->rowCount();
			
				$statement = $dbh->query("SELECT distinct id FROM " . TABLE_USERS . " WHERE level = '0'");
				$total_clients = $statement->rowCount();
			
				$statement = $dbh->query("SELECT distinct id FROM " . TABLE_GROUPS);
				$total_groups = $statement->rowCount();
			
				$statement = $dbh->query("SELECT distinct id FROM " . TABLE_USERS . " WHERE level != '0'");
				$total_users = $statement->rowCount();

				$statement = $dbh->query("SELECT distinct id FROM " . TABLE_CATEGORIES);
				$total_categories = $statement->rowCount();
			?>
		</dl>

		<h3><?php _e('Data','cftp_admin'); ?></h3>
		<dl class="dl-horizontal">
			<dt><?php _e('Files','cftp_admin'); ?></dt>
			<dd><?php echo $total_files; ?></dd>

			<dt><?php _e('Clients','cftp_admin'); ?></dt>
			<dd><?php echo $total_clients; ?></dd>

			<dt><?php _e('System users','cftp_admin'); ?></dt>
			<dd><?php echo $total_users; ?></dd>

			<dt><?php _e('Groups','cftp_admin'); ?></dt>
			<dd><?php echo $total_groups; ?></dd>

			<dt><?php _e('Categories','cftp_admin'); ?></dt>
			<dd><?php echo $total_categories; ?></dd>

			<?php
				/**
				 * Hidden so it doesn't get shared by accident in any bug report
				<dt><?php _e('Root directory','cftp_admin'); ?></dt>
				<dd><?php echo ROOT_DIR; ?></dd>

				<dt><?php _e('Uploads folder','cftp_admin'); ?></dt>
				<dd><?php echo UPLOADED_FILES_FOLDER; ?></dd>
				*/
			?>
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