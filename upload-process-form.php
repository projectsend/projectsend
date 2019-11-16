<?php
/**
 * Uploading files, step 2
 *
 * This file handles all the uploaded files, whether you are
 * coming from the "Upload from computer" or "Find orphan files"
 * pages. The only difference is from which POST array it takes
 * the information to list the available files to process.
 *
 * It can display up tp 3 tables:
 * One that will list all the files that were brought in from
 * the first step. One with the confirmed uploaded and assigned
 * files, and a possible third one with the ones that failed.
 *
 * @package ProjectSend
 * @subpackage Upload
 */
define('IS_FILE_EDITOR', true);

$allowed_levels = array(9,8,7,0);
require_once 'bootstrap.php';

$active_nav = 'files';

$page_title = __('Upload files', 'cftp_admin');

$page_id = 'new_uploads_editor';

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

define('CAN_INCLUDE_FILES', true);
?>

<div class="col-xs-12">

<?php
/** Coming from the web uploader */
if (isset($_POST['file_ids'])) {
	$uploaded_files = array_filter($_POST['file_ids']);
}

/**
 * A hidden field sends the list of failed files as a string,
 * where each filename is separated by a comma.
 * Here we change it into an array so we can list the files
 * on a separate table.
 */
if (isset($_POST['upload_failed'])) {
	$upload_failed_hidden_post = array_filter(explode(',',$_POST['upload_failed']));
}
/**
 * Files that failed are removed from the uploaded files list.
 */
if (isset($upload_failed_hidden_post) && count($upload_failed_hidden_post) > 0) {
	foreach ($upload_failed_hidden_post as $failed) {
		$delete_key = array_search($failed, $uploaded_files);
		unset($uploaded_files[$delete_key]);
	}
}

/** Define the arrays */
$upload_failed = array();
$move_failed = array();

/**
 * $empty_fields counts the amount of "name" fields that
 * were not completed.
 */
$empty_fields = 0;

/** Fill the users array that will be used on the notifications process */
$users = array();
$statement = $dbh->prepare("SELECT id, name, level FROM " . TABLE_USERS . " ORDER BY name ASC");
$statement->execute();
$statement->setFetchMode(PDO::FETCH_ASSOC);
while( $row = $statement->fetch() ) {
	$users[$row["id"]] = $row["name"];
	if ($row["level"] == '0') {
		$clients[$row["id"]] = $row["name"];
	}
}

/** Fill the groups array that will be used on the form */
$groups = array();
$statement = $dbh->prepare("SELECT id, name FROM " . TABLE_GROUPS . " ORDER BY name ASC");
$statement->execute();
$statement->setFetchMode(PDO::FETCH_ASSOC);
while( $row = $statement->fetch() ) {
	$groups[$row["id"]] = $row["name"];
}

/** Fill the categories array that will be used on the form */
$categories = array();
$get_categories = get_categories();

/**
 * Make an array of file urls that are on the DB already.
 */
$statement = $dbh->prepare("SELECT DISTINCT url FROM " . TABLE_FILES);
$statement->execute();
$statement->setFetchMode(PDO::FETCH_ASSOC);
while( $row = $statement->fetch() ) {
	$urls_db_files[] = $row["url"];
}

