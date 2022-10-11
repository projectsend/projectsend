<?php
/**
 * Contains the form that is used when adding or editing groups.
 */

switch ($asset_form_type) {
	case 'new':
		$submit_value = __('Create asset','cftp_admin');
		$form_action = 'custom-assets-add.php';
		break;
	case 'edit':
		$submit_value = __('Save asset','cftp_admin');
		$form_action = 'custom-assets-edit.php?id='.$asset_id;
		break;
}

$tags_message = null;
switch ($language) {
    case 'css':
        $tags_message = ['&ltstyle&gt','&lt/style&gt'];
    break;
    case 'js':
        $tags_message = ['&ltscript&gt','&lt/script&gt'];
    break;
}
?>

<form action="<?php echo html_output($form_action); ?>" name="asset_form" id="asset_form" method="post" class="form-horizontal">
    <?php \ProjectSend\Classes\Csrf::addCsrf(); ?>
    <input type="hidden" name="language" id="asset_language" value="<?php echo $language; ?>">

	<div class="form-group row">
		<label for="title" class="col-sm-4 control-label"><?php _e('Title','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="title" id="title" class="form-control required" value="<?php echo (isset($asset_arguments['title'])) ? html_output(stripslashes($asset_arguments['title'])) : ''; ?>" required />
		</div>
	</div>

	<div class="form-group row">
		<label for="content" class="col-sm-4 control-label"><?php _e('Content','cftp_admin'); ?></label>
		<div class="col-sm-8">
            <?php if (!empty($tags_message[0])) { ?><p class="field_note form-text"><?php echo $tags_message[0]; ?></p><?php } ?>
			<textarea name="content" id="content" class="form-control"><?php echo (isset($asset_arguments['content'])) ? html_output($asset_arguments['content']) : ''; ?></textarea>
            <?php if (!empty($tags_message)) { ?>
                <?php if (!empty($tags_message[1])) { ?><p class="field_note form-text"><?php echo $tags_message[1]; ?></p><?php } ?>
                <p class="field_note form-text"><?php echo sprintf(__('Do not add the %s and %s tags, they will be added automatically when the code is rendered', 'cftp_admin'), $tags_message[0], $tags_message[1]); ?></p>
            <?php } ?>
		</div>
	</div>

    <div class="form-group row">
		<div class="col-sm-8 offset-sm-4">
			<label for="enabled">
				<input type="checkbox" name="enabled" id="enabled" <?php echo (isset($asset_arguments['enabled']) && $asset_arguments['enabled'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Enabled','cftp_admin'); ?>
			</label>
		</div>
    </div>

    <div class="form-group row">
        <label for="location" class="col-sm-4 control-label"><?php _e('Location','cftp_admin'); ?></label>
        <div class="col-sm-8">
            <select class="form-select" name="location" id="location" required>
                <?php
                    foreach ( get_asset_locations() as $location => $formatted ) {
                ?>
                        <option value="<?php echo $location; ?>" <?php echo (isset($asset_arguments['location']) && $asset_arguments['location'] == $location) ? 'selected="selected"' : ''; ?>><?php echo $formatted; ?></option>
                <?php
                    }
                ?>
            </select>
        </div>
    </div>

    <div class="form-group row">
        <label for="position" class="col-sm-4 control-label"><?php _e('Position','cftp_admin'); ?></label>
        <div class="col-sm-8">
            <select class="form-select" name="position" id="position" required>
                <?php
                    foreach ( get_asset_positions() as $position => $formatted ) {
                ?>
                        <option value="<?php echo $position; ?>" <?php echo (isset($asset_arguments['position']) && $asset_arguments['position'] == $position) ? 'selected="selected"' : ''; ?>><?php echo $formatted; ?></option>
                <?php
                    }
                ?>
            </select>
        </div>
    </div>

	<div class="inside_form_buttons">
		<button type="submit" class="btn btn-wide btn-primary"><?php echo html_output($submit_value); ?></button>
	</div>
</form>
