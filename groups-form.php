<?php
/**
 * Contains the form that is used when adding or editing groups.
 *
 * @package		ProjectSend
 * @subpackage	Groups
 *
 */
?>

<script type="text/javascript">
	$(document).ready(function() {
		$("form").submit(function() {
			clean_form(this);

			is_complete(this.add_group_form_name,'<?php echo $validation_no_name; ?>');
			// show the errors or continue if everything is ok
			if (show_form_errors() == false) { return false; }
		});
	});
</script>

<?php
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

<form action="<?php echo html_output($form_action); ?>" name="addgroup" method="post" class="form-horizontal">
	<div class="form-group">
		<label for="add_group_form_name" class="col-sm-4 control-label"><?php _e('Group name','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="add_group_form_name" id="add_group_form_name" class="form-control required" value="<?php echo (isset($add_group_data_name)) ? html_output(stripslashes($add_group_data_name)) : ''; ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="add_group_form_description" class="col-sm-4 control-label"><?php _e('Description','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<textarea name="add_group_form_description" id="add_group_form_description" class="form-control"><?php echo (isset($add_group_data_description)) ? html_output($add_group_data_description) : ''; ?></textarea>
		</div>
	</div>

	<div class="form-group assigns">
		<label for="add_group_form_members" class="col-sm-4 control-label"><?php _e('Members','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<select multiple="multiple" id="members-select" class="form-control chosen-select" name="add_group_form_members[]" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
				<?php
					$sql = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " ORDER BY name ASC");
					$sql->execute();
					$sql->setFetchMode(PDO::FETCH_ASSOC);
					while ( $row = $sql->fetch() ) {
				?>
						<option value="<?php echo $row["id"]; ?>"
							<?php
								if($groups_form_type == 'edit_group') {
									if (in_array($row["id"],$current_members)) {
										echo ' selected="selected"';
									}
								}
							?>
						><?php echo html_output($row["name"]); ?></option>
				<?php
					}
				?>
			</select>
			<div class="list_mass_members">
				<a href="#" class="btn btn-default add-all"><?php _e('Add all','cftp_admin'); ?></a>
				<a href="#" class="btn btn-default remove-all"><?php _e('Remove all','cftp_admin'); ?></a>
			</div>
		</div>
	</div>

	<div class="inside_form_buttons">
		<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php echo html_output($submit_value); ?></button>
	</div>
</form>

<script type="text/javascript">
	$(document).ready(function() {
		$('.chosen-select').chosen({
			no_results_text	: "<?php _e('No results where found.','cftp_admin'); ?>",
			search_contains	: true
		});

		$('.add-all').click(function(){
			var selector = $(this).closest('.assigns').find('select');
			$(selector).find('option').each(function(){
				$(this).prop('selected', true);
			});
			$('select').trigger('chosen:updated');
			return false;
		});

		$('.remove-all').click(function(){
			var selector = $(this).closest('.assigns').find('select');
			$(selector).find('option').each(function(){
				$(this).prop('selected', false);
			});
			$('select').trigger('chosen:updated');
			return false;
		});
	});
</script>
