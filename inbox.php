<?php
/**
 * Allows to hide, show or delete the files assigend to the
 * selected client.
 *
 * @package ProjectSend
 */
$load_scripts	= array(
						'footable',
					);

$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

$active_nav = 'files';
$cc_active_page = 'Inbox Files';

$page_title = __('Inbox Files','cftp_admin');

$current_level = get_current_user_level();
$this_user = CURRENT_USER_USERNAME;
$client_info = get_client_by_username($this_user);

$sql_groups = $dbh->prepare( "SELECT DISTINCT group_id FROM " . TABLE_MEMBERS . " WHERE (client_id=:id AND m_org_status= '1') " );
$sql_groups->bindParam(':id', $client_info['id'], PDO::PARAM_INT);
$sql_groups->execute();
$count_groups = $sql_groups->rowCount();

if ($count_groups > 0) {
	$sql_groups	->setFetchMode(PDO::FETCH_ASSOC);
	while ( $row = $sql_groups->fetch() ) {
		$groups_ids[] = $row["group_id"];
	}
	$found_groups = implode(',',$groups_ids);
}

/*
 * Get the total downloads count here. The results are then
 * referenced on the results table.
 */
$downloads_information = generate_downloads_count();

/**
 * Used to distinguish the current page results.
 * Global means all files.
 * Client or group is only when looking into files
 * assigned to any of them.
 */
$results_type = 'global';

/**
 * The client's id is passed on the URI.
 * Then get_client_by_id() gets all the other account values.
 */

if (isset($_GET['client_id'])) {
	$this_id = $_GET['client_id'];
	$this_client = get_client_by_id($this_id);
	/** Add the name of the client to the page's title. */
	if(!empty($this_client)) {
		$page_title .= ' '.__('for client','cftp_admin').' '.html_entity_decode($this_client['name']);
		$search_on = 'client_id';
		$name_for_actions = $this_client['username'];
		$results_type = 'client';
	}
}

/**
 * The group's id is passed on the URI also.
 */
if (isset($_GET['group_id'])) {
	$this_id = $_GET['group_id'];


	$sql_name = $dbh->prepare("SELECT name from " . TABLE_GROUPS . " WHERE id=:id");
	$sql_name->bindParam(':id', $this_id, PDO::PARAM_INT);
	$sql_name->execute();							

	if ( $sql_name->rowCount() > 0) {
		$sql_name->setFetchMode(PDO::FETCH_ASSOC);
		while( $row_group = $sql_name->fetch() ) {
			$group_name = $row_group["name"];
		}
		/** Add the name of the client to the page's title. */
		if(!empty($group_name)) {
			$page_title .= ' '.__('for group','cftp_admin').' '.html_entity_decode($group_name);
			$search_on = 'group_id';
			$name_for_actions = html_entity_decode($group_name);
			$results_type = 'group';
		}
	}
}

/** Fetch all categories */
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_CATEGORIES);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);
	$categories = $statement->fetchAll();
	
	
/**
 * Filtering by category
 */
if (isset($_GET['category'])) {
	$this_id = $_GET['category'];
	$this_category = get_category($this_id);

	/** Add the name of the client to the page's title. */
	if(!empty($this_category)) {
	
		$page_title .= ' '.__('on category','cftp_admin').' '.html_entity_decode($this_category['name']);
		$name_for_actions = $this_category['name'];
		$results_type = 'category';
	}
}

include('header.php');
?>
<style>
.hideNow{
display:none;
}
.requestType {
font-weight:bold;
}
.requestType.normal {
color:#5cb85c;
}
.requestType.requested {
color:#337ab7;
}
.requestType.guest {
color:#e33a49;
}
.delBtn ,.unAssnBtn{
-webkit-appearance: none;
background-color: transparent;
border: none;
cursor: pointer;
}
.delBtn{
 color: red;
 }
 .unAssnBtn{
 color: black;
}
</style>
<script type="text/javascript">
	$(document).ready(function() {
		$("#do_action").click(function() {
			var checks = $("td input:checkbox").serializeArray();

		//	console.log($('#files_actions').val);
			var actType = $('#files_actions').val();
			if (actType == 'show') {
				return true;
			}
			else if (checks.length == 0) {
					alert('<?php _e('Please select at least one file to proceed.','cftp_admin'); ?>');
					return false;
			}
			else {
				// var action = $('#files_actions').val();
				// if (action == 'delete') {
				// 	var msg_1 = '<?php // _e("You are about to delete",'cftp_admin'); ?>';
				// 	if(checks.length > 1){
				// 		var msg_2 = 'files from your Inbox permanently. Only your copy will be deleted. Are you sure you want to continue?';
				// 	}
				// 	else{
				// 		var msg_2 = 'file from your Inbox permanently. Only your copy will be deleted. Are you sure you want to continue?';
				// 	}
				//
				// 	if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
				// 		return true;
				// 	} else {
				// 		return false;
				// 	}
				// }
				// else if (action == 'unassign') {
				// 	var msg_1 = '<?php //_e("You are about to unassign",'cftp_admin'); ?>';
				// 	var msg_2 = '<?php //_e("files from this account. Are you sure you want to continue?",'cftp_admin'); ?>';
				// 	if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
				// 		return true;
				// 	} else {
				// 		return false;
				// 	}
				// }
			}
		});

	    $('.public_link').popover({ 
			html : true,
			content: function() {
				var id		= $(this).data('id');
				var token	= $(this).data('token');
				return '<strong><?php _e('Click to select','cftp_admin'); ?></strong><textarea class="input-large public_link_copy" rows="4"><?php echo BASE_URI; ?>download.php?id=' + id + '&token=' + token + '</textarea><small><?php _e('Send this URL to someone to download the file without registering or logging in.','cftp_admin'); ?></small><div class="close-popover"><button type="button" class="btn btn-inverse btn-sm"><?php _e('Close','cftp_admin'); ?></button></div>';
			}
		});

		$(".col_visibility").on('click', '.close-popover button', function(e) {
			var popped = $(this).parents('.col_visibility').find('.public_link');
			popped.popover('hide');
		});

		$(".col_visibility").on('click', '.public_link_copy', function(e) {
			$(this).select();
			$(this).mouseup(function() {
				$(this).unbind("mouseup");
				return false;
			});
		});
	});