/**
 * A posted form will include information of the uploaded files
 * (name, description and client).
 * @todo
 */
	if (isset($_POST['submit'])) {

        $n = 0;

        /** Edit each file and its assignations */
		foreach ($_POST['file'] as $file) {
			$n++;

			if (!empty($file['name'])) {
			}
			else {
				$empty_fields++;
			}
		}
	}

	/**
	 * Generate the table of files that were assigned to a client
	 * on this last POST. These files appear on this table only once,
	 * so if there is another submition of the form, only the new
	 * assigned files will be displayed.
	 */
	if (!empty($upload_finish)) {
?>
		<h3><?php _e('Files uploaded correctly','cftp_admin'); ?></h3>
		<table id="uploaded_files_tbl" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
			<thead>
				<tr>
					<th data-sort-initial="true"><?php _e('Title','cftp_admin'); ?></th>
					<th data-hide="phone"><?php _e('Description','cftp_admin'); ?></th>
					<th data-hide="phone"><?php _e('File Name','cftp_admin'); ?></th>
					<?php
						if (CURRENT_USER_LEVEL != 0) {
					?>
							<th data-hide="phone"><?php _e("Status",'cftp_admin'); ?></th>
							<th data-hide="phone"><?php _e('Assignations','cftp_admin'); ?></th>
							<th data-hide="phone"><?php _e('Public','cftp_admin'); ?></th>
					<?php
						}
					?>
					<th data-hide="phone" data-sort-ignore="true"><?php _e("Actions",'cftp_admin'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
				foreach($upload_finish as $uploaded) {
			?>
					<tr>
						<td><?php echo html_output($uploaded['name']); ?></td>
						<td><?php echo htmlentities_allowed($uploaded['description']); ?></td>
						<td><?php echo html_output($uploaded['file']); ?></td>
						<?php
							if (CURRENT_USER_LEVEL != 0) {
						?>
								<td class="<?php echo (!empty($uploaded['hidden'])) ? 'file_status_hidden' : 'file_status_visible'; ?>">

									<?php
										$status_hidden	= __('Hidden','cftp_admin');
										$status_visible	= __('Visible','cftp_admin');
										$class			= (!empty($uploaded['hidden'])) ? 'danger' : 'success';
									?>
									<span class="label label-<?php echo $class; ?>">
										<?php echo ( !empty( $hidden ) && $hidden == 1) ? $status_hidden : $status_visible; ?>
									</span>
								</td>
								<td>
									<?php $class = ($uploaded['assignations'] > 0) ? 'success' : 'danger'; ?>
									<span class="label label-<?php echo $class; ?>">
										<?php echo $uploaded['assignations']; ?>
									</span>
								</td>
								<td class="col_visibility">
									<?php
										if ($uploaded['public'] == '1') {
									?>
											<a href="javascript:void(0);" class="btn btn-primary btn-sm public_link" data-type="file" data-id="<?php echo $uploaded['file_id']; ?>" data-token="<?php echo html_output($uploaded['public_token']); ?>">
									<?php
										}
										else {
									?>
											<a href="javascript:void(0);" class="btn btn-default btn-sm disabled" rel="" title="">
									<?php
										}
												$status_public	= __('Public','cftp_admin');
												$status_private	= __('Private','cftp_admin');
												echo ($uploaded['public'] == 1) ? $status_public : $status_private;
									?>
											</a>
								</td>
						<?php
							}
						?>
						<td>
							<a href="edit-file.php?file_id=<?php echo html_output($uploaded['new_file_id']); ?>" class="btn-primary btn btn-sm">
								<i class="fa fa-pencil"></i><span class="button_label"><?php _e('Edit file','cftp_admin'); ?></span>
							</a>
							<?php
								/*
								 * Show the "My files" button only to clients
								 */
								if (CURRENT_USER_LEVEL == 0) {
							?>
									<a href="<?php echo CLIENT_VIEW_FILE_LIST_URL; ?>" class="btn-primary btn btn-sm"><?php _e('View my files','cftp_admin'); ?></a>
							<?php
								}
							?>
						</td>
					</tr>
			<?php
				}
			?>
			</tbody>
		</table>
<?php
	}

	/**
	 * Generate the table of files ready to be assigned to a client.
	 */
	if (!empty($uploaded_files)) {
?>
		<h3><?php _e('Successfully uploaded files','cftp_admin'); ?></h3>

		<?php
			if (CURRENT_USER_LEVEL != 0) {
		?>
			<div class="message message_info"><strong><?php _e('Note','cftp_admin'); ?></strong>: <?php _e('You can skip assigning if you want. The files are retained and you may add them to clients or groups later.','cftp_admin'); ?></div>
		<?php
			}

		/**
		 * First, do a server side validation for files that were submited
		 * via the form, but the name field was left empty.
		 */
		if(!empty($empty_fields)) {
			$msg = __('Title is a required field for all uploaded files.', 'cftp_admin');
			echo system_message('danger', $msg);
		}

        /**
         * Include the form.
         */
        include_once FORMS_DIR . DS . 'file_editor.php';
    }
	/**
	 * There are no more files to assign.
	 * Send the notifications
	 */
	else {
        require_once INCLUDES_DIR . DS . 'upload-send-notifications.php';
	}

	/**
	 * Generate the table for the failed files.
	 */
	if (count($upload_failed) > 0) {
?>
		<h3><?php _e('Files not uploaded','cftp_admin'); ?></h3>
		<table id="failed_files_tbl" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
			<thead>
				<tr>
					<th data-sort-initial="true"><?php _e('File Name','cftp_admin'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
				foreach($upload_failed as $failed) {
			?>
					<tr>
						<td><?php echo $failed; ?></td>
					</tr>
			<?php
				}
			?>
			</tbody>
		</table>
<?php
	}
?>
</div>

<?php
	include_once ADMIN_VIEWS_DIR . DS . 'footer.php';