<?php
/**
 * Show the list of activities logged.
 *
 * @package		ProjectSend
 * @subpackage	Log
 *
 */
$load_scripts	= array(
                        'datepicker',
			'footable',
		); 

$allowed_levels = array(9);
$cc_active_page = 'Activities log';
require_once('sys.includes.php');
$page_title = __('Activities Log','cftp_admin');;

include('header.php');
?>

<script type="text/javascript">
	$(document).ready( function() {
		$("#do_action").click(function() {
			var checks = $("td>input:checkbox").serializeArray(); 
			var action = $('#log_actions').val();

			if (action == 'delete') {
				if (checks.length == 0) { 
					alert('<?php _e('Please select at least one activity to proceed.','cftp_admin'); ?>');
					return false; 
				}
				else {
					var msg_1 = '<?php _e("You are about to delete",'cftp_admin'); ?>';
					var msg_2 = '<?php _e("activities. Are you sure you want to continue?",'cftp_admin'); ?>';
					if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
						return true;
					} else {
						return false;
					}
				}
			}
			if (action == 'clear') {
				var msg = '<?php _e("You are about to delete all activities from the log. Only those used for statistics will remain. Are you sure you want to continue?",'cftp_admin'); ?>';
				if (confirm(msg)) {
					return true;
				} else {
					return false;
				}
			}

			if (action == 'download') {
				$(document).psendmodal();
				$('.modal_content').html('<p class="loading-img">'+
											'<img src="<?php echo BASE_URI; ?>img/ajax-loader.gif" alt="Loading" /></p>'+
											'<p class="lead text-center text-info"><?php _e('Please wait while your download is prepared.','cftp_admin'); ?></p>'
										);
				$('.modal_content').append('<iframe src="<?php echo BASE_URI; ?>includes/actions.log.export.php?format=csv"></iframe>');
				return false;
			}
		});

		$('.date-container .date-field').datepicker({
		    format      : 'dd-mm-yyyy',
		    autoclose   : true
		});
               $('.date-container .date-field').datepicker("option", "maxDate", "0");
	});
</script>

<div id="main">
  <div id="content"> 
    
    <!-- Added by B) -------------------->
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
          <h1 class="page-title txt-color-blueDark"><i class="fa-fw fa fa-history"></i><?php echo $page_title; ?></h1>


<?php

	/**
	 * Apply the corresponding action to the selected users.
	 */
	if (isset($_POST['log_actions']) && $_POST['log_actions'] != 'none') {
		/** Continue only if 1 or more users were selected. */
			switch($_POST['log_actions']) {
				case 'delete':

					$selected_actions = $_POST['activities'];
					$delete_ids = implode( ',', $selected_actions );

					if ( !empty( $_POST['activities'] ) ) {
							$statement = $dbh->prepare("DELETE FROM " . TABLE_LOG . " WHERE FIND_IN_SET(id, :delete)");
							$params = array(
											':delete'	=> $delete_ids,
										);
							$statement->execute( $params );
						
							$msg = __('The selected activities were deleted.','cftp_admin');
							echo system_message('ok',$msg);
					}
					else {
						$msg = __('Please select at least one activity.','cftp_admin');
						echo system_message('error',$msg);
					}
				break;
				case 'clear':
					$keep = '5,8,9';
					$statement = $dbh->prepare("DELETE FROM " . TABLE_LOG . " WHERE NOT ( FIND_IN_SET(action, :keep) ) ");
					$params = array(
									':keep'	=> $keep,
								);
					$statement->execute( $params );

					$msg = __('The log was cleared. Only data used for statistics remained. You can delete them manually if you want.','cftp_admin');
					echo system_message('ok',$msg);
				break;
			}
	}

	$params	= array();

	$cq = "SELECT * FROM " . TABLE_LOG;

	/** Add the search terms */	
	if ( isset($_POST['search']) && !empty($_POST['search'] ) ) {
		$cq .= " WHERE (owner_user LIKE :owner OR affected_file_name LIKE :file OR affected_account_name LIKE :account)";
		$next_clause = ' AND';
		$no_results_error = 'search';
		
		$search_terms		= '%'.$_POST['search'].'%';
		$params[':owner']	= $search_terms;
		$params[':file']	= $search_terms;
		$params[':account']	= $search_terms;
	}
	else if(isset($_POST['date_from']) || isset($_POST['date_to'])) {				
		$date_f = $_POST['date_from'];				
		$date = date("Y-m-d", strtotime($date_f) );				
		$date_t = $_POST['date_to'];				
		$date_to = date("Y-m-d", strtotime($date_t) );				
		if($date === $date_to) {						
			$cq .= " WHERE (timestamp LIKE :date)";						
			$next_clause = ' AND';						
			$no_results_error = 'date search';						
			$params[':date']	= '%'.$date.'%';
		}				
		else {
			$cq .= " WHERE (timestamp BETWEEN :date AND :date_to)";						
			$next_clause = ' AND';						
			$no_results_error = 'date search';						
			$params[':date']	= $date;						
			$params[':date_to']	= $date_to;							
		}				
		
	}
	else if(isset($_POST['activity']) && $_POST['activity'] != 'all') {
		$next_clause = ' WHERE';
		$cq .= $next_clause. " action=:status";

		$status_filter		= $_POST['activity'];
		$params[':status']	= $status_filter;

		$no_results_error = 'filter';
	}
	else {
		
		$current_date = date("Y-m-d");
		$cq .= " WHERE (timestamp LIKE :current_date)" ;				
		$params[':current_date']	= '%'.$current_date.'%'; 
		$next_clause = ' AND';
	}

	$cq .= " ORDER BY id DESC";
	
	$sql = $dbh->prepare( $cq );
	$sql->execute( $params );
	$count = $sql->rowCount();
