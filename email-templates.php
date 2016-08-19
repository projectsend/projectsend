<?php
/**
 * Allows the administrator to customize the emails
 * sent by the system.
 *
 * @package ProjectSend
 * @subpackage Options
 */
$easytabs = 1;
$allowed_levels = array(9);
require_once('sys.includes.php');

$page_title = __('E-mail templates','cftp_admin');

$active_nav = 'options';
include('header.php');

if ($_POST) {
	/** Checkboxes */
	$checkboxes				= array(
								'email_header_footer_customize',
								'email_new_file_by_user_customize',
								'email_new_file_by_client_customize',
								'email_new_client_by_user_customize',
								'email_new_client_by_self_customize',
								'email_new_user_customize',
								'email_pass_reset_customize',
							);
	foreach ($checkboxes as $checkbox) {
		$_POST[$checkbox] = (empty($_POST[$checkbox]) || !isset($_POST[$checkbox])) ? 0 : 1;
	}

	/**
	 * Escape all the posted values on a single function.
	 * Defined on functions.php
	 */
	$keys = array_keys($_POST);

	$options_total = count($keys);

	$updated = 0;
	for ($j = 0; $j < $options_total; $j++) {
		$save = $dbh->prepare( "UPDATE " . TABLE_OPTIONS . " SET value=:value WHERE name=:name" );
		$save->bindParam(':value', $_POST[$keys[$j]]);
		$save->bindParam(':name', $keys[$j]);
		$save->execute();

		$updated++;
	}
	if ($updated > 0){
		$query_state = '1';
	}
	else {
		$query_state = '2';
	}

	/** Redirect so the options are reflected immediatly */
	while (ob_get_level()) ob_end_clean();
	$location = BASE_URI . 'email-templates.php?status=' . $query_state;
	header("Location: $location");
	die();
}
?>
<div id="main">
	<h2><?php echo $page_title; ?></h2>

	<?php
		if (isset($_GET['status'])) {
			switch ($_GET['status']) {
				case '1':
					$msg = __('Options updated succesfuly.','cftp_admin');
					echo system_message('ok',$msg);
					break;
				case '2':
					$msg = __('There was an error. Please try again.','cftp_admin');
					echo system_message('error',$msg);
					break;
			}
		}

	?>
		
	<script type="text/javascript">
		$(document).ready(function() {
			$('#tab-container').easytabs({
				updateHash: false
			});
		});
	</script>
	
	<?php
		$href_string = ' ' . __('(to be used as href on a link tag)','cftp_admin');

		$options_groups = array(
								1	=> array(
												'tab'			=> 'file_by_user',
												'name'			=> __('New file by user','cftp_admin'),
												'checkbox'		=> 'email_new_file_by_user_customize',
												'textarea'		=> 'email_new_file_by_user_text',
												'description'	=> __('This email will be sent to a client whenever a new file has been assigned to his account.','cftp_admin'),
												'option_check'	=> EMAILS_FILE_BY_USER_USE_CUSTOM,
												'option_text'	=> EMAILS_FILE_BY_USER_TEXT,
												'tags'			=> array(
																			'%FILES%'		=> __('Shows the list of files','cftp_admin'),
																			'%URI%	'		=> __('The login link','cftp_admin') . $href_string,
																		),
											),
								2	=> array(
												'tab'			=> 'file_by_client',
												'name'			=> __('New file by client','cftp_admin'),
												'checkbox'		=> 'email_new_file_by_client_customize',
												'textarea'		=> 'email_new_file_by_client_text',
												'description'	=> __('This email will be sent to the system administrator whenever a client uploads a new file.','cftp_admin'),
												'option_check'	=> EMAILS_FILE_BY_CLIENT_USE_CUSTOM,
												'option_text'	=> EMAILS_FILE_BY_CLIENT_TEXT,
												'tags'			=> array(
																			'%FILES%'		=> __('Shows the list of files','cftp_admin'),
																			'%URI%	'		=> __('The login link','cftp_admin') . $href_string,
																		),
											),
								3	=> array(
												'tab'			=> 'client_by_user',
												'name'			=> __('New client (welcome)','cftp_admin'),
												'checkbox'		=> 'email_new_client_by_user_customize',
												'textarea'		=> 'email_new_client_by_user_text',
												'description'	=> __('This email will be sent to the new client after an administrator has created his new account. It would be best to include the log in details (username and password).','cftp_admin'),
												'option_check'	=> EMAILS_CLIENT_BY_USER_USE_CUSTOM,
												'option_text'	=> EMAILS_CLIENT_BY_USER_TEXT,
												'tags'			=> array(
																			'%USERNAME%'	=> __('The new username for this account','cftp_admin'),
																			'%PASSWORD%'	=> __('The new password for this account','cftp_admin'),
																			'%URI%	'		=> __('The login link','cftp_admin') . $href_string,
																		),
											),
								4	=> array(
												'tab'			=> 'client_by_self',
												'name'			=> __('New client (self-registered)','cftp_admin'),
												'checkbox'		=> 'email_new_client_by_self_customize',
												'textarea'		=> 'email_new_client_by_self_text',
												'description'	=> __('This email will be sent to the system administrator after a new client has created a new account for himself.','cftp_admin'),
												'option_check'	=> EMAILS_CLIENT_BY_SELF_USE_CUSTOM,
												'option_text'	=> EMAILS_CLIENT_BY_SELF_TEXT,
												'tags'			=> array(
																			'%FULLNAME%'	=> __('The full name the client registered with','cftp_admin'),
																			'%USERNAME%'	=> __('The new username for this account','cftp_admin'),
																			'%URI%	'		=> __('The login link','cftp_admin') . $href_string,
																		),
											),
								5	=> array(
												'tab'			=> 'new_user_welcome',
												'name'			=> __('New user (welcome)','cftp_admin'),
												'checkbox'		=> 'email_new_user_customize',
												'textarea'		=> 'email_new_user_text',
												'description'	=> __('This email will be sent to the new system user after an administrator has created his new account. It would be best to include the log in details (username and password).','cftp_admin'),
												'option_check'	=> EMAILS_NEW_USER_USE_CUSTOM,
												'option_text'	=> EMAILS_NEW_USER_TEXT,
												'tags'			=> array(
																			'%USERNAME%'	=> __('The new username for this account','cftp_admin'),
																			'%PASSWORD%'	=> __('The new password for this account','cftp_admin'),
																			'%URI%	'		=> __('The login link','cftp_admin') . $href_string,
																		),
											),
								6	=> array(
												'tab'			=> 'password_reset',
												'name'			=> __('Password reset','cftp_admin'),
												'checkbox'		=> 'email_pass_reset_customize',
												'textarea'		=> 'email_pass_reset_text',
												'description'	=> __('This email will be sent to a user or client when they try to reset their password.','cftp_admin'),
												'option_check'	=> EMAILS_PASS_RESET_USE_CUSTOM,
												'option_text'	=> EMAILS_PASS_RESET_TEXT,
												'tags'			=> array(
																			'%USERNAME%'	=> __('The username for this account','cftp_admin'),
																			'%TOKEN%'		=> __('The text string unique to this request. Must be included somewhere.','cftp_admin'),
																			'%URI%	'		=> __('The link to continue the process','cftp_admin') . $href_string,
																		),
											),
							);
	?>

	<form action="email-templates.php" name="templatesform" method="post">
		<div id="outer_tabs_wrapper">

			<div id="tab-container" class="tab-container">
				<ul class="etabs">
					<li class="tab">
						<a href="#tab_header_footer">
							<?php _e('Header / Footer','cftp_admin'); ?>
						</a>
					</li>
					<?php
						foreach ($options_groups as $group) {
					?>
							<li class="tab">
								<a href="#tab_<?php echo $group['tab']; ?>">
									<?php echo $group['name']; ?>
								</a>
							</li>
					<?php
						}
					?>
				</ul>
				<div class="panel-container">
					<div id="tab_header_footer">
						<div class="options_box whitebox">
							<ul class="form_fields">
								<li>
									<h3><?php _e('Header / Footer','cftp_admin'); ?></h3>
									<p class="text-warning"><?php _e('Here you set up the header and footer of every email, or use the default ones available with the system. Use this to customize each part and include, for example, your own logo and markup.','cftp_admin'); ?></p>
									<p class="text-warning"><?php _e("Do not forget to also include -and close accordingly- the basic structural HTML tags (DOCTYPE, HTML, HEADER, BODY).",'cftp_admin'); ?></p>
								</li>
								<li>
									<label for="email_header_footer_customize"><?php _e('Use custom header / footer','cftp_admin'); ?></label>
									<input type="checkbox" value="1" name="email_header_footer_customize" class="checkbox_options" <?php echo (EMAILS_HEADER_FOOTER_CUSTOM == 1) ? 'checked="checked"' : ''; ?> />
								</li>
								<li>
									<label for="email_header_text"><?php _e('Header','cftp_admin'); ?></label>
									<textarea name="email_header_text" id="email_header_text"><?php echo EMAILS_HEADER_TEXT; ?></textarea>
								</li>	
								<li>
									<p class="field_note"><?php _e('You can use HTML tags here.','cftp_admin'); ?></p>
								</li>	

								<li>
									<label for="email_footer_text"><?php _e('Footer','cftp_admin'); ?></label>
									<textarea name="email_footer_text" id="email_footer_text"><?php echo EMAILS_FOOTER_TEXT; ?></textarea>
								</li>	
								<li>
									<p class="field_note"><?php _e('You can use HTML tags here.','cftp_admin'); ?></p>
								</li>	
							</ul>
						</div>
					</div>
					<?php
						foreach ($options_groups as $group) {
					?>
							<div id="tab_<?php echo $group['tab']; ?>">
								<div class="options_box whitebox">
									<ul class="form_fields">
										<li>
											<h3><?php echo $group['name']; ?></h3>
											<p class="text-warning"><?php echo $group['description']; ?></p>
										</li>
										<li>
											<label for="<?php echo $group['checkbox']; ?>"><?php _e('Use custom template','cftp_admin'); ?></label>
											<input type="checkbox" value="1" name="<?php echo $group['checkbox']; ?>" class="checkbox_options" <?php echo ($group['option_check'] == 1) ? 'checked="checked"' : ''; ?> />
										</li>
										<li>
											<label for="<?php echo $group['textarea']; ?>"><?php _e('Template text','cftp_admin'); ?></label>
											<textarea name="<?php echo $group['textarea']; ?>" id="<?php echo $group['textarea']; ?>"><?php echo $group['option_text']; ?></textarea>
										</li>	
										
										<li>
											<p class="field_note"><?php _e('You can use HTML tags here.','cftp_admin'); ?></p>
										</li>	
										
										<li class="email_available_tags">
											<p><strong><?php _e("The following tags can be used on this e-mails' body.",'cftp_admin'); ?></strong></p>
											<?php
												if (!empty($group['tags'])) {
											?>
													<ul>
														<?php
															foreach ($group['tags'] as $tag => $description) {
														?>
																<li><i class="icon-ok"></i> <strong><?php echo $tag; ?></strong>: <?php echo $description; ?></li>
														<?php
															}
														?>
													</ul>
											<?php
												}
											?>
										</li>			
						
									</ul>
									<hr />
									<div class="preview_button">
										<button type="button" data-preview="<?php echo $group['tab']; ?>" class="btn btn-wide btn-primary preview"><?php _e('Preview this template','cftp_admin'); ?></button>
										<p><?php _e("Before trying this function, please save your changes to see them reflected on the preview.",'cftp_admin'); ?></p>
									</div>
								</div>
							</div>
					<?php
						}
					?>
				</div>
			</div>
		</div>

		<div class="after_form_buttons">
			<button type="submit" name="submit" class="btn btn-wide btn-primary empty"><?php _e('Update all options','cftp_admin'); ?></button>
		</div>
	</form>
</div>

	<div class="clear"></div>
</div>

<script type="text/javascript">
	$(document).ready(function(e) {
		$('.preview').click(function(e) {
			e.preventDefault();
			var type	= jQuery(this).data('preview');
			var theurl	= '<?php echo BASE_URI; ?>email-preview.php?t=' + type;
		    window.open(theurl, "previewWindow", "width=800,height=600,scrollbars=yes");
		});
	});
</script>

<?php include('footer.php'); ?>