</script>

<div id="main"> 
  <!-- MAIN CONTENT -->
  <div id="content"> 
    
    <!-- Added by B) -------------------->
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
          <h2 class="page-title txt-color-blueDark"><?php echo $page_title; ?></h2>
          <?php
		/**
		 * Apply the corresponding action to the selected files.
		 */

		 if(isset($_POST['delete_file'])) {
			 // print_r($_POST);
			 $file_id = $_POST['delete_file'];
			 $delete_results	= array(
									 'ok'		=> 0,
									 'errors'	=> 0,
								 );
				 $this_file		= new FilesActions();
				 $delete_status	= $this_file->delete_inbox_files($file_id);
				 if ( $delete_status == true ) {
					 $delete_results['ok']++;
				 }
				 else {
					 $delete_results['errors']++;
					 unset($all_files[$file_id]);
				 }

			 if ( $delete_results['ok'] > 0 ) {
				 $msg = __('The selected file deleted.','cftp_admin');
				 echo system_message('ok',$msg);
				 $log_action_number = 12;
			 }
			 if ( $delete_results['errors'] > 0 ) {
				 $msg = __('Some files could not be deleted.','cftp_admin');
				 echo system_message('error',$msg);
			 }
		  }
		 if(isset($_POST['unassign_file'])) {
			 // print_r($_POST);
			 $file_id = $_POST['unassign_file'];
			 $this_file = new FilesActions();
			 $this_file->unassign($file_id);
			 $msg = __('The selected file unassigned from this client.','cftp_admin');
			 echo system_message('ok',$msg);
			 $log_action_number = 11;

		  }


		if(isset($_POST['do_action'])) {
			/** Continue only if 1 or more files were selected. */

			if ($_POST['files_actions']=='show') {
				$this_file = new FilesActions();
				$filedetails=$this_file->show_inbox();
				$msg = __('All hidden files were marked as visible.','cftp_admin');
				echo system_message('ok',$msg);
				$log_action_number = 22;
				if(!empty($filedetails)){
					foreach ($filedetails as $work_file) {
						$affected_name= $dbh->prepare("SELECT filename from ".TABLE_FILES." WHERE id = ".$work_file['file_id']);
						$affected_name->execute();
						$af_name= $affected_name->fetchAll(PDO::FETCH_ASSOC);
							$new_log_action = new LogActions();
							$log_action_args = array(
																			'action' => $log_action_number,
																			'owner_id' => $global_id,
																			'affected_file' => $work_file['file_id'],
																			'affected_file_name' => $af_name[0]['filename'],
																	);
							$log_action_args['affected_account_name'] = CURRENT_USER_USERNAME ;
							$new_record_action = $new_log_action->log_action_save($log_action_args);
							}
					}
			}
		 	else if(!empty($_POST['files'])) {
				$selected_files = array_map('intval',array_unique($_POST['files']));
				$files_to_get = implode(',',$selected_files);
				$sql_file = $dbh->prepare("SELECT id, filename FROM " . TABLE_FILES . " WHERE FIND_IN_SET(id, :files)");
				$sql_file->bindParam(':files', $files_to_get);
				$sql_file->execute();
				$sql_file->setFetchMode(PDO::FETCH_ASSOC);
				while( $data_file = $sql_file->fetch() ) {
					$all_files[$data_file['id']] = $data_file['filename'];
				}
				
				switch($_POST['files_actions']) {
					case 'hide':
						/**
						 * Changes the value on the "hidden" column value on the database.
						 * This files are not shown on the client's file list. They are
						 * also not counted on the home.php files count when the logged in
						 * account is the client.
						 */
						foreach ($selected_files as $work_file) {
								$this_file = new FilesActions();
								$this_file->hide_inbox($work_file);
						}
						$msg = __('The selected files were marked as hidden.','cftp_admin');
						echo system_message('ok',$msg);
						$log_action_number = 21;
						break;



					case 'unassign':
						/**
						 * Remove the file from this client or group only.
						 */
						foreach ($selected_files as $work_file) {
								$this_file = new FilesActions();
								$this_file->unassign($work_file);
						}
						$msg = __('The selected files were unassigned from this client.','cftp_admin');
						echo system_message('ok',$msg);
						$log_action_number = 11;
						break;

					case 'delete':
						$delete_results	= array(
												'ok'		=> 0,
												'errors'	=> 0,
											);
						foreach ($selected_files as $index => $file_id) {
							$this_file		= new FilesActions();
							$delete_status	= $this_file->delete_inbox_files($file_id);
							if ( $delete_status == true ) {
								$delete_results['ok']++;
							}
							else {
								$delete_results['errors']++;
								unset($all_files[$file_id]);
							}
						}

						if ( $delete_results['ok'] > 0 ) {
							$msg = __('The selected files were deleted.','cftp_admin');
							echo system_message('ok',$msg);
							$log_action_number = 12;
						}
						if ( $delete_results['errors'] > 0 ) {
							$msg = __('Some files could not be deleted.','cftp_admin');
							echo system_message('error',$msg);
						}
						break;
				}

				/** Record the action log */
				foreach ($all_files as $work_file_id => $work_file) {
					$new_log_action = new LogActions();
					$log_action_args = array(
											'action' => $log_action_number,
											'owner_id' => $global_id,
											'affected_file' => $work_file_id,
											'affected_file_name' => $work_file
										);
					if (!empty($name_for_actions)) {
						$log_action_args['affected_account_name'] = $name_for_actions;
						$log_action_args['get_user_real_name'] = true;
					}
					$new_record_action = $new_log_action->log_action_save($log_action_args);
				}
			}

			else {
				$msg = __('Please select at least one file.','cftp_admin');
				echo system_message('error',$msg);
			}
		}
		
		/**
		 * Global form action
		 */
		$form_action_url = 'inbox.php';
		
		$query_table_files = true;

		if (isset($search_on)) {
			$params = array();
			$cq = "SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE $search_on = :id";
			$params[':id'] = $this_id;
			$form_action_url .= '?'.$search_on.'='.$this_id;

			

			/**
			 * Count the files assigned to this client. If there is none, show
			 * an error message.
			 */
			$sql = $dbh->prepare($cq);
			$sql->execute( $params );
			
			if ( $sql->rowCount() > 0) {
				/**
				 * Get the IDs of files that match the previous query.
				 */
				$sql->setFetchMode(PDO::FETCH_ASSOC);
				while( $row_files = $sql->fetch() ) {
					$files_ids[] = $row_files['file_id'];
					$gotten_files = implode(',',$files_ids);
				}
			}
			else {
				$count = 0;
				$no_results_error = 'filter';
				$query_table_files = false;
			}
		}

		if ( $query_table_files === true ) {
			/**
			 * Get the files
			 */
			$today = date("Y-m-d H:i:s",strtotime("tomorrow"));
			$params = array();
			$fq = "SELECT tbl_files.* ,tbl_files_relations.* FROM tbl_files LEFT JOIN tbl_files_relations ON tbl_files.id = tbl_files_relations.file_id ";


			$conditions[] = "tbl_files_relations.client_id =".CURRENT_USER_ID;
			$conditions[] = "tbl_files_relations.hide_inbox = '0' ";
			/** Check expires status for no file message */
		 	$current_date = date("Y-m-d");
        	$conditions[] = "tbl_files.expires = '0' || tbl_files.expires = '1' && tbl_files.expiry_date >'".$current_date."'";
			if ( isset($search_on) && !empty($gotten_files) ) {
				$conditions[] = "FIND_IN_SET(id, :files)";
				$params[':files'] = $gotten_files;
			}
	
			/** Add the search terms */	
			if(isset($_GET['search']) && !empty($_GET['search'])) {
				$term = "%".$_GET['search']."%";
				$conditions[] = "(filename LIKE '$term' OR description LIKE '$term')";
				$no_results_error = 'search';
	
				$search_terms			= $term;
				$params[':name']		= $search_terms;
				$params[':description']	= $search_terms;
			}
	
			/**
			 * If the user is an uploader, or a client is editing his files
			 * only show files uploaded by that account.
			*/
			$current_level = get_current_user_level();
			if ($current_level == '7' || $current_level == '8' || $current_level == '0' || $current_level == '9') {
				$conditions[] = "tbl_files_relations.client_id =" . CURRENT_USER_ID;
				$conditions[] = "tbl_files.future_send_date< DATE('".$today."')";
				$params[':uploader'] = $global_user;
			}
			
			/**
			 * Add the category filter
			 */
			if ( isset( $results_type ) && $results_type == 'category' ) {
				$children = array();
				$stat = $dbh->prepare("SELECT id FROM tbl_categories WHERE parent = :parent_id");
				$stat->bindParam(':parent_id', $this_category['id'], PDO::PARAM_INT);
				$stat->execute();
				$stat->setFetchMode(PDO::FETCH_ASSOC);
				$children = $stat->fetchAll();
				$cats = array();
				$cats['0']= $this_category['id'];
					if(!empty($children)){
						foreach ($children as $child) {
							$cats[]= $child['id'];
						}
					}
				// print_r($cats);
				$files_id_by_cat = array();
				$catquery = "SELECT file_id FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE cat_id IN (".implode(',',$cats).")";
				$statement = $dbh->prepare($catquery);
				$statement->execute();
				$statement->setFetchMode(PDO::FETCH_ASSOC);
				$file_data = $statement->fetchAll();
				
				if(!empty($file_data)) {
					foreach ( $file_data as $data) {
						$files_id_by_cat[] = $data['file_id'];
					}
					
					$files_id_by_cat = implode(',',$files_id_by_cat);
					/** Overwrite the parameter set previously */
					$conditions[] = "FIND_IN_SET(tbl_files.id, '".$files_id_by_cat."')";
					$params[':files'] = $files_id_by_cat;
				}
				else {
					$conditions[] = "FIND_IN_SET(tbl_files.id, 'not found')";
					$no_results_error = 'category';
				}
				
			}
	
			/**
			 * Build the final query
			 */
			if ( !empty( $conditions ) ) { 
				foreach ( $conditions as $index => $condition ) { 
					
					//$fq .= if($index == 0) ? ' WHERE ' : ' AND ';
					if($index == 0) {
						$var_1 = 'WHERE';
					}
					else {
						$var_1 = 'AND';
					}
					$fq .= ' '.$var_1.' '.$condition;
					
				}
			}

			if (!empty($found_groups)) {
				$fq .= " OR FIND_IN_SET(group_id, :groups)";
				if(isset($_GET['search']) && !empty($_GET['search'])) {
					$term = "%".$_GET['search']."%";
					$fq .=  "AND (filename LIKE '$term' OR description LIKE '$term')";
					$no_results_error = 'search';

					$search_terms			= $term;
					$params[':name']		= $search_terms;
					$params[':description']	= $search_terms;
				}
			}
			$fq .= "ORDER BY tbl_files_relations.timestamp DESC";
			$sql_files = $dbh->prepare($fq);
			if (!empty($found_groups)) {
				$sql_files->bindParam(':groups', $found_groups);
			}
			$sql_files->execute();
			
			$count = $sql_files->rowCount();
		}
	?>
          <div class="form_actions_left">
            <div class="form_actions_limit_results">
              <form action="<?php echo html_output($form_action_url); ?>" name="files_search" method="GET" class="form-inline">
                <div class="form-group group_float">
                  <input type="text" name="search" id="search" value="<?php if(isset($_GET['search']) && !empty($search_terms)) { echo html_output($_GET['search']); } ?>" class="txtfield form_actions_search_box form-control" />
                </div>

                <div class="form-group group_float">
                  <select name="category" id="category" class="txtfield form-control">
                    <option value="0">All categories</option>
                    <?php

									if(!empty($categories)){

										foreach ( $categories as $cat ) {
											if($cat['parent'] == ''){
									?>
                    <option <?php if(!empty($this_id)){if($this_id == $cat['id'] ){ echo "selected";}}?> value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                    <?php
										foreach ($categories as $childcat) {
											if($childcat['parent'] == $cat['id']){
											 ?>
											<option <?php if(!empty($this_id)){if($this_id == $childcat['id'] ){ echo "selected";}}?> value="<?php echo $childcat['id']; ?>"> &nbsp&nbsp<?php echo $childcat['name']; ?></option>
											<?php
												}
											}
											}
										}
									}
									?>
                  </select>
                </div>
                <button type="submit" id="btn_proceed_search" class="btn btn-sm btn-default">
                <?php _e('Search','cftp_admin'); ?>
                </button>
              </form>
            </div>
          </div>
          <form action="<?php echo html_output($form_action_url); ?>" name="files_list" method="post" class="form-inline">
            <div class="form_actions_right">
              <div class="form_actions">
                <div class="form_actions_submit">
                  <div class="form-group group_float">
                    <label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i>
                      <?php _e('Selected files actions','cftp_admin'); ?>
                      :</label>
                    <?php
										if (isset($search_on)) {
									?>
                    <input type="hidden" name="modify_type" id="modify_type" value="<?php echo $search_on; ?>" />
                    <input type="hidden" name="modify_id" id="modify_id" value="<?php echo $this_id; ?>" />
                    <?php
										}
									?>
                    <select name="files_actions" id="files_actions" class="txtfield form-control">
										<option value="show">
										<?php _e('Show All','cftp_admin'); ?>
										</option>
                      <option value="hide">
                      <?php _e('Hide','cftp_admin'); ?>
                      </option>
											<?php
												/** Actions are not available for clients */
												if($current_level != 0) { /*
											?>
                      <option value="unassign">
                      <?php _e('Unassign','cftp_admin'); ?>
                      </option>
                      <option value="delete">
                      <?php _e('Delete','cftp_admin'); ?>
                      </option>
											<?php
										*/	}
										?>
                    </select>
                  </div>
                  <button type="submit" name="do_action" id="do_action" class="btn btn-sm btn-default">
                  <?php _e('Proceed','cftp_admin'); ?>
                  </button>
                </div>
              </div>
            </div>

            <div class="clear"></div>
            <div class="form_actions_count">
              <p class="form_count_total">
                <?php _e('Showing','cftp_admin'); ?>
                : <span><?php echo $count; ?>
                <?php _e('files','cftp_admin'); ?>
                </span></p>
            </div>
            <div class="clear"></div>
            <?php
				if (!$count) {
					if (isset($no_results_error)) {
						switch ($no_results_error) {
							case 'search':
								$no_results_message = __('Your search keywords returned no results.','cftp_admin');;
								break;
							case 'category':
								$no_results_message = __('There are no files assigned to this category.','cftp_admin');;
								break;
							case 'filter':
								$no_results_message = __('The filters you selected returned no results.','cftp_admin');;
								break;
							case 'none_assigned':
								$no_results_message = __('There are no files assigned to this client.','cftp_admin');;
								break;
							case 'account_level':
								$no_results_message = __('You have not uploaded any files for this account.','cftp_admin');;
								break;
						}
					}
					else {
						$no_results_message = __('There are no files for this client.','cftp_admin');;
					}
					echo system_message('error',$no_results_message);
				}
			?>
            <section id="no-more-tables" class="cc-overflow-scroll">
            <table id="files_list" class="cc-mail-listing-style table table-striped table-bordered table-hover dataTable no-footer" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
              <thead>
                <tr>
                  <?php
							/** Actions are not available for clients if delete own files is false */
							// if($current_level != 0) {
						?>
                  <th class="td_checkbox" data-sort-ignore="true"> 
                  <label class="cc-chk-container">
                      <input type="checkbox" name="select_all" id="select_all" value="0" />
                      <span class="checkmark"></span>
                  </label>
                  </th>
                  <?php
							// }
						?>
                  <th data-type="numeric" data-sort-initial="descending" data-hide="phone"><?php _e('Date','cftp_admin'); ?></th>
                  <th data-hide="phone,tablet"><?php _e('Ext.','cftp_admin'); ?></th>
                  <th><?php _e('Title','cftp_admin'); ?></th>

                  <th><?php _e('Size','cftp_admin'); ?></th>
				  <th data-hide="phone,tablet" data-sort-ignore="true"><?php _e('Download/Limit','cftp_admin'); ?></th>
                  <th data-hide="phone,tablet"><?php _e('Uploader','cftp_admin'); ?></th>
                  <?php
							if($current_level != 0) {
						?>

                  <?php
									if ( !isset( $search_on ) ) {
								?>
                  <th class="hideNow" data-hide="phone"><?php _e('Assigned','cftp_admin'); ?></th>
                  <?php
									}
								?>
                  <th class="hideNow" data-hide="phone"><?php _e('Public','cftp_admin'); ?></th>
                  <?php
							} ?>
							<th data-hide="phone"><?php _e('Expiry','cftp_admin'); ?></th>
						<?php 	/**
							 * These columns are only available when filtering by client or group.
							 */
							if (isset($search_on)) {
						?>
                  <th data-hide="phone"><?php _e('Status','cftp_admin'); ?></th>
                  <th data-hide="phone"><?php _e('Download count','cftp_admin'); ?></th>
                  <?php
							}
							else {
								if($current_level != 0) {
						?>
                  <th data-hide="phone"><?php _e('Total downloads','cftp_admin'); ?></th>
                  <?php
								}
							}
						?>
                  <th data-hide="phone">Type</th>
		  <th data-hide="phone" data-sort-ignore="true"><?php _e('Actions','cftp_admin'); ?></th>
								 
								</tr>
              </thead>
              <tbody>
                <?php
						if ($count > 0) {
							$sql_files->setFetchMode(PDO::FETCH_ASSOC);
							//echo CURRENT_USER_USERNAME;
							while( $row = $sql_files->fetch() ) {
								$uploader_cc= trim($row['uploader']);
								if($uploader_cc == CURRENT_USER_USERNAME) {
									$edit_access = true;
								}
								else {
									$edit_access = '';
								}

								$current_download_count = current_download_count_user($row['file_id']);

								//echo $current_download_count;
								//echo $file_id = $row['id'];
								
								/**
								 * Construct the complete file URI to use on the download button.
								 */
								$this_file_absolute = UPLOADED_FILES_FOLDER.$row['url'];
								$this_file_uri = BASE_URI.UPLOADED_FILES_URL.$row['url'];
																
								/**
								 * Download count and visibility status are only available when
								 * filtering by client or group.
								 */
								$params = array();
								$query_this_file = "SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :file_id";
								$params[':file_id'] = $row['id'];

								if (isset($search_on)) {
									$query_this_file .= " AND $search_on = :id";
									$params[':id'] = $this_id;
									/**
									 * Count how many times this file has been downloaded
									 * Here, the download count is specific per user.
									 */
									switch ($results_type) {
										case 'client':
												$download_count_sql	= $dbh->prepare("SELECT user_id, file_id FROM " . TABLE_DOWNLOADS . " WHERE file_id = :file_id AND user_id = :user_id");
												$download_count_sql->bindParam(':file_id', $row['id'], PDO::PARAM_INT);
												$download_count_sql->bindParam(':user_id', $this_id, PDO::PARAM_INT);
												$download_count_sql->execute();
												$download_count	= $download_count_sql->rowCount();
											break;

										case 'group':
										case 'category':
												$download_count_sql	= $dbh->prepare("SELECT file_id FROM " . TABLE_DOWNLOADS . " WHERE file_id = :file_id");
												$download_count_sql->bindParam(':file_id', $row['id'], PDO::PARAM_INT);
												$download_count_sql->execute();
												$download_count	= $download_count_sql->rowCount();
											break;
									}
								}

								$sql_this_file = $dbh->prepare($query_this_file);
								$sql_this_file->execute( $params );
								$sql_this_file->setFetchMode(PDO::FETCH_ASSOC);

								$count_assignations = $sql_this_file->rowCount();

								while( $data_file = $sql_this_file->fetch() ) {
									$file_id = $data_file['id'];
									$hidden = $data_file['hidden'];
								}
								$date = date(TIMEFORMAT_USE,strtotime($row['timestamp']));
								/**
								 * Get file size only if the file exists
								 */
								if ( file_exists( $this_file_absolute ) ) {
									$this_file_size = get_real_size($this_file_absolute);
									$formatted_size = html_output(format_file_size($this_file_size));
								}
								else {
									$this_file_size = '0';
									$formatted_size = '-';
								}


	
        if(($row['expires'] == 0) || (time() < strtotime($row['expiry_date']))) {
			
		      ?>
                <tr>
                
                  <?php
										/** Actions are not available for clients */
										// if($current_level != 0) {
									?>
                  <td>
                  <label class="cc-chk-container">
                      <input type="checkbox" name="files[]" value="<?php echo $row['id']; ?>" <?php echo  isset($disabled)?$disabled:''; ?> />
                      <span class="checkmark"></span>
                  </label>
                  </td>
                  <?php
										// }
									?>
                  <td data-value="<?php echo strtotime($row['timestamp']); ?>"><?php echo $date; ?></td>
                  <td><?php
											$pathinfo = pathinfo($row['url']);
											$extension = strtolower($pathinfo['extension']);
											echo html_output($extension);
										?></td>
                                    <td class="file_name">
                                        <?php
                                            $download_link = BASE_URI.'process.php?do=download&amp;client='.$global_user.'&amp;id='.$row['file_id'].'&amp;n=1&amp;request_type='.$row['request_type'].'';


                                            // $download_link = BASE_URI.'process.php?do=download&amp;client='.$global_user.'&amp;id='.$row['file_id'].'&amp;n=1';

                                        /**
                                        * Clients can download from here.
                                        *It was like client cannot download. But now changed to Can.
                                        */
                                        if($current_level == '0')
                                        {
                                            if((($current_download_count>=$row['number_downloads'] && $current_download_count>0 && $row['number_downloads']!=0)) || (($row['expires'] != '0') && (time() > strtotime($row['expiry_date'])))) {
                                                echo html_output($row['filename']);
                                            }
                                            else
                                            {
                                                ?>
                                                <a href="<?php echo $download_link; ?>" class="refreshcls" target="_blank"> <?php echo html_output($row['filename']); ?> </a>
                                                <?php 
                                            }
                                        
                                        }
                                        else {
                                            if (($row['expires'] == '0') || (time() < strtotime($row['expiry_date']))) {
                                        ?>
                                            <a href="<?php echo $download_link; ?>" class="refreshcls" target="_blank">
                                            <?php
	                                            $targetDir = UPLOADED_FILES_FOLDER;
												$zip = zip_open($targetDir.$row['filename']);
												if($row['request_type'] != '0' || $row['request_type'] != null){
													if ($zip) {
														while ($zip_entry = zip_read($zip)) {
															echo zip_entry_name($zip_entry) .",";
														}
														zip_close($zip);
													}
												}else{
                                            		echo html_output($row['filename']);
												}
                                            ?>

                                            </a>

                                        <?php 
                                            } else {
            //                                 	$targetDir = UPLOADED_FILES_FOLDER;
												// $zip = zip_open($targetDir.$row['filename']);
												// if ($zip) {
												// 	while ($zip_entry = zip_read($zip)) {
												// 		echo zip_entry_name($zip_entry) . ",";
												// 	}
												// 	zip_close($zip);
												// }
                                                echo html_output($row['filename']);
                                            }
                                        }
                                        ?>
                                    </td>

									  <td data-value="<?php echo $this_file_size; ?>"><?php echo $formatted_size; ?></td>
									  <td>
										<strong>
												<?php 
												if(isset($row['number_downloads']) && $row['number_downloads']>0)
												{
												?>
													<?php echo htmlentities($current_download_count).'/'. htmlentities($row['number_downloads']); ?></strong>
												<?php
												} else {
													echo "Not set";
												}
												?>
									</td>

                  <?php
										if(($current_level != '0') && ($edit_access==true)) {
									?>
                  <td>

                  		<a href="edit-file.php?file_id=<?php echo $row["file_id"]; ?>&page_id=2" class="btn-sm">
                    	<?php _e(html_output($row['uploader']),'cftp_admin'); ?>
                    	</a>

                  </td>
	<?php
	}else{
?>
                  <td>
                  	
                    	<?php _e(html_output($row['uploader']),'cftp_admin'); ?>

                  </td>

<?php

}
?>

                  
                  <?php
										if($current_level != 0) {
												if ( !isset( $search_on ) ) {
											?>
                  <!-- <td class="hideNow"><?php
															$class	= ($count_assignations == 0) ? 'danger' : 'success';
															$status	= ($count_assignations == 0) ? __('No','cftp_admin') : __('Yes','cftp_admin');
														?>
                    <span class="label label-<?php echo $class; ?>"> <?php echo $status; ?> </span></td> -->
                  <?php
												}
											?>
                  <!-- <td class="col_visibility hideNow"><?php
													if ($row['public_allow'] == '1') {
												?>
                    <a href="javascript:void(0);" class="btn btn-primary btn-sm public_link" data-id="<?php echo $row['id']; ?>" data-token="<?php echo html_output($row['public_token']); ?>" data-placement="top" data-toggle="popover" data-original-title="<?php _e('Public URL','cftp_admin'); ?>">
                    <?php
													}
													else {
												?>
                    <a href="javascript:void(0);" class="btn btn-default btn-sm disabled" rel="" title="">
                    <?php
													}
															$status_public	= __('Public','cftp_admin');
															$status_private	= __('Private','cftp_admin');
															echo ($row['public_allow'] == 1) ? $status_public : $status_private;
												?>
                    </a></td> -->

                  <?php
								} ?>
								<td><?php
												if ($row['expires'] == '0') {
											?>
									<a href="javascript:void(0);" class="btn btn-success disabled btn-sm">
									<?php _e('Does not expire','cftp_admin'); ?>
									</a>
									<?php
												}
												else {
													if (time() > strtotime($row['expiry_date'])) {
											?>
									<a href="javascript:void(0);" class="btn btn-danger disabled btn-sm" rel="" title="">
									<?php _e('Expired on','cftp_admin'); ?>
									<?php echo date(TIMEFORMAT_USE,strtotime($row['expiry_date'])); ?> </a>
									<?php
													}
													else {
											?>
									<a href="javascript:void(0);" class="btn btn-info disabled btn-sm" rel="" title="">
									<?php _e('Expires on','cftp_admin'); ?>
									<?php echo date(TIMEFORMAT_USE,strtotime($row['expiry_date'])); ?> </a>
									<?php
													}
												}
											?></td>
										<?php /**
										 * These columns are only available when filtering by client or group.
										 */
										if (isset($search_on)) {
									?>
                  <td class="<?php echo ($hidden == 1) ? 'file_status_hidden' : 'file_status_visible'; ?>"><?php
													$status_hidden	= __('Hidden','cftp_admin');
													$status_visible	= __('Visible','cftp_admin');
													$class			= ($hidden == 1) ? 'danger' : 'success';
												?>
                    <span class="label label-<?php echo $class; ?>"> <?php echo ($hidden == 1) ? $status_hidden : $status_visible; ?> </span></td>
                  <td><?php
													switch ($results_type) {
														case 'client':
															echo $download_count; ?>
                    <?php _e('times11111','cftp_admin');
															break;
				
														case 'group':
														case 'category':
												?>
                    <a href="javascript:void(0);" class="<?php if ($download_count > 0) { echo 'downloaders btn-primary'; } else { echo 'btn-default disabled'; } ?> btn btn-sm" rel="<?php echo $row["id"]; ?>" title="<?php echo html_output($row['filename']); ?>"> <?php echo $download_count; ?>
                    <?php _e('downloads','cftp_admin'); ?>
                    </a>
                    <?php
														break;
													}
												?></td>
                  <?php
										}
										else {
											if ($current_level != '0') {
												if ( isset( $downloads_information[$row["id"]] ) ) {
													$download_info	= $downloads_information[$row["id"]];
													$btn_class		= ( $download_info['total'] > 0 ) ? 'downloaders btn-primary' : 'btn-default disabled';
													$total_count	= $download_info['total'];
												}
												else {
													$btn_class		= 'btn-default disabled';
													$total_count	= 0;
												}
									?>
                  <td><a href="<?php echo BASE_URI; ?>download-information.php?id=<?php echo $row['id']; ?>" class="<?php echo $btn_class; ?> btn btn-sm" rel="<?php echo $row["id"]; ?>" title="<?php echo html_output($row['filename']); ?>"> <?php echo $total_count; ?>
                    <?php _e('downloads','cftp_admin'); ?>
                    </a></td>
                  <?php
											}
										}
									?>

									<td>
									<?php
									if ($row['request_type'] == '0' || $row['request_type'] == null)
														{ echo("<span class='requestType normal'> Normal </span>"); }
														else if ($row['request_type'] == '1')
														{ echo("<span class='requestType requested'> Requested </span>"); }
														else if ($row['request_type'] == '2')
														{ echo("<span class='requestType guest'>Guest </span>");}

														?>
														</td>
										<td>
									<?php if (($current_level != '0') && (($row['request_type'] == '1' || $row['request_type'] == '2'))) { ?>
                                        <a del-id="<?php echo $row["file_id"]; ?>" class="delBtn" id="delBtn" >
                                            <i class="fa fa-times" aria-hidden="true"></i>
                                        </a>
                                    <?php } else if ((empty($row['group_id'])) && (($current_level != '0') || ($current_level == '0') && ($row['request_type'] != '1'))) {
                                    ?>
                                        <a  un-id="<?php echo $row["file_id"]; ?>" id="unAssBtn" class="unAssnBtn">
                                            <i class="fa fa-sign-out" aria-hidden="true"></i>
                                        </a>
                                    <?php } else { echo(" -- ");  }?>
								</td>
								</tr>
                <?php
							    }
							}
						}
					?>
              </tbody>
            </table>
            </section>
            <nav aria-label="<?php _e('Results navigation','cftp_admin'); ?>">
              <div class="pagination_wrapper text-center">
                <ul class="pagination hide-if-no-paging">
                </ul>
              </div>
            </nav>
          </form>
          <?php
			if ($current_level != '0') {
				$msg = __('Please note that downloading a file from here will not add to the download count.','cftp_admin');
				echo system_message('info',$msg);
			}
		?>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<form  id="deleteForm" action="" method="POST" style="display:none;">
	<input id="delete_file" name="delete_file" type="text" value="">