?>

	<div class="form_actions_left">
		<div class="form_actions_limit_results">
			<form action="actions-log.php" name="log_search" method="post" class="form-inline">
				<div class="form-group group_float">
					<input type="text" name="search" id="search" value="<?php if(isset($_POST['search']) && !empty($_POST['search'])) { echo html_output($_POST['search']); } ?>" class="txtfield form_actions_search_box form-control" />
				</div>
				<button type="submit" id="btn_proceed_search" class="btn btn-sm btn-default"><?php _e('Search','cftp_admin'); ?></button>
			</form>
                   <br>
		    <form action="actions-log.php" name="log_date_search" method="post" class="form-inline">
		      <div class="form-group">
			<label for="date_from">
			  <?php _e('Date from', 'cftp_admin');?>
			</label>
			<div class="input-group date-container">
			  <input type="text" class="date-field form-control datapick-field" readonly id="date_from" name="date_from" value="<?php echo isset($date_f)?$date_f: date("d-m-Y")?>" />
			  <div class="input-group-addon"> <i class="glyphicon glyphicon-time"></i> </div>
			</div>
		      </div>
		      <div class="form-group">
			<label for="date_to">
			  <?php _e('Date to', 'cftp_admin');?>
			</label>
			<div class="input-group date-container">
			  <input type="text" class="date-field form-control datapick-field" readonly id="date_to" name="date_to" value="<?php echo isset($date_t)?$date_t: date("d-m-Y")?>" />
			  <div class="input-group-addon"> <i class="glyphicon glyphicon-time"></i> </div>
			</div>
		      </div>
		      <button type="submit" id="btn_proceed_date_search" class="btn btn-sm btn-default"><?php _e('Filter','cftp_admin'); ?></button>
		    </form>
		    <br>
			<form action="actions-log.php" name="actions_filters" method="post" class="form-inline form_filters">
				<div class="form-group group_float">
					<label for="activity" class="sr-only"><?php _e('Filter activities','cftp_admin'); ?></label>
					<select name="activity" id="activity" class="form-control">
						<option value="all"><?php _e('All activities','cftp_admin'); ?></option>
						<?php
							global $activities_references;
								foreach ( $activities_references as $val => $text ) {
							?>
									<option value="<?php echo $val; ?>"><?php echo $text; ?></option>
							<?php
								}
							?>
					</select>
				</div>
				<button type="submit" id="btn_proceed_filter_clients" class="btn btn-sm btn-default"><?php _e('Filter','cftp_admin'); ?></button>
			</form>
		</div>
	</div>

	<form action="actions-log.php" name="actions_list" method="post" class="form-inline">
		<div class="form_actions_right">
			<div class="form_actions">
				<div class="form_actions_submit">
					<div class="form-group group_float">
						<label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Activities actions','cftp_admin'); ?>:</label>
						<select name="log_actions" id="log_actions" class="form-control">
							<option value="none"><?php _e('Select action','cftp_admin'); ?></option>
							<option value="download"><?php _e('Download as csv','cftp_admin'); ?></option>
							<option value="delete"><?php _e('Delete selected','cftp_admin'); ?></option>
							<option value="clear"><?php _e('Clear entire log','cftp_admin'); ?></option>
						</select>
					</div>
					<button type="submit" id="do_action" name="proceed" class="btn btn-sm btn-default"><?php _e('Proceed','cftp_admin'); ?></button>
				</div>
			</div>
		</div>
		<div class="clear"></div>

		<div class="form_actions_count">
			<p><?php _e('Showing','cftp_admin'); ?>: <span><?php echo $count; ?> <?php _e('activities','cftp_admin'); ?></span></p>
		</div>

		<div class="clear"></div>

		<?php
			if (!$count) {
				if (isset($no_results_error)) {
					switch ($no_results_error) {
						case 'filter':
							$no_results_message = __('The filters you selected returned no results.','cftp_admin');;
							break;
					}
				}
				else {
					$no_results_message = __('There are no activities recorded.','cftp_admin');;
				}
				echo system_message('error',$no_results_message);
			}
		?>
