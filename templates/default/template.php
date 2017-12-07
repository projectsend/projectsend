
<?php
/*
Template name:
Default
*/


$ld = 'cftp_template'; // specify the language domain for this template

if ( !empty( $_POST['category'] ) ) {
	$category_filter = $_POST['category'];
}

include_once(ROOT_DIR.'/templates/common.php'); // include the required functions for every template

$window_title = __('File downloads','cftp_template');

$load_scripts	= array(
						'footable',
					); 
include_once(ROOT_DIR.'/header.php');


$count = count($my_files);
?>
<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">

<link rel="stylesheet" media="all" type="text/css" href="https://msend.microhealthllc.com/css/base.css" />
<!----------------------------------------------------->
<div id="main" role="main">
			<!-- MAIN CONTENT -->
			<div id="content">

				<!-- Added by B) -------------------->
            <div class="container-fluid">
            	<div class="row">
                	<div class="col-md-12">
						<h1 class="page-title txt-color-blueDark"><i class="fa-fw fa fa-home"></i><?php echo $page_title; ?></h1>
<!------------------------------------------------------->
	<div id="wrapper">

	
		<div id="right_column">
	
			<div class="form_actions_left">
				<div class="form_actions_limit_results">
					<form action="" name="files_search" method="post" class="form-inline">
						<div class="form-group group_float">
							<input type="text" name="search" id="search" value="<?php if(isset($_POST['search']) && !empty($_POST['search'])) { echo html_output($_POST['search']); } ?>" class="txtfield form_actions_search_box form-control" />
						</div>
						<button type="submit" id="btn_proceed_search" class="btn btn-sm btn-default"><?php _e('Search','cftp_admin'); ?></button>
					</form>

					<?php
						if ( !empty( $cat_ids ) ) {
					?>
							<form action="" name="files_filters" method="post" class="form-inline form_filters">
								<div class="form-group group_float">
									<select name="category" id="category" class="txtfield form-control">
										<option value="0"><?php _e('All categories','cftp_admin'); ?></option>
										<?php
											$selected_parent = ( isset($category_filter) ) ? array( $category_filter ) : array();
											echo generate_categories_options( $get_categories['arranged'], 0, $selected_parent, 'include', $cat_ids );
										?>
									</select>
								</div>
								<button type="submit" id="btn_proceed_filter_files" class="btn btn-sm btn-default"><?php _e('Filter','cftp_admin'); ?></button>
							</form>
					<?php
						}
					?>
				</div>
			</div>
		
			<form action="" name="files_list" method="post" class="form-inline">
				<div class="form_actions_right">
					<div class="form_actions">
						<div class="form_actions_submit">
							<div class="form-group group_float">
								<label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected files actions','cftp_admin'); ?>:</label>
								<select name="files_actions" id="files_actions" class="txtfield form-control">
									<option value="zip"><?php _e('Download zipped','cftp_admin'); ?></option>
								</select>
							</div>
							<button type="submit" id="do_action" name="proceed" class="btn btn-sm btn-default"><?php _e('Proceed','cftp_admin'); ?></button>
						</div>
					</div>
				</div>
		
				<div class="right_clear"></div><br />

				<div class="form_actions_count">
					<p class="form_count_total"><?php _e('Showing','cftp_admin'); ?>: <span><?php echo $count; ?> <?php _e('files','cftp_admin'); ?></span></p>
				</div>
	
				<div class="right_clear"></div>
	
				<?php
					if (!$count) {
						if (isset($no_results_error)) {
							switch ($no_results_error) {
								case 'search':
									$no_results_message = __('Your search keywords returned no results.','cftp_admin');;
									break;
							}
						}
						else {
							$no_results_message = __('There are no files available.','cftp_template');;
						}
						echo system_message('error',$no_results_message);
					}
				?>
		
				<table id="files_list" class="table table-striped table-bordered table-hover dataTable no-footer" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
					<thead>
						<tr>
							<th class="td_checkbox" data-sort-ignore="true">
								<input type="checkbox" name="select_all" id="select_all" value="0" />
							</th>
							<th><?php _e('Title','cftp_template'); ?></th>
							<th data-hide="phone"><?php _e('Ext.','cftp_admin'); ?></th>
							<th data-hide="phone" class="description"><?php _e('Description','cftp_template'); ?></th>
							<th data-hide="phone"><?php _e('Size','cftp_template'); ?></th>
							<th data-type="numeric" data-sort-initial="descending"><?php _e('Date','cftp_template'); ?></th>
							<th data-hide="phone" data-sort-ignore="true"><?php _e('Expiration date','cftp_template'); ?></th>
							<th data-hide="phone,tablet" data-sort-ignore="true"><?php _e('Image preview','cftp_template'); ?></th>
							<th data-hide="phone" data-sort-ignore="true"><?php _e('Download','cftp_template'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
							if ($count > 0) {
								foreach ($my_files as $file) {
									$download_link = make_download_link($file);
									$date = date(TIMEFORMAT_USE,strtotime($file['timestamp']));
						?>
									<tr>
										<td>
											<?php
												if ($file['expired'] == false) {
											?>
													<input type="checkbox" name="files[]" value="<?php echo $file["id"]; ?>" />
											<?php
												}
											?>
										</td>
										<td class="file_name">
											<?php
												if ($file['expired'] == true) {
											?>
													<strong><?php echo htmlentities($file['name']); ?></strong>
											<?php
												}
												else {
											?>
													<a href="<?php echo $download_link; ?>" target="_blank">
														<strong><?php echo htmlentities($file['name']); ?></strong>
													</a>
											<?php
												}
											?>
										</td>
										<td class="extra">
											<span class="label label-success label_big">
												<?php		
													$pathinfo = pathinfo($file['url']);	
													$extension = strtolower($pathinfo['extension']);					
													echo $extension;
												?>
											</span>
										</td>
										<td class="description"><?php echo htmlentities($file['description']); ?></td>
										<td>
											<?php
												$file_absolute_path = UPLOADED_FILES_FOLDER . $file['url'];
												if ( file_exists( $file_absolute_path ) ) {
													$this_file_size = get_real_size(UPLOADED_FILES_FOLDER.$file['url']);
													echo format_file_size($this_file_size);
												}
												else {
													echo '-';
												}
											?>
										</td>
										<td data-value="<?php echo strtotime($file['timestamp']); ?>">
											<?php echo $date; ?>
										</td>
										<td>
											<?php
												if ( $file['expires'] == '1' ) {
													if ( $file['expired'] == false ) {
														$class = 'primary';
													} else {
														$class = 'danger';
													}
													
													$value = date( TIMEFORMAT_USE, strtotime( $file['expiry_date'] ) );
												} else {
													$class = 'success';
													$value = __('Never','cftp_template');
												}
											?>
											<span class="label label-<?php echo $class; ?> label_big">
												<?php echo $value; ?>
											</span>
										</td>
										<?php
											if ($file['expired'] == true) {
										?>
											<td class="extra"></td>
											<td class="text-center">
												<a href="javascript:void(0);" class="btn btn-danger disabled btn-sm">
													<?php _e('File expired','cftp_template'); ?>
												</a>
											</td>
										<?php
											}
											else {
										?>
												<td class="extra">
													<?php
														if (
															$extension == "gif" ||
															$extension == "jpg" ||
															$extension == "pjpeg" ||
															$extension == "jpeg" ||
															$extension == "png"
														) {
															if ( file_exists( $file_absolute_path ) ) {
																$this_thumbnail_url = UPLOADED_FILES_URL.$file['url'];
																if (THUMBS_USE_ABSOLUTE == '1') {
																	$this_thumbnail_url = BASE_URI.$this_thumbnail_url;
																}
													?>
																<img src="<?php echo TIMTHUMB_URL; ?>?src=<?php echo $this_thumbnail_url; ?>&amp;w=<?php echo THUMBS_MAX_WIDTH; ?>&amp;q=<?php echo THUMBS_QUALITY; ?>" class="thumbnail" alt="<?php echo htmlentities($this_file['name']); ?>" />
													<?php
															}
														}
													?>
												</td>
												<td>
													<a href="<?php echo $download_link; ?>" target="_blank" class="btn btn-primary btn-sm btn-wide">
														<?php _e('Download','cftp_template'); ?>
													</a>
												</td>
										<?php
											}
										?>
									</tr>
						<?php
								}
							}
						?>
					</tbody>
				</table>

				<nav aria-label="<?php _e('Results navigation','cftp_admin'); ?>">
					<div class="pagination_wrapper text-center">
						<ul class="pagination hide-if-no-paging"></ul>
					</div>
				</nav>
			</form>
		
		</div> <!-- right_column -->
	
	
	</div> <!-- wrapper -->
    <!----------------------------------------------------------->
    </div></div></div></div></div>
    <!------------------------------------------------------------>
	
	<?php //default_footer_info(); ?>
    <div class="page-footer">
			<div class="row">
				<div class="col-xs-12 col-sm-12 txt-color-white">
    <?php
include('../../footer.php');
?>
</div>
</div>
</div>


	<script type="text/javascript">
		$(document).ready(function() {
			$("#do_action").click(function() {
				var checks = $("td>input:checkbox").serializeArray(); 
				if (checks.length == 0) { 
					alert('<?php _e('Please select at least one file to proceed.','cftp_admin'); ?>');
					return false; 
				} 
				else {
					var action = $('#files_actions').val();
					if (action == 'zip') {

						var checkboxes = $.map($('input:checkbox:checked'), function(e,i) {
							if (e.value != '0') {
								return +e.value;
							}
						});
						
						$(document).psendmodal();
						$('.modal_content').html('<p class="loading-img"><img src="<?php echo BASE_URI; ?>img/ajax-loader.gif" alt="Loading" /></p>'+
													'<p class="lead text-center text-info"><?php _e('Please wait while your download is prepared.','cftp_admin'); ?></p>'+
													'<p class="text-center text-info"><?php _e('This operation could take a few minutes, depending on the size of the files.','cftp_admin'); ?></p>'
												);
						$.get('<?php echo BASE_URI; ?>process.php', { do:"zip_download", client:"<?php echo CURRENT_USER_USERNAME; ?>", files:checkboxes },
							function(data) {
								var url = '<?php echo BASE_URI; ?>process-zip-download.php?file=' + data;
								$('.modal_content').append("<iframe id='modal_zip'></iframe>");
								$('#modal_zip').attr('src', url);
								// Close the modal window
								//remove_modal();
							}
						);
					}
				return false;
				}
			});

		});
	</script>

	<?php
		load_js_files();
	?>
</body>
</html>
<!-- Added B) -->
<script type="text/javascript">
$(document).ready(function(e) {	
	$("#show-shortcut").click(function(e) {
        $("#shortcut").toggleClass('cc-visible');
    });
	$(".close-shortcut").click(function(e) {
		$("#shortcut").toggleClass('cc-visible');
    });
	$(".cc-dropdown").click(function(e) {
			$(this).find('ul').toggleClass('cc-visible');
    });
	$(".cc-active-subpage").parent().addClass('cc-visible');
});
</script>
<!-- Ended By B) -->
