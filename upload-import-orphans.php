<?php
/**
 * Shows a list of files found on the upload/ folder that
 * are not yet on the database, meaning they were uploaded
 * via FTP.
 * Only shows files that are allowed according to the sytem
 * settings.
 * Submits an array of file names.
 *
 * @package ProjectSend
 * @subpackage Upload
 */
$load_scripts	= array(
						'footable',
					); 

$allowed_levels = array(9);
require_once('sys.includes.php');

$active_nav = 'files';
$cc_active_page = 'Orphan Files';

$page_title = __('Find Orphan Files', 'cftp_admin');
include('header.php');

/**
 * Use the folder defined on sys.vars.php
 * Composed of the absolute path to that file plus the
 * default uploads folder.
 */
$work_folder = UPLOADED_FILES_FOLDER;
?>

<div id="main">

  <div id="content">
  
    <div class="container-fluid">
	
      <div class="row">
	  
        <div class="col-md-12">
		
          <h1 class="page-title txt-color-blueDark"><i class="fa fa-file" aria-hidden="true"></i>&nbsp;<?php echo $page_title; ?></h1>
		  
          <?php
		  
		if ( false === CAN_UPLOAD_ANY_FILE_TYPE ) {
			
			$msg = __('This list only shows the files that are allowed according to your security settings. If the file type you need to add is not listed here, add the extension to the "Allowed file extensions" box on the options page.', 'cftp_admin');
			
			echo system_message('warning',$msg);
			
		}
	?>
          <?php
		  
		/** Count clients to show an error message, or the form */
		
		$statement		= $dbh->query("SELECT id FROM " . TABLE_USERS . " WHERE level = '0'");
		
		$count_clients	= $statement->rowCount();
		
		$statement		= $dbh->query("SELECT id FROM " . TABLE_GROUPS);
		
		$count_groups	= $statement->rowCount();

		if ( ( !$count_clients or $count_clients < 1 ) && ( !$count_groups or $count_groups < 1 ) ) {
			
			message_no_clients();
			
		}

		/**
		 * Make a list of existing files on the database.
		 * When a file doesn't correspond to a record, it can
		 * be safely renamed.
		 */
		$sql = $dbh->query("SELECT url, id, public_allow FROM " . TABLE_FILES );
		
		$db_files = array();
		
		$sql->setFetchMode(PDO::FETCH_ASSOC);
		
		while ( $row = $sql->fetch() ) {
			
			$db_files[$row["url"]] = $row["id"];
			
			if ($row['public_allow'] == 1) {$db_files_public[$row["url"]] = $row["id"];}
		}

		/** Make an array of already assigned files */
		
		$sql = $dbh->query("SELECT DISTINCT file_id FROM " . TABLE_FILES_RELATIONS . " WHERE client_id IS NOT NULL OR group_id IS NOT NULL OR folder_id IS NOT NULL");
		
		$assigned = array();
		
		$sql->setFetchMode(PDO::FETCH_ASSOC);
		
		
		while ( $row = $sql->fetch() ) {
			
			$assigned[] = $row["file_id"];
		}
		
		/** We consider public file as assigned file */
		if (isset($db_files_public)) {
			foreach ($db_files_public as $file_id){

				$assigned[] = $file_id;

			}
		}

		/** Read the temp folder and list every allowed file */
		
		
		
		if ($handle = opendir($work_folder)) {
			
			while (false !== ($filename = readdir($handle))) {
				
				$filename_path = $work_folder.'/'.$filename;
				
				if(!is_dir($filename_path)) {
					
					if ($filename != "." && $filename != "..") {
						
						/** Check types of files that are not on the database */
						
						if (!array_key_exists($filename,$db_files)) {
							
							$file_object = new PSend_Upload_File();
							
							$new_filename = $file_object->safe_rename_on_disk($filename,$work_folder);
							
							/** Check if the filetype is allowed */
							
							if ($file_object->is_filetype_allowed($new_filename)) {
								
								/** Add it to the array of available files */
								
								$new_filename_path = $work_folder.'/'.$new_filename;
								
								//$files_to_add[$new_filename] = $new_filename_path;
								
								$files_to_add[] = array(
								
														'path'		=> $new_filename_path,
														
														'name'		=> $new_filename,
														
														'reason'	=> 'not_on_db',
														'last_modifed' =>filemtime($new_filename_path)
													);
							}
						}
					}
				}
			}
			closedir($handle);
		}
		
		if (!empty($_POST['search'])) {
			
			$search = htmlspecialchars($_POST['search']);

			$no_results_error= "search";
			function search_text($item) {
				
				global $search;
				
				if (stripos($item['name'], $search) !== false) {
					
					/**
					 * Items that match the search
					 */
					return true;
				}
				
				else {
					
					/**
					 * Remove other items
					 */
					 
					unset($item);
					
				}
				
				return false;
				
			}

			$files_to_add = array_filter($files_to_add, 'search_text');
		}


		// if(isset($_POST['search']) && ($no_results_error=="search")) {
		if(isset($_POST['search']) && (count($files_to_add) == 0)) {
			echo system_message('error',"Your search keywords returned no results");
		}



		/**
		 * Generate the list of files if there is at least 1
		 * available and allowed.
		 */
		if(isset($files_to_add)) {
			foreach ($files_to_add as $key => $node) {
		   
				$files_to[$key]    = $node['last_modifed'];
			}
			array_multisort($files_to, SORT_DESC, $files_to_add);
			
	?>
	<div class="form_actions_left">
          <div class="form_actions_limit_results">
		  
            <form action="" name="files_search" method="post" class="form-inline">
			
              <div class="form-group group_float">
			  
                <input type="text" name="search" id="search" value="<?php if(isset($_POST['search']) && !empty($_POST['search'])) { echo html_output($_POST['search']); } ?>" class="txtfield form_actions_search_box form-control" />
				
              </div>
			  
              <button type="submit" id="btn_proceed_search" class="btn btn-sm btn-default">
			  
              <?php _e('Search','cftp_admin'); ?>
			  
			  
              </button>
			  
            </form>
			
          </div>		  
        </div>
		  
		  
			<div class="form-inline">
			
			<div class="form_actions_limit_results">
			
					<div class="form-group group_float">
					
						<label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i>
						
                      <?php _e('Selected orphan files actions','cftp_admin'); ?>
					  
                      :</label>
					  
						<select name="files_actions" id="files_actions" class="txtfield form-control" style="width:200px !important;">
						
										<option value="delete"><?php _e('Delete','cftp_admin'); ?></option>
										
									</select>
									
								</div>
								
								<button type="submit" name="do_delete" id="do_delete" class="btn btn-sm btn-default"><?php _e('Proceed','cftp_admin'); ?></button>
								
			</div>
			
			</div>

          <div class="form_actions_count">
		  
            <p class="form_count_total">
			
              <?php _e('Showing','cftp_admin'); ?>
			  
              : <span><?php echo count($files_to_add); ?>
			  
              <?php _e('files','cftp_admin'); ?>
			  
              </span></p>
			  
          </div>
		  
          <div class="clear"></div>
		  
          <form action="upload-process-form.php" name="upload_by_ftp" id="upload_by_ftp" method="post" enctype="multipart/form-data">
		  
          	<section id="no-more-tables">
			
            <table id="add_files_from_ftp" class="table table-striped table-bordered table-hover dataTable no-footer" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
			
              <thead>
			  
                <tr>
				
                  <th class="td_checkbox" data-sort-ignore="true"> <input type="checkbox" name="select_all" id="select_all" value="0" />
				  
                  </th>
				  
                  <th data-sort-initial="true"><?php _e('File Name','cftp_admin'); ?></th>
				  
                  <th data-type="numeric" data-hide="phone"><?php _e('File Size','cftp_admin'); ?></th>
				  
                  <th data-type="numeric" data-hide="phone"><?php _e('Last Modified','cftp_admin'); ?></th>
				  
                  
                </tr>
				
              </thead>
			  
                <tbody>
              
              <?php
			  
						$curr_usr_id =	CURRENT_USER_ID;
							
							foreach ($files_to_add as $add_file){



								$x=explode("_", $add_file['name']);


								//	if($x[2]==$curr_usr_id){
									
			  ?>
        <tr>
              
            <td>
			<input type="checkbox" name="add[]" class="select_file_checkbox" value="<?php echo html_output($add_file['name']); ?>" />
			</td>

            <td>
			  <a href="#" name="file_edit" class="btn-edit-file">
              <?php _e(html_output($add_file['name']),'cftp_admin'); ?>
              </a>
			</td>


              <td data-value="<?php echo filesize($add_file['path']); ?>">
			  <?php echo html_output(format_file_size(get_real_size($add_file['path']))); ?>
			  </td>
			  
              <td data-value="<?php echo filemtime($add_file['path']); ?>">
			  <?php echo date(TIMEFORMAT_USE, $add_file['last_modifed']); ?>
			  </td>
               
              
        </tr>
              
						<?php
								//	}
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
			
            <?php
					$msg = __('Please note that the listed files will be renamed if they contain invalid characters.','cftp_admin');
					echo system_message('info',$msg);
				?>
            <div class="after_form_buttons" hidden>
              <button type="submit" name="submit" class="btn btn-wide btn-primary" id="upload-continue">
              <?php _e('Continue','cftp_admin'); ?>
              </button>
            </div>
          </form>
          <script type="text/javascript">
				$(document).ready(function() {
					$("#upload_by_ftp").submit(function() {
						var checks = $("td>input:checkbox").serializeArray(); 
						if (checks.length == 0) { 
							alert('<?php _e('Please select at least one file to proceed.','cftp_admin'); ?>');
							return false; 
						} 
					});
					
					/**
					 * Only select the current file when clicking an "delete" button
					 */
					$("#do_delete").click(function() {
						var checks = $("td>input:checkbox").serializeArray(); 
						if (checks.length == 0) { 
							alert('<?php _e('Please select at least one file to proceed.','cftp_admin'); ?>');
							return false; 
						}else
						{
							var msg_1 = '<?php _e("You are about to delete",'cftp_admin'); ?>';
							var msg_2 = '<?php _e("Orphan File. Are you sure you want to continue?",'cftp_admin'); ?>';
							if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
								var $reutrn_var =  true;
							} else {
								var $reutrn_var =  false;
							}
							if($reutrn_var) {
								/* move checked file names to an array */
								var values = new Array();
								$.each($("input[name='add[]']:checked"), function() {
									values.push($(this).val());
								});
								var jsonStringValues = JSON.stringify(values);
								var postData = {  "values": jsonStringValues };
								/*Call ajax to delete orphan files */
								$.ajax({
								  type: "POST",
								  url: "delete-import-orphans.php",
								  data: postData,
								  traditional: true,
								  success: function (data) {
												if(data='done'){
													alert('File has been removed successfully!!')
													location.reload(); 
												}
								  }
							  });
							}
						}  
					});

					
					/**
					 * Only select the current file when clicking an "edit" button
					 */
					$('.btn-edit-file').click(function(e) {
						$('#select_all').prop('checked', false);
						$('td .select_file_checkbox').prop('checked', false);
						$(this).parents('tr').find('td .select_file_checkbox').prop('checked', true);
						$('#upload-continue').click();
					});

				});
			</script>
          <?php
		}
		else {
			/** No files found */
		?>
          <div class="container">
            <div class="row">
              <div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3 white-box">
                <div class="white-box-interior">
                  <p>
                    <?php _e('There are no files available to add right now.', 'cftp_admin'); ?>
                  </p>
                  <p class="margin_0">
                    <?php
									_e('To use this feature you need to upload your files via FTP to the folder', 'cftp_admin');
									echo ' <span class="format_url"><strong>'.html_output($work_folder).'</strong></span>.';
								?>
                  </p>
                  <?php /*
							<p><?php _e('This is the same folder where the files uploaded by the web interface will be stored. So if you finish uploading your files but do not assign them to any clients/groups, the files will still be there for later use.', 'cftp_admin'); ?></p>
							*/ ?>
                </div>
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
<?php
	include('footer.php');
?>



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
    td:nth-of-type(2):before { content: "File Name"; }
    td:nth-of-type(3):before { content: "File Size"; }
    td:nth-of-type(4):before { content: "Last Modified"; }
}
/*-------------------- Responsive table End--------------------------*/
</style>
