<?php
/**
 * Shows a table of details of a file download information
 *
 * @package ProjectSend
 */
$footable_min = true; // delete this line after finishing pagination on every table
$load_scripts	= array(
						'footable',
						'flot',
					); 

$allowed_levels = array(9,8,7);
require_once('sys.includes.php');

$active_nav = 'files';

$page_title = __('Download information','cftp_admin');

/** Check if the id parameter is on the URI. */
if (isset($_GET['id'])) {
	$file_id = $_GET['id'];
	$page_status = (download_information_exists($file_id)) ? 1 : 2;
}
else {
	/**
	 * Return 0 if the id is not set.
	 */
	$page_status = 0;
}

/*
 * Get the file information from the database.
 */
if ($page_status === 1) {
	$file = get_file_by_id( $file_id );
	$general_stats = generate_downloads_count( $file_id );
	$file_stats = $general_stats[$file_id];

	$filename_on_disk = (!empty( $file['original_url'] ) ) ? $file['original_url'] : $file['url'];

	$page_title .= ': ' . $filename_on_disk;

	/**
	 * Make a list of users names
	 */
 	global $dbh;
	$names = $dbh->prepare("SELECT id, name FROM " . TABLE_USERS);
	$names->execute();
	if ( $names->rowCount() > 0 ) {
		$users_names = array();
		$names->setFetchMode(PDO::FETCH_ASSOC);
		while ( $row = $names->fetch() ) {
			$users_names[$row['id']] = $row['name'];
		}
	}
}

include('header.php');
?>

<div id="main">
	<h2><?php echo $page_title; ?></h2>

	<?php	
		if ($page_status === 0) {
			$msg = __('No file was selected.','cftp_admin');
			echo system_message('error',$msg);
			echo '<p>'.$direct_access_error.'</p>';
		}
		else if ($page_status === 2) {
			$msg = __('There is no information with that file ID number.','cftp_admin');
			echo system_message('error',$msg);
			echo '<p>'.$direct_access_error.'</p>';
		}
		else {
	?>

		<form action="download-information.php" name="groups_list" method="get" class="form-inline">
			<?php form_add_existing_parameters(); ?>
			<div class="container-fluid">
				<div class="row">
					<div class="col-sm-12">
						<h3><?php _e('Total downloads','cftp_admin'); ?>: <span class="label label-primary"><strong><?php echo $file_stats['total']; ?></strong></span></h3>
					</div>
				</div>
				<div class="row">
					<div class="col-sm-12">

						<?php
							$params = array();
							$cq = "SELECT * FROM " . TABLE_DOWNLOADS . " WHERE file_id = :id";
					
							/**
							 * Add the order.
							 * Defaults to order by: name, order: ASC
							 */
							$cq .= sql_add_order( TABLE_GROUPS, 'timestamp', 'desc' );

							$statement = $dbh->prepare($cq);

							$params[':id'] = $file_id;
							$statement->execute($params);

							/**
							 * Pre-query to count the total results
							*/
							$count_sql = $dbh->prepare( $cq );
							$count_sql->execute($params);
							$count_for_pagination = $count_sql->rowCount();
						
							/**
							 * Repeat the query but this time, limited by pagination
							 */
							$cq .= " LIMIT :limit_start, :limit_number";
							$sql = $dbh->prepare( $cq );
						
							$pagination_page			= ( isset( $_GET["page"] ) ) ? $_GET["page"] : 1;
							$pagination_start			= ( $pagination_page - 1 ) * RESULTS_PER_PAGE;
							$params[':limit_start']		= $pagination_start;
							$params[':limit_number']	= RESULTS_PER_PAGE;
						
							$sql->execute( $params );

							/**
							 * Generate the table using the class.
							 */
							$table_attributes	= array(
														'id'		=> 'download_info_tbl',
														'class'		=> 'footable table',
													);
							$table = new generateTable( $table_attributes );

							$thead_columns		= array(
														array(
															'sortable'		=> true,
															'sort_url'		=> 'timestamp',
															'sort_default'	=> true,
															'content'		=> __('Date','cftp_admin'),
														),
														array(
															'content'		=> __('Time','cftp_admin'),
														),
														array(
															'sortable'		=> true,
															'sort_url'		=> 'user_id',
															'content'		=> __('Client','cftp_admin'),
														),
														array(
															'sortable'		=> true,
															'sort_url'		=> 'anonymous',
															'content'		=> __('Anonymous','cftp_admin'),
														),
														array(
															'sortable'		=> true,
															'sort_url'		=> 'remote_ip',
															'content'		=> __("Client's IP",'cftp_admin'),
															'hide'			=> 'phone',
														),
														array(
															'sortable'		=> true,
															'sort_url'		=> 'remote_host',
															'content'		=> __("Client's hostname",'cftp_admin'),
															'hide'			=> 'phone',
														),
													);
							$table->thead( $thead_columns );

							$tfoot_columns		= array(
														array(
															'content'		=> '',
														),
														array(
															'content'		=> '',
														),
														array(
															'content'		=> __('Unique logged in clients/users','cftp_admin') . ': <span class="label label-primary">' . $file_stats['unique_clients'] . '</span>',
														),
														array(
															'content'		=> __('Total public downloads','cftp_admin') . ': <span class="label label-primary">' . $file_stats['anonymous_users'] . '</span>',
														),
														array(
															'content'		=> '',
														),
														array(
															'content'		=> '',
														),
													);
							$table->tfoot( $tfoot_columns );

							$sql->setFetchMode(PDO::FETCH_ASSOC);
							while ( $row = $sql->fetch() ) {
								$table->add_row();
			
								/**
								 * Prepare the information to be used later on the cells array
								 * 1- Get account download time and date
								 */
								$date = date(TIMEFORMAT_USE,strtotime($row['timestamp']));
								$time = date('h:s:i',strtotime($row['timestamp']));
								
								/**
								 * 2- Check if it's from a know user or anonymous
								 */
								$anon_yes	= __('Yes','cftp_admin');
								$anon_no	= __('No','cftp_admin');
								$label		= ($row['anonymous'] == '1') ? $anon_yes : $anon_no;
								$class		= ($row['anonymous'] == '1') ? 'warning' : 'success';
			
								/**
								 * Add the cells to the row
								 */
								$tbody_cells = array(
														array(
																'content'		=> $date,
															),
														array(
																'content'		=> $time,
															),
														array(
																'content'		=> ( !empty( $users_names[$row['user_id']] ) ) ? html_output( $users_names[$row['user_id']] ) : '',
															),
														array(
																'content'		=> '<span class="label label-' . $class . '">' . $label . '</span>',
															),
														array(
																'content'		=> html_output( $row['remote_ip'] ),
															),
														array(
																'content'		=> html_output( $row['remote_host'] ),
															),
													);
								
								
								foreach ( $tbody_cells as $cell ) {
									$table->add_cell( $cell );
								}
								
								$table->end_row();
							}

							echo $table->render();
			
							/**
							 * PAGINATION
							 */
							$pagination_args = array(
													'link'		=> 'download-information.php',
													'current'	=> $pagination_page,
													'pages'		=> ceil( $count_for_pagination / RESULTS_PER_PAGE ),
												);
							
							echo $table->pagination( $pagination_args );
						?>
					</div>
				</div>
			</div>
		</form>
	<?php
		}
	?>
</div>

<?php include('footer.php'); ?>