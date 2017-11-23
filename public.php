<?php
/**
 * Shows the list of public groups and files
 *
 * @package		ProjectSend
 * @subpackage	Files
 *
 */
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

/**
 * If the option to show this page is not enabled, redirect
 */
if ( PUBLIC_LISTING_ENABLE != 1 ) {
	header("location:" . BASE_URI . "index.php");
	die();	
}

/**
 * Check the option to show the page to logged in users only
 */
if ( PUBLIC_LISTING_LOGGED_ONLY == 1 ) {
	check_for_session();
}

$page_title = __('Public groups and files','cftp_admin');

$dont_redirect_if_logged = 1;

include('header-unlogged.php');
?>
<div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">

	<div class="white-box">
		<div class="white-box-interior">
			<div class="text-center">
				<h3><?php echo $page_title; ?></h3>
			</div>
			
			<div class="treeview">
					<?php
						function list_file($data) {
							$output = '';
							if ( PUBLIC_LISTING_USE_DOWNLOAD_LINK == 1 && $data['expired'] != true ) {
								$download_link = BASE_URI . 'download.php?id=' . $data['id'] . '&token=' . $data['token'];
								$output = '<a href="' . $download_link . '">' . $data['filename'] . '</a>';
							}
							else {
								$output = $data['filename'];
							}
							
							return $output;
						}

						/**
						 * 1- Make a list of files IDs
						 */
						$all_files = array();
						$public_files = array();
						$expired_files = array();
						$remove_files = array(); // used to remove file ids from the complete list after showing the groups so the files don't appear again on the list.
						$files_sql = "SELECT * FROM " . TABLE_FILES;

						/** All files or just the public ones? */
						if ( PUBLIC_LISTING_SHOW_ALL_FILES != 1 ) {
							$files_sql .= " WHERE public_allow=1";
						}

						$sql = $dbh->prepare($files_sql);
		 				$sql->execute();
		 				$sql->setFetchMode(PDO::FETCH_ASSOC);
		 				while ( $row = $sql->fetch() ) {

							/** Does it expire? */
							$add_file = true;
							$expired	= false;

							if ($row['expires'] == '1') {
								if (time() > strtotime($row['expiry_date'])) {
									if (EXPIRED_FILES_HIDE == '1') {
										$add_file = false;
									}
									$expired = true;
								}
							}

							if ($add_file == true) {
								$filename_on_disk = (!empty( $row['original_url'] ) ) ? $row['original_url'] : $row['url'];

								$all_files[$row['id']] = array(
																	'id'				=> encode_html($row['id']),
																	'filename'		=> encode_html($filename_on_disk),
																	'title'			=> encode_html($row['filename']),
																	'public'			=> encode_html($row['public_allow']),
																	'token'			=> encode_html($row['public_token']),
																	'expired'		=> $expired,
																	'expire_date'	=> encode_html($row['expiry_date']),
																);
								if ( $row['public_allow'] == 1 ) {
									$public_files[] = $row['id'];
								}
							}
							else {
								$expired_files[] = $row['id'];
							}
						}

						/**
						 * 2- Get public groups
						 */
						$groups = array();
						$get_groups		= new GroupActions();
						$get_arguments	= array(
												 	'public'	=> true,
												);
						$found_groups	= $get_groups->get_groups($get_arguments); 
						foreach ($found_groups as $group_id => $group_data) {
							$groups[$group_id] = array(
														'name'	=> $group_data['name'],
														'files'	=> array(),
													);
							/**
							 * 3- Get list of files from this group
							 */
							$group_files = array();
							$files_groups_sql = "SELECT id, file_id, client_id, group_id FROM " . TABLE_FILES_RELATIONS . " WHERE group_id=:group_id AND hidden = '0'";
							// Don't include private files
							if ( PUBLIC_LISTING_SHOW_ALL_FILES != 1 ) {
								$files_groups_sql .= " AND FIND_IN_SET(file_id, :public_files)";
							}
							
							// Don't include expired files
							if (EXPIRED_FILES_HIDE == '1') {
								$files_groups_sql .= " AND !FIND_IN_SET(file_id, :excluded_files)";
							}

							$sql = $dbh->prepare($files_groups_sql);
							$sql->bindParam(':group_id', $group_id, PDO::PARAM_INT);
							
							if ( PUBLIC_LISTING_SHOW_ALL_FILES != 1 ) {
								$included_files = implode( ',', array_map( 'intval', array_unique( $public_files ) ) );
								$sql->bindParam(':public_files', $included_files);
							}
							if (EXPIRED_FILES_HIDE == '1') {
								$excluded_files = implode( ',', array_map( 'intval', array_unique( $expired_files ) ) );
								$sql->bindParam(':excluded_files', $excluded_files);
							}
							
			 				$sql->execute();
			 				$sql->setFetchMode(PDO::FETCH_ASSOC);
							while ( $row = $sql->fetch() ) {
								/** TODO:
									* - no incluir archivos expirados
									* */
								$groups[$group_id]['files'][$row['file_id']] = $all_files[$row['file_id']];
								$remove_files[] = $row['file_id'];
							}
						}
						
						/**
						 * Removes from the array of files those that are on, at least, one group
						 * so in the list of groupless files they are not repeated.
						 */
						foreach ( $remove_files as $file_id ) {
							unset($all_files[$file_id]);
						}
						
						//print_r($groups);
						//print_r($all_files);
						
						/**
						 * Finally, generate the list
						 * 1- Groups
						 */
					?>
						<div class="listing">
							<ul>
								<?php
									foreach ( $groups as $group ) {
								?>
										<li>
											<?php echo $group['name']; ?>
											<ul>
												<?php
														foreach ( $group['files'] as $id => $file_info ) {
												?>
															<li><?php echo list_file($file_info) ?></li>
												<?php
														}
												?>
											</ul>
										</li>
								<?php
									}

									/**
									 * 2- Groupless files
									 */
									foreach ( $all_files as $id => $file_info) {
								?>
										<li><?php echo list_file($file_info) ?></li>
								<?php
									}
								?>
							</ul>
						</div>
			</div>
		</div>
	</div>

	<div class="login_form_links">
		<?php
			if ( !check_for_session(false) && CLIENTS_CAN_REGISTER == '1') {
		?>
				<p id="register_link"><?php _e("Don't have an account yet?",'cftp_admin'); ?> <a href="<?php echo BASE_URI; ?>register.php"><?php _e('Register as a new client.','cftp_admin'); ?></a></p>
		<?php
			}
		?>
		<p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.','cftp_admin'); ?></a></p>
	</div>
</div>

<?php
	include('footer.php');