<section id="no-more-tables">
		<table id="activities_tbl" class="table footable" data-limit-navigation="10" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER_LOG; ?>">
			<thead>
				<tr>
					<th class="td_checkbox" data-sort-ignore="true">
						<input type="checkbox" name="select_all" id="select_all" value="0" />
					</th>
					<th data-type="numeric" data-sort-initial="descending"><?php _e('Date','cftp_admin'); ?></th>
					<th><?php _e('Author','cftp_admin'); ?></th>
					<th data-hide="phone"><?php _e('Activity','cftp_admin'); ?></th>
					<th data-hide="phone"><?php _e('Affected File','cftp_admin'); ?></th>
					<th data-hide="phone"><?php _e('from / to','cftp_admin'); ?></th>
					<th data-hide="phone"><?php _e('Affected Account','cftp_admin'); ?></th>
				</tr>
			</thead>
			<tbody>
			
			<?php
				$sql->setFetchMode(PDO::FETCH_ASSOC);
				while ( $log = $sql->fetch() ) {
					$this_action = render_log_action(
										array(
											'action'				=> $log['action'],
											'timestamp'				=> $log['timestamp'],
											'owner_id'				=> $log['owner_id'],
											'owner_user'			=> $log['owner_user'],
											'affected_file'			=> $log['affected_file'],
											'affected_file_name'	=> $log['affected_file_name'],
											'affected_account'		=> $log['affected_account'],
											'affected_account_name'	=> $log['affected_account_name']
										)
					);
					$date = date(TIMEFORMAT_USE,strtotime($log['timestamp']));
				?>
				<tr>
					<td><input type="checkbox" name="activities[]" value="<?php echo $log["id"]; ?>" /></td>
					<td data-value="<?php echo strtotime($log['timestamp']); ?>">
						<?php echo $date; ?>
					</td>
					<td><?php echo (!empty($this_action["1"])) ? $this_action["1"] : ''; ?></td>
					<td><?php echo $this_action["text"]; ?></td>
					<td><?php echo (!empty($this_action["2"])) ? $this_action["2"] : ''; ?></td>
					<td><?php echo (!empty($this_action["3"])) ? $this_action["3"] : ''; ?></td>
					<td><?php echo (!empty($this_action["4"])) ? $this_action["4"] : ''; ?></td>
				</tr>
						
				<?php
				}
			?>
			
			</tbody>
		</table>
</section>

		<nav aria-label="<?php _e('Results navigation','cftp_admin'); ?>">
            <div class="pagination_wrapper text-center">
                <ul class="pagination hide-if-no-paging"></ul>
            </div>
        </nav>
	</form>
	
</div></div></div></div></div>

<?php include('footer.php'); ?>



<style type="text/css">
/*-------------------- Responsive table by B) -----------------------*/
@media only screen and (max-width: 1200px) {
    #content {
        padding-top:30px;
    }
    
    /* Force table to not be like tables anymore */
    #no-more-tables table, 
    #no-more-tables thead, 
    #no-more-tables tbody, 
    #no-more-tables th, 
    #no-more-tables td, 
    #no-more-tables tr { 
        display: block; 
    }
 
    /* Hide table headers (but not display: none;, for accessibility) */
    #no-more-tables thead tr { 
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
 
    #no-more-tables tr { border: 1px solid #ccc; }
 
    #no-more-tables td { 
        /* Behave  like a "row" */
        border: none;
        border-bottom: 1px solid #eee; 
        position: relative;
        padding-left: 50%; 
        white-space: normal;
        text-align:left;
    }
 
    #no-more-tables td:before { 
        /* Now like a table header */
        position: absolute;
        /* Top/left values mimic padding */
        top: 6px;
        left: 6px;
        width: 45%; 
        padding-right: 10px; 
        white-space: nowrap;
        text-align:left;
        font-weight: bold;
    }
 
    /*
    Label the data
    */

    
    td:nth-of-type(1):before { content: ""; }
    td:nth-of-type(2):before { content: "Date"; }
    td:nth-of-type(3):before { content: "Author"; }
    td:nth-of-type(4):before { content: "Activity"; }
    td:nth-of-type(5):before { content: "Affected File"; }
    td:nth-of-type(6):before { content: "from / to"; }
    td:nth-of-type(7):before { content: "Affected Account"; }
}
/*-------------------- Responsive table End--------------------------*/
</style>
