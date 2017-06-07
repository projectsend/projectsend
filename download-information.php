<?php
/**
 * Shows a table of details of a file download information
 *
 * @package ProjectSend
 */
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

	$page_title .= ': ' . $file['url'];

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
  <div id="content"> 
    
    <!-- Added by B) -------------------->
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
          <h1 class="page-title txt-color-blueDark"><i class="fa fa-download"></i>&nbsp;<?php echo $page_title; ?></h1>
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
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <h3>
                  <?php _e('Total downloads','cftp_admin'); ?>
                  : <span class="label label-primary"><strong><?php echo $file_stats['total']; ?></strong></span></h3>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-12">
                <table id="download_info_tbl" class="table table-striped table-bordered table-hover dataTable no-footer" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
                  <thead>
                    <tr>
                      <th data-type="numeric" data-sort-initial="descending"><?php _e('Date','cftp_admin'); ?></th>
                      <th data-sort-ignore="true"><?php _e('Time','cftp_admin'); ?></th>
                      <th><?php _e('Client','cftp_admin'); ?></th>
                      <th><?php _e('Anonymous','cftp_admin'); ?></th>
                      <th data-hide="phone"><?php _e("Client's IP",'cftp_admin'); ?></th>
                      <th data-hide="phone"><?php _e("Client's hostname",'cftp_admin'); ?></th>
                    </tr>
                  </thead>
                  <tfoot>
                    <tr>
                      <td></td>
                      <td></td>
                      <td><?php _e('Unique logged in clients/users','cftp_admin'); ?>
                        : <span class="label label-primary"><?php echo $file_stats['unique_clients']; ?></span></td>
                      <td><?php _e('Total public downloads','cftp_admin'); ?>
                        : <span class="label label-primary"><?php echo $file_stats['anonymous_users']; ?></span></td>
                      <td></td>
                      <td></td>
                    </tr>
                  </tfoot>
                  <tbody>
                    <?php
									$statement = $dbh->prepare("SELECT * FROM " . TABLE_DOWNLOADS . " WHERE file_id = :id");
									$statement->bindValue(':id', $file_id, PDO::PARAM_INT);
									$statement->execute();
								
									$statement->setFetchMode(PDO::FETCH_ASSOC);
									while ( $row = $statement->fetch() ) {
										$date = date(TIMEFORMAT_USE,strtotime($row['timestamp']));
										$time = date('h:s:i',strtotime($row['timestamp']));
								?>
                    <tr>
                      <td data-value="<?php echo strtotime($row['timestamp']); ?>"><?php echo $date; ?></td>
                      <td><?php echo $time; ?></td>
                      <td><?php echo ( !empty( $users_names[$row['user_id']] ) ) ? $users_names[$row['user_id']] : ''; ?></td>
                      <td><?php
													$anon_yes	= __('Yes','cftp_admin');
													$anon_no	= __('No','cftp_admin');
													$label		= ($row['anonymous'] == '1') ? $anon_yes : $anon_no;
													$class		= ($row['anonymous'] == '1') ? 'warning' : 'success';
												?>
                        <span class="label label-<?php echo $class; ?>"> <?php echo $label; ?> </span></td>
                      <td><?php echo $row['remote_ip']; ?></td>
                      <td><span class="format_url"><?php echo $row['remote_host']; ?></span></td>
                    </tr>
                    <?php
									}
								?>
                  </tbody>
                </table>
                <nav aria-label="<?php _e('Results navigation','cftp_admin'); ?>">
                  <div class="pagination_wrapper text-center">
                    <ul class="pagination hide-if-no-paging">
                    </ul>
                  </div>
                </nav>
              </div>
            </div>
          </div>
          <?php
		}
	?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include('footer.php'); ?>