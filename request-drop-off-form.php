<?php
/**
 * Contains the form that is used when user request a drop-off.
 *
 * @package		ProjectSend
 * @subpackage	Request a drop-off
 *
 */
?>


<form action="<?php echo $form_action; ?>" name="addclient" method="post" class="form-horizontal">
	<div class="form-group">
		<div class="col-sm-4"></div>		<div class="col-sm-8" style="padding-bottom: 10px;">
		This page will allow you to send a request to one or more people requesting that they send (upload) one more files for you.
		</div>
		<label for="from_mail_id" class="col-sm-4 control-label"><?php _e('From email','cftp_admin'); ?></label>
		<div class="col-sm-8" style="font-weight:bold">
			<?php echo isset($logged_in_email)?$logged_in_email:''; ?>
			
		</div>
	</div>
	

	<div class="form-group">
		<label for="to_name_request" class="col-sm-4 control-label"><?php _e('To Name','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" value="<?php echo $to_name_request; ?>" name="to_name_request" id="to_name_request" class="form-control <?php if (!$disable_user) { echo 'required'; } ?>" maxlength="<?php echo MAX_USER_CHARS; ?>" placeholder="<?php _e("Enter To name",'cftp_admin'); ?>" />
            <span style="color:#F00"><?php echo $to_nameErr; ?></span>
		</div>
	</div>

	<div class="form-group">
		<label for="from_organization" class="col-sm-4 control-label"><?php _e('Organization','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" value="<?php if(isset($from_organization)) { echo $from_organization; } ?>" name="from_organization" id="from_organization" class="form-control required" value="" placeholder="Organization name" />
		</div>
	</div>

	<div class="form-group">
			
		<label for="to_email_request" class="col-sm-4 control-label"><?php _e('E-mail','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" value="<?php echo $to_email_request; ?>" name="to_email_request" id="to_email_request" class="form-control required"  placeholder="<?php _e("Must be valid and unique",'cftp_admin'); ?>" />
            <span style="color:#F00"><?php echo isset($to_emailErr)?$to_emailErr:''; ?></span>
		</div>	
	</div>

	<div class="form-group">
		<label for="to_subject_request" class="col-sm-4 control-label"><?php _e('Subject','cftp_admin'); ?></label>
		<div class="col-sm-8">
				<input value="<?php echo $to_subject_request; ?>" name="to_subject_request" id="to_subject_request" class="form-control <?php if ($require_pass) { echo 'required'; } ?>" type="text" maxlength="<?php echo MAX_PASS_CHARS; ?>" />
                <span style="color:#F00"><?php echo $to_subErr; ?></span>
		</div>
	</div>

	<div class="form-group">
		<label for="to_note_request" class="col-sm-4 control-label"><?php _e('Note','cftp_admin'); ?></label>
		<div class="col-sm-8">
			This will be sent to the recipient(s). It will also be included in the resulting drop-off sent to you. 
			<textarea name="to_note_request" id="to_note_request" class="form-control" ><?php echo $to_note_request; ?></textarea>
		</div>
	</div>
	
	<?php
		if ( $clients_form_type != 'new_client_self' ) {
			if ( defined('RECAPTCHA_AVAILABLE') ) {
	?>
				<div class="form-group">
					<label class="col-sm-4 control-label"><?php //_e('Verification','cftp_admin'); ?></label>
					<div class="col-sm-8">
						<?php /*?><div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div><?php */?>
                        <!--<div class="g-recaptcha" data-sitekey="6LcUwxYUAAAAAM1HIM_8K_PwanAaXoHIjgneoutx"></div>-->
					</div>
				</div>
	<?php
			}
		}
	?>

	<div class="inside_form_buttons">
		<button type="submit" name="submit" class="btn btn-wide btn-primary">Send the request</button>
		<button type="button" name="clear" class="btn btn-wide clear_button">Clear</button>
	</div>

</form>
