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

$page_title = __('Edit files', 'cftp_admin');

$page_id = 'file_editor';

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

define('CAN_INCLUDE_FILES', true);

// Editable
$editable = [];
$files = explode(',', $_GET['ids']);
foreach ($files as $file_id) {
    if (is_numeric($file_id)) {
        if (userCanEditFile(CURRENT_USER_ID, $file_id))
        {
            $editable[] = (int)$file_id;
        }
    }
}

$saved_files = [];

/** Fill the categories array that will be used on the form */
$categories = [];
$get_categories = get_categories();
?>
<div class="col-xs-12">
<?php
    /**
     * A posted form will include information of the uploaded files
     * (name, description and client).
     */
	if (isset($_POST['save'])) {
        // Edit each file and its assignations
		foreach ($_POST['file'] as $file) {
            $object = new \ProjectSend\Classes\Files;
            $object->get($file['id']);
            if ($object->recordExists()) {
                if ($object->save($file) != false) {
                    $saved_files[] = $file['id'];
                }
            }
        }

        // Redirect
        $saved = implode(',', $saved_files);
        header("Location: files-edit.php?ids=".$saved.'&saved=true');
        exit;
	}

    // Saved files
    $saved_files = [];
	if (!empty($_GET['saved'])) {
        // Send the notifications
        require_once INCLUDES_DIR . DS . 'upload-send-notifications.php';

        foreach ($editable as $file_id) {
            if (is_numeric($file_id)) {
                $saved_files[] = $file_id;
            }
        }

        echo system_message('success', __('Files saved correctly','cftp_admin'));
?>
		<table id="uploaded_files_tbl" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
			<thead>
				<tr>
					<th data-sort-initial="true"><?php _e('Title','cftp_admin'); ?></th>
					<th data-hide="phone"><?php _e('Description','cftp_admin'); ?></th>
					<th data-hide="phone"><?php _e('File Name','cftp_admin'); ?></th>
					<?php
						if (CURRENT_USER_LEVEL != 0) {
					?>
							<th data-hide="phone"><?php _e('Public','cftp_admin'); ?></th>
					<?php
						}
					?>
					<th data-hide="phone" data-sort-ignore="true"><?php _e("Actions",'cftp_admin'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
				foreach($saved_files as $file_id) {
                    $file = new \ProjectSend\Classes\Files();
                    $file->get($file_id);
                    if ($file->recordExists()) {
            ?>
                        <tr>
                            <td><?php echo html_output($file->title); ?></td>
                            <td><?php echo htmlentities_allowed($file->description); ?></td>
                            <td><?php echo html_output($file->filename_original); ?></td>
                            <?php
                                if (CURRENT_USER_LEVEL != 0) {
                            ?>
                                    <td class="col_visibility">
                                        <?php
                                            if ($file->public == '1') {
                                        ?>
                                                <a href="javascript:void(0);" class="btn btn-primary btn-sm public_link" data-type="file" data-public-url="<?php echo $file->public_url; ?>">
                                        <?php
                                            }
                                            else {
                                        ?>
                                                <a href="javascript:void(0);" class="btn btn-default btn-sm disabled" rel="" title="">
                                        <?php
                                            }
                                                    $status_public	= __('Public','cftp_admin');
                                                    $status_private	= __('Private','cftp_admin');
                                                    echo ($file->public == 1) ? $status_public : $status_private;
                                        ?>
                                                </a>
                                    </td>
                            <?php
                                }
                            ?>
                            <td>
                                <a href="files-edit.php?ids=<?php echo html_output($file->id); ?>" class="btn-primary btn btn-sm">
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
                    }
                ?>
			</tbody>
		</table>
<?php
	} else {
        /**
         * Generate the table of files ready to be edited
         */
        if (!empty($editable)) {
            if (CURRENT_USER_LEVEL != 0) {
                $msg = __('You can skip assigning if you want. The files are retained and you may add them to clients or groups later.','cftp_admin');
                echo system_message('info',$msg);
            }

            include_once FORMS_DIR . DS . 'file_editor.php';
        }
    }
?>
</div>

<?php
	include_once ADMIN_VIEWS_DIR . DS . 'footer.php';