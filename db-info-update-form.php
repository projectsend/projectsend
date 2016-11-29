<?php
/**
 * Contains the form that is used when adding or editing users.
 *
 * @package		ProjectSend
 * @subpackage	Users
 *
 */
?>
<?php
$form_action = 'databse_info.php';
$submit_value ='update database info';
?>
<form action="<?php echo html_output($form_action); ?>" name="adduser" method="post" class="form-horizontal">
	<div class="form-group">
		<label for="db_host_name" class="col-sm-4 control-label"><?php _e('DB Host','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="db_host" id="db_host" class="form-control required" value="<?php echo (isset($db_host)) ? html_output(stripslashes($db_host)) : ''; ?>" />
		</div>
	</div>
	<div class="form-group">
		<label for="db_name" class="col-sm-4 control-label"><?php _e('DB name','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="db_name" id="db_name" class="form-control required" value="<?php echo (isset($db_name)) ? html_output(stripslashes($db_name)) : ''; ?>" />
		</div>
	</div>
	<div class="form-group">
		<label for="db_user_name" class="col-sm-4 control-label"><?php _e('DB Username','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="db_user_name" id="db_user_name" class="form-control required" value="<?php echo (isset($db_user_name)) ? html_output(stripslashes($db_user_name)) : ''; ?>" />
		</div>
	</div>
	<div class="form-group">
		<label for="db_password" class="col-sm-4 control-label"><?php _e('DB Password','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="password" name="db_password" id="db_password" class="form-control required" value="<?php echo (isset($decPassword)) ? html_output(stripslashes($decPassword)) : ''; ?>"  />
		</div>
	</div>

	<div class="inside_form_buttons">
		<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php echo $submit_value; ?></button>
	</div>

</form>
