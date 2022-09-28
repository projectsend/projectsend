<?php
/**
 * Contains the form that is used when adding or editing categories.
 */

$show_cancel = false;

switch ( $form_information['type'] ) {
	case 'create':
		$submit_value	= __('Create','cftp_admin');
		break;
	case 'edit':
		$submit_value	= __('Save','cftp_admin');
		$show_cancel	= true;
		break;
}
?>
<form action="categories.php" class="form-horizontal" name="process_category" id="process_category" method="post">
    <input type="hidden" name="processing" id="processing" value="1">
    <?php addCsrf(); ?>
    <?php
        if ( !empty( $action ) && $action == 'edit' ) {
    ?>
            <input type="hidden" name="editing_id" id="editing_id" value="<?php echo $editing; ?>">
    <?php
        }
    ?>

    <div class="categories_form form-horizontal">
        <h3><?php echo $form_information['title']; ?></h3>

        <div class="form-group">
            <label for="category_name" class="col-sm-4 control-label"><?php _e('Name','cftp_admin'); ?></label>
            <div class="col-sm-8 field_wrapper">
                <input type="text" name="category_name" id="category_name" class="form-control required" value="<?php echo (isset($category_name)) ? html_output(stripslashes($category_name)) : ''; ?>" required />
            </div>
        </div>

        <div class="form-group">
            <label for="category_parent" class="col-sm-4 control-label"><?php _e('Parent','cftp_admin'); ?></label>
            <div class="col-sm-8">
                <select name="category_parent" id="category_parent" class="form-control">
                    <option value="0" <?php echo (isset($category_parent) && $category_parent == '0') ? 'selected="selected"' : ''; ?>><?php _e('None','cftp_admin'); ?></option>
                    <?php
                        $select_categories	= get_categories();
                        $ignore				= ( $form_information['type'] == 'edit_category' ) ? $editing : 0;
                        $selected_parent	= ( isset($category_parent) ) ? array( $category_parent ) : array();
                        echo generate_categories_options( $select_categories['arranged'], 0, $selected_parent, 'exclude_and_children', array( $ignore ) );
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

        <div class="inside_form_buttons">
            <?php if ( $show_cancel === true ) { ?>
                <a href="<?php echo BASE_URI; ?>categories.php" name="cancel" class="btn btn-wide btn-default"><?php _e('Cancel','cftp_admin'); ?></a>
            <?php } ?>
            <button type="submit" name="btn_process" id="btn_process" class="btn btn-wide btn-primary"><?php echo html_output($submit_value); ?></button>
        </div>
    </div>
</form>