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

$allowed_levels = array(9,8,7);
require_once('sys.includes.php');

$active_nav = 'files';

$page_title = __('Find orphan files', 'cftp_admin');
include('header.php');

/**
 * Use the folder defined on sys.vars.php
 * Composed of the absolute path to that file plus the
 * default uploads folder.
 */
$work_folder = UPLOADED_FILES_FOLDER;
?>

<div id="main">
	<h2><?php echo $page_title; ?></h2>

	<?php
		if ( false === CAN_UPLOAD_ANY_FILE_TYPE ) {
			$msg = __('This list only shows the files that are allowed according to your security settings. If the file type you need to add is not listed here, add the extension to the "Allowed file extensions" box on the options page.', 'cftp_admin');
			echo system_message('warning',$msg);
		}
	?>
	
	<?php
		/** Count clients to show an error message, or the form */
		$sql = $dbh->query("SELECT * FROM " . TABLE_USERS . " WHERE level = '0'");
		$count = $sql->rowCount();
		if (!$count) {
			/** Echo the "no clients" default message */
			message_no_clients();
		}
		else {
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
			foreach ($db_files_public as $file_id){
				$assigned[] = $file_id;
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
								$new_filename = $file_object->safe_rename_on_disc($filename,$work_folder);
								/** Check if the filetype is allowed */
								if ($file_object->is_filetype_allowed($new_filename)) {
									/** Add it to the array of available files */
									$new_filename_path = $work_folder.'/'.$new_filename;
									//$files_to_add[$new_filename] = $new_filename_path;
									$files_to_add[] = array(
															'path'		=> $new_filename_path,
															'name'		=> $new_filename,
															'reason'	=> 'not_on_db',
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
			
//			var_dump($result);
			
			/**
			 * Generate the list of files if there is at least 1
			 * available and allowed.
			 */
			if(isset($files_to_add) && count($files_to_add) > 0) {
		?>

				<div class="form_actions_limit_results">
					<form action="" name="files_search" method="post" class="form-inline">
						<div class="form-group group_float">
							<input type="text" name="search" id="search" value="<?php if(isset($_POST['search']) && !empty($_POST['search'])) { echo html_output($_POST['search']); } ?>" class="txtfield form_actions_search_box form-control" />
						</div>
						<button type="submit" id="btn_proceed_search" class="btn btn-sm btn-default"><?php _e('Search','cftp_admin'); ?></button>
					</form>
				</div>

				<div class="clear"></div>
		
				<div class="form_actions_count">
					<p class="form_count_total"><?php _e('Showing','cftp_admin'); ?>: <span><?php echo count($files_to_add); ?> <?php _e('files','cftp_admin'); ?></span></p>
				</div>
		
				<div class="clear"></div>

				<form action="upload-process-form.php" name="upload_by_ftp" id="upload_by_ftp" method="post" enctype="multipart/form-data">
					<table id="add_files_from_ftp" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
						<thead>
							<tr>
								<th class="td_checkbox" data-sort-ignore="true">
									<input type="checkbox" name="select_all" id="select_all" value="0" />
								</th>
								<th data-sort-initial="true"><?php _e('File name','cftp_admin'); ?></th>
								<th data-type="numeric" data-hide="phone"><?php _e('File size','cftp_admin'); ?></th>
								<th data-type="numeric" data-hide="phone"><?php _e('Last modified','cftp_admin'); ?></th>
								<th><?php _e('Actions','cftp_admin'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
								foreach ($files_to_add as $add_file) {
									?>
										<tr>
											<td><input type="checkbox" name="add[]" class="select_file_checkbox" value="<?php echo html_output($add_file['name']); ?>" /></td>
											<td><?php echo html_output($add_file['name']); ?></td>
											<td data-value="<?php echo filesize($add_file['path']); ?>"><?php echo html_output(format_file_size(filesize($add_file['path']))); ?></td>
											<td data-value="<?php echo filemtime($add_file['path']); ?>">
												<?php echo date(TIMEFORMAT_USE, filemtime($add_file['path'])); ?>
											</td>
											<td>
												<button type="button" name="file_edit" class="btn btn-primary btn-sm btn-edit-file">
													<?php _e('Edit','cftp_admin'); ?>
												</a>
											</td>
										</tr>
									<?php
								}
							?>
						</tbody>
					</table>

					<nav aria-label="<?php _e('Results navigation','cftp_admin'); ?>">
						<div class="pagination_wrapper text-center">
							<ul class="pagination hide-if-no-paging"></ul>
						</div>
					</nav>

					<?php
						$msg = __('Please note that the listed files will be renamed if they contain invalid characters.','cftp_admin');
						echo system_message('info',$msg);
					?>
	
					<div class="after_form_buttons">
						<button type="submit" name="submit" class="btn btn-wide btn-primary" id="upload-continue"><?php _e('Continue','cftp_admin'); ?></button>
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
								<p><?php _e('There are no files available to add right now.', 'cftp_admin'); ?></p>
								<p class="margin_0">
									<?php
										_e('To use this feature you need to upload your files via FTP to the folder', 'cftp_admin');
										echo ' <strong>'.html_output($work_folder).'</strong>.';
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
		} /** End if for users count */
	?>

</div>

<?php
	include('footer.php');
?>