<?php
/**
 * Contains the form that is used when adding or editing categories.
 *
 * @package		ProjectSend
 * @subpackage	Files
 *
 */

$show_cancel = false;

switch ( $form_information['type'] ) {
	case 'new_category':
		$submit_value	= __('Create','cftp_admin');
		$form_action = 'category-add.php';
		break;
	case 'edit_category':
		$submit_value	= __('Save','cftp_admin');
		$form_action = 'category-edit.php?id='.$edit_id;
		$show_cancel	= true;
		break;
}

?>
<form action="<?php echo html_output($form_action); ?>" name="addcategory" id="process_category" method="post" class="form-horizontal">
<div class="categories_form form-horizontal">
	<h2><?php if ( isset ($form_information['title']) ) { echo $form_information['title']; } ?></h2>

	<div class="form-group">
		<label for="category_name" class="col-sm-4 control-label"><?php _e('Name','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="category_name" id="category_name" class="form-control required" value="<?php echo (isset($category_name)) ? html_output(stripslashes($category_name)) : ''; ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="category_parent" class="col-sm-4 control-label"><?php _e('Parent','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<select name="category_parent" id="category_parent" class="form-control">
				<option value="0" <?php echo (isset($category_parent) && $category_parent == '0') ? 'selected="selected"' : ''; ?>><?php _e('None','cftp_admin'); ?></option>
				<?php
					$ignore				= ( $form_information['type'] == 'edit_category' ) ? $editing : 0;
					$selected_parent	= ( isset($category_parent) ) ? array( $category_parent ) : array();
					echo generate_categories_options( $get_categories['arranged'], 0, $selected_parent, 'exclude_and_children', array( $ignore ) );
				?>
			</select>
		</div>
	</div>

	<div class="form-group">
		<label for="category_description" class="col-sm-4 control-label"><?php _e('Description','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<textarea name="category_description" id="category_description" class="form-control"><?php if ( !empty( $category_description ) ) { echo html_output( $category_description ); } ?></textarea>
		</div>
	</div>

	<div class="inside_form_buttons cc-text-right">
		<?php if ( $show_cancel === true ) { ?>
			<a href="<?php echo BASE_URI; ?>categories.php" name="cancel" class="btn btn-wide btn-default"><?php _e('Cancel','cftp_admin'); ?></a>
		<?php } ?>
		<button type="submit" name="btn_process" id="btn_process" class="btn btn-wide btn-primary"><?php echo html_output($submit_value); ?></button>
	</div>
</div>
</form>
