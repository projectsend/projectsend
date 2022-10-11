<?php
/**
 * Contains the form that is used when adding or editing groups.
 */

switch ($groups_form_type) {
	case 'new_group':
		$submit_value = __('Create group','cftp_admin');
		$form_action = 'groups-add.php';
		break;
	case 'edit_group':
		$submit_value = __('Save group','cftp_admin');
		$form_action = 'groups-edit.php?id='.$group_id;
		break;
}
?>

<form action="<?php echo html_output($form_action); ?>" name="group_form" id="group_form" method="post" class="form-horizontal">
    <?php \ProjectSend\Classes\Csrf::addCsrf(); ?>

	<div class="form-group row">
		<label for="name" class="col-sm-4 control-label"><?php _e('Group name','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="name" id="name" class="form-control required" value="<?php echo (isset($group_arguments['name'])) ? html_output(stripslashes($group_arguments['name'])) : ''; ?>" required />
		</div>
	</div>

	<div class="form-group row">
		<label for="description" class="col-sm-4 control-label"><?php _e('Description','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<textarea name="description" id="description" class="ckeditor form-control"><?php echo (isset($group_arguments['description'])) ? html_output($group_arguments['description']) : ''; ?></textarea>
		</div>
	</div>

	<div class="form-group row assigns">
		<label for="members" class="col-sm-4 control-label"><?php _e('Members','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<select class="select2 none" multiple="multiple" id="members" name="members[]" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
				<?php
					$sql = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE level = '0' ORDER BY name ASC");
					$sql->execute();
					$sql->setFetchMode(PDO::FETCH_ASSOC);
					while ( $row = $sql->fetch() ) {
				?>
						<option value="<?php echo $row["id"]; ?>"
							<?php
								if ($groups_form_type == 'edit_group') {
                                    if (!empty($group_arguments['members'])) {
									    if (in_array($row["id"], $group_arguments['members'])) {
										    echo ' selected="selected"';
                                        }
                                    }
								}
							?>
						><?php echo html_output($row["name"]); ?></option>
				<?php
					}
				?>
			</select>
			<div class="select_control_buttons">
				<a href="#" class="btn btn-pslight add-all" data-target="members"><?php _e('Add all','cftp_admin'); ?></a>
				<a href="#" class="btn btn-pslight remove-all" data-target="members"><?php _e('Remove all','cftp_admin'); ?></a>
			</div>
		</div>
	</div>

	<div class="form-group row">
		<div class="col-sm-8 offset-sm-4">
			<label for="public">
				<input type="checkbox" name="public" id="public" <?php echo (isset($group_arguments['public']) && $group_arguments['public'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Public','cftp_admin'); ?>
				<p class="field_note form-text"><?php _e('Allows clients to request access to this group in the registration process and when editing their own profile.','cftp_admin'); ?></p>
                <?php
                    if ( get_option('public_listing_page_enable') != 1 ) {
                        $msg = '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i> ' . __('The group cannot be made publicly visible while the public page is disabled.');
                        $msg .= ' <a href="'.BASE_URI.'options.php?section=privacy" class="underline" targeT="_blank">'.__('Go to privacy options', 'cftp_admin').'</a>';
                        echo system_message('warning', $msg);
                    }
                ?>
			</label>
		</div>
    </div>

	<div class="inside_form_buttons">
		<button type="submit" class="btn btn-wide btn-primary"><?php echo html_output($submit_value); ?></button>
	</div>
</form>