</form>
<form id="unassignForm"  action="" method="POST" style="display:none;">
	<input id="unassign_file" name="unassign_file"  type="text" value="">
</form>
<div id="myModal" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">File Not Found!</h4>
      </div>
      <div class="modal-body">
        <p>this file is expired and removed from the system</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>

  </div>
</div>

<?php include('footer.php'); 
 if (isset($_GET['status'])) {
	 if($_GET['status'] == 1) { ?>
<script type="text/javascript">

$("document").ready(function(e) {
 $('#myModal').modal('show');   
});
</script>
<!-- Modal -->

	 <?php }
 } ?>
 <script>
 $(document).ready(function(e) {
var numfiles = document.querySelectorAll("#files_list tbody tr");
var totalcount = document.querySelectorAll(".form_count_total span");
totalcount[0].innerHTML = numfiles.length + " files";
	 
$(".refreshcls").on("click", function (e) {  
		setTimeout(function() {
    location.reload();
}, 1000);
	});
});
 </script>


<style type="text/css">

.disabled_cc {background-color: #e7e5e5!important;cursor: default;}
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
    td:nth-of-type(3):before { content: "Ext."; }
    td:nth-of-type(4):before { content: "Title"; }
    td:nth-of-type(5):before { content: "Size"; }
    td:nth-of-type(6):before { content: "Download/Limit"; }
    td:nth-of-type(7):before { content: "Uploader"; }
    td:nth-of-type(8):before { content: "Expiry"; }
    td:nth-of-type(9):before { content: "Total downloads"; }
    td:nth-of-type(10):before { content: "Type"; }
    td:nth-of-type(11):before { content: "Action"; }
}
/*-------------------- Responsive table End--------------------------*/
</style>
<script type="text/javascript">
 $(document).ready(function(e) {
	 $(".delBtn").click(function() {
				var msg_1 = 'You are about to delete a file from your Inbox permanently. Only your copy will be deleted. Are you sure you want to continue??';
					if (confirm(msg_1)) {
						$('#delete_file').val($(this).attr('del-id'));
					  $('#deleteForm').submit();
				} else {
					return false;
				}
		});
	 $(".unAssnBtn").click(function() {
				var msg_1 = 'You are about to unassign a file from your Inbox permanently. Only your copy will be deleted. Are you sure you want to continue?';
					if (confirm(msg_1)) {
						$('#unassign_file').val($(this).attr('un-id'));
						$('#unassignForm').submit();
				} else {
					return false;
				}
		});
		});
</script>
