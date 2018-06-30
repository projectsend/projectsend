<?php
/**
 * Allows the administrator to customize the emails
 * sent by the system.
 *
 * @package ProjectSend
 * @subpackage Options
 */
$allowed_levels = array(9);
require_once('bootstrap.php');

$section = ( !empty( $_GET['section'] ) ) ? $_GET['section'] : $_POST['section'];

switch ( $section ) {
	case 'header_footer':
		$section_title	= __('Header / footer','cftp_admin');
		$checkboxes		= array(
								'email_header_footer_customize',
							);
		break;
	case 'new_files_by_user':
		$section_title	= __('New file by user','cftp_admin');
		$checkboxes		= array(
								'email_new_file_by_user_customize',
								'email_new_file_by_user_subject_customize',
							);
		break;
	case 'new_files_by_client':
		$section_title	= __('New file by client','cftp_admin');
		$checkboxes		= array(
								'email_new_file_by_client_customize',
								'email_new_file_by_client_subject_customize',
							);
		break;
	case 'new_client':
		$section_title	= __('New client (welcome)','cftp_admin');
		$checkboxes		= array(
								'email_new_client_by_user_customize',
								'email_new_client_by_user_subject_customize',
							);
		break;
	case 'new_client_self':
		$section_title	= __('New client (self-registered)','cftp_admin');
		$checkboxes		= array(
								'email_new_client_by_self_customize',
								'email_new_client_by_self_subject_customize',
							);
		break;
	case 'account_approve':
		$section_title	= __('Approve client account','cftp_admin');
		$checkboxes		= array(
								'email_account_approve_subject_customize',
								'email_account_approve_customize',
							);
		break;
	case 'account_deny':
		$section_title	= __('Deny client account','cftp_admin');
		$checkboxes		= array(
								'email_account_deny_subject_customize',
								'email_account_deny_customize',
							);
		break;
	case 'new_user':
		$section_title	= __('New user (welcome)','cftp_admin');
		$checkboxes		= array(
								'email_new_user_customize',
								'email_new_user_subject_customize',
							);
		break;
	case 'password_reset':
		$section_title	= __('Password reset','cftp_admin');
		$checkboxes		= array(
								'email_pass_reset_customize',
								'email_pass_reset_subject_customize',
							);
		break;
	case 'client_edited':
		$section_title	= __('Client updated memberships','cftp_admin');
		$checkboxes		= array(
								'email_client_edited_subject_customize',
								'email_client_edited_customize',
							);
		break;
	default:
		$location = BASE_URI . 'email-templates.php?section=header_footer';
		header("Location: $location");
		die();
		break;
}

$page_title = $section_title;

$active_nav = 'emails';

include_once ADMIN_TEMPLATES_DIR . DS . 'header.php';

if ($_POST) {
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
	$section_redirect = html_output($_POST['section']);
	$location = BASE_URI . 'email-templates.php?section=' . $section_redirect;

	if ( !empty( $query_state ) ) {
		$location .= '&status=' . $query_state;
	}

	header("Location: $location");
	die();
}
?>
<div class="col-xs-12 col-sm-12 col-lg-6">
	<?php
		if (isset($_GET['status'])) {
			switch ($_GET['status']) {
				case '1':
					$msg = __('Options updated succesfuly.','cftp_admin');
					echo system_message('success',$msg);
					break;
				case '2':
					$msg = __('There was an error. Please try again.','cftp_admin');
					echo system_message('danger',$msg);
					break;
			}
		}

	?>

	<div class="white-box">
		<div class="white-box-interior">
			<?php
				$href_string = ' ' . __('(to be used as href on a link tag)','cftp_admin');
		
				$options_groups = array(
										'new_files_by_user'	=> array(
																		'subject_checkbox'	=> 'email_new_file_by_user_subject_customize',
																		'subject'			=> 'email_new_file_by_user_subject',
																		'body_checkbox'		=> 'email_new_file_by_user_customize',
																		'body_textarea'		=> 'email_new_file_by_user_text',
																		'description'		=> __('This email will be sent to a client whenever a new file has been assigned to his account.','cftp_admin'),
																		'subject_check'		=> EMAIL_NEW_FILE_BY_USER_SUBJECT_CUSTOMIZE,
																		'subject_text'		=> EMAIL_NEW_FILE_BY_USER_SUBJECT,
																		'body_check'		=> EMAIL_NEW_FILE_BY_USER_CUSTOMIZE,
																		'body_text'			=> EMAIL_NEW_FILE_BY_USER_TEXT,
																		'tags'				=> array(
																										'%FILES%'		=> __('Shows the list of files','cftp_admin'),
																										'%URI%'			=> __('The login link','cftp_admin') . $href_string,
																									),
																		'default_text'		=> EMAIL_TEMPLATE_NEW_FILE_BY_USER,
																	),
										'new_files_by_client'	=> array(
																		'subject_checkbox'	=> 'email_new_file_by_client_subject_customize',
																		'subject'			=> 'email_new_file_by_client_subject',
																		'body_checkbox'		=> 'email_new_file_by_client_customize',
																		'body_textarea'		=> 'email_new_file_by_client_text',
																		'description'		=> __('This email will be sent to the system administrator whenever a client uploads a new file.','cftp_admin'),
																		'subject_check'		=> EMAIL_NEW_FILE_BY_CLIENT_SUBJECT_CUSTOMIZE,
																		'subject_text'		=> EMAIL_NEW_FILE_BY_CLIENT_SUBJECT,
																		'body_check'		=> EMAIL_NEW_FILE_BY_CLIENT_CUSTOMIZE,
																		'body_text'			=> EMAIL_NEW_FILE_BY_CLIENT_TEXT,
																		'tags'				=> array(
																										'%FILES%'		=> __('Shows the list of files','cftp_admin'),
																										'%URI%'			=> __('The login link','cftp_admin') . $href_string,
																									),
																		'default_text'		=> EMAIL_TEMPLATE_NEW_FILE_BY_CLIENT,
																	),
										'new_client'			=> array(
																		'subject_checkbox'	=> 'email_new_client_by_user_subject_customize',
																		'subject'			=> 'email_new_client_by_user_subject',
																		'body_checkbox'		=> 'email_new_client_by_user_customize',
																		'body_textarea'		=> 'email_new_client_by_user_text',
																		'description'		=> __('This email will be sent to the new client after an administrator has created his new account. It would be best to include the log in details (username and password).','cftp_admin'),
																		'subject_check'		=> EMAIL_NEW_CLIENT_BY_USER_SUBJECT_CUSTOMIZE,
																		'subject_text'		=> EMAIL_NEW_CLIENT_BY_USER_SUBJECT,
																		'body_check'		=> EMAIL_NEW_CLIENT_BY_USER_CUSTOMIZE,
																		'body_text'			=> EMAIL_NEW_CLIENT_BY_USER_TEXT,
																		'tags'				=> array(
																										'%USERNAME%'	=> __('The new username for this account','cftp_admin'),
																										'%PASSWORD%'	=> __('The new password for this account','cftp_admin'),
																										'%URI%'			=> __('The login link','cftp_admin') . $href_string,
																									),
																		'default_text'		=> EMAIL_TEMPLATE_NEW_CLIENT,
																	),
										'new_client_self'		=> array(
																		'subject_checkbox'	=> 'email_new_client_by_self_subject_customize',
																		'subject'			=> 'email_new_client_by_self_subject',
																		'body_checkbox'		=> 'email_new_client_by_self_customize',
																		'body_textarea'		=> 'email_new_client_by_self_text',
																		'description'		=> __('This email will be sent to the system administrator after a new client has created a new account for himself.','cftp_admin'),
																		'subject_check'		=> EMAIL_NEW_CLIENT_BY_SELF_SUBJECT_CUSTOMIZE,
																		'subject_text'		=> EMAIL_NEW_CLIENT_BY_SELF_SUBJECT,
																		'body_check'		=> EMAIL_NEW_CLIENT_BY_SELF_CUSTOMIZE,
																		'body_text'			=> EMAIL_NEW_CLIENT_BY_SELF_TEXT,
																		'tags'				=> array(
																										'%FULLNAME%'		=> __('The full name the client registered with','cftp_admin'),
																										'%USERNAME%'		=> __('The new username for this account','cftp_admin'),
																										'%URI%'				=> __('The login link','cftp_admin') . $href_string,
																										'%GROUPS_REQUESTS%' => __('List of groups that the client requests membership to','cftp_admin'),
																									),
																		'default_text'		=> EMAIL_TEMPLATE_NEW_CLIENT_SELF,
																	),
										'account_approve'		=> array(
																		'subject_checkbox'	=> 'email_account_approve_subject_customize',
																		'subject'			=> 'email_account_approve_subject',
																		'body_checkbox'		=> 'email_account_approve_customize',
																		'body_textarea'		=> 'email_account_approve_text',
																		'description'		=> __('This email will be sent to a client that requested an account if it gets approved. Group membership requests are also mentioned on the email, separated by their approval status.','cftp_admin'),
																		'subject_check'		=> EMAIL_ACCOUNT_APPROVE_SUBJECT_CUSTOMIZE,
																		'subject_text'		=> EMAIL_ACCOUNT_APPROVE_SUBJECT,
																		'body_check'		=> EMAIL_ACCOUNT_APPROVE_CUSTOMIZE,
																		'body_text'			=> EMAIL_ACCOUNT_APPROVE_TEXT,
																		'tags'				=> array(
																										'%GROUPS_APPROVED%'	=> __('List of approved group memberships','cftp_admin'),
																										'%GROUPS_DENIED%'	=> __('List of denied group memberships','cftp_admin'),
																										'%URI%'				=> __('The login link','cftp_admin') . $href_string,
																									),
																		'default_text'		=> EMAIL_TEMPLATE_ACCOUNT_APPROVE,
																	),
										'account_deny'		=> array(
																		'subject_checkbox'	=> 'email_account_deny_subject_customize',
																		'subject'			=> 'email_account_deny_subject',
																		'body_checkbox'		=> 'email_account_deny_customize',
																		'body_textarea'		=> 'email_account_deny_text',
																		'description'		=> __('This email will be sent to a client that requested an account if it gets denied. All group membership requests for this account are denied automatically.','cftp_admin'),
																		'subject_check'		=> EMAIL_ACCOUNT_DENY_SUBJECT_CUSTOMIZE,
																		'subject_text'		=> EMAIL_ACCOUNT_DENY_SUBJECT,
																		'body_check'		=> EMAIL_ACCOUNT_DENY_CUSTOMIZE,
																		'body_text'			=> EMAIL_ACCOUNT_DENY_TEXT,
																		'tags'				=> array(
																									),
																		'default_text'		=> EMAIL_TEMPLATE_ACCOUNT_DENY,
																	),
										'new_user'				=> array(
																		'subject_checkbox'	=> 'email_new_user_subject_customize',
																		'subject'			=> 'email_new_user_subject',
																		'body_checkbox'		=> 'email_new_user_customize',
																		'body_textarea'		=> 'email_new_user_text',
																		'description'		=> __('This email will be sent to the new system user after an administrator has created his new account. It would be best to include the log in details (username and password).','cftp_admin'),
																		'subject_check'		=> EMAIL_NEW_USER_SUBJECT_CUSTOMIZE,
																		'subject_text'		=> EMAIL_NEW_USER_SUBJECT,
																		'body_check'		=> EMAIL_NEW_USER_CUSTOMIZE,
																		'body_text'			=> EMAIL_NEW_USER_TEXT,
																		'tags'				=> array(
																										'%USERNAME%'	=> __('The new username for this account','cftp_admin'),
																										'%PASSWORD%'	=> __('The new password for this account','cftp_admin'),
																										'%URI%'			=> __('The login link','cftp_admin') . $href_string,
																									),
																		'default_text'		=> EMAIL_TEMPLATE_NEW_USER,
																	),
										'password_reset'		=> array(
																		'subject_checkbox'	=> 'email_pass_reset_subject_customize',
																		'subject'			=> 'email_pass_reset_subject',
																		'body_checkbox'		=> 'email_pass_reset_customize',
																		'body_textarea'		=> 'email_pass_reset_text',
																		'description'		=> __('This email will be sent to a user or client when they try to reset their password.','cftp_admin'),
																		'subject_check'		=> EMAIL_PASS_RESET_SUBJECT_CUSTOMIZE,
																		'subject_text'		=> EMAIL_PASS_RESET_SUBJECT,
																		'body_check'		=> EMAIL_PASS_RESET_CUSTOMIZE,
																		'body_text'			=> EMAIL_PASS_RESET_TEXT,
																		'tags'				=> array(
																										'%USERNAME%'	=> __('The username for this account','cftp_admin'),
																										'%TOKEN%'		=> __('The text string unique to this request. Must be included somewhere.','cftp_admin'),
																										'%URI%'			=> __('The link to continue the process','cftp_admin') . $href_string,
																									),
																		'default_text'		=> EMAIL_TEMPLATE_PASSWORD_RESET,
																	),
										'client_edited'		=> array(
																		'subject_checkbox'	=> 'email_client_edited_subject_customize',
																		'subject'			=> 'email_client_edited_subject',
																		'body_checkbox'		=> 'email_client_edited_customize',
																		'body_textarea'		=> 'email_client_edited_text',
																		'description'		=> __('This email will be sent to the system administrator when a client edits his account and changes the public groups membership requests.','cftp_admin'),
																		'subject_check'		=> EMAIL_CLIENT_EDITED_SUBJECT_CUSTOMIZE,
																		'subject_text'		=> EMAIL_CLIENT_EDITED_SUBJECT,
																		'body_check'		=> EMAIL_CLIENT_EDITED_CUSTOMIZE,
																		'body_text'			=> EMAIL_CLIENT_EDITED_TEXT,
																		'tags'				=> array(
																										'%FULLNAME%'		=> __('The full name the client registered with','cftp_admin'),
																										'%USERNAME%'		=> __('The new username for this account','cftp_admin'),
																										'%URI%'				=> __('The login link','cftp_admin') . $href_string,
																										'%GROUPS_REQUESTS%' => __('List of groups that the client requests membership to','cftp_admin'),
																									),
																		'default_text'		=> EMAIL_TEMPLATE_CLIENT_EDITED,
																	),
									);
			?>


			<form action="email-templates.php" name="templatesform" method="post" enctype="multipart/form-data" class="form-horizontal">
				<input type="hidden" name="section" value="<?php echo $section; ?>">
	
				<?php
					/** Header and footer options */
					if ( $section == 'header_footer' ) {
				?>
						<p class="text-warning"><?php _e('Here you set up the header and footer of every email, or use the default ones available with the system. Use this to customize each part and include, for example, your own logo and markup.','cftp_admin'); ?></p>
						<p class="text-warning"><?php _e("Do not forget to also include -and close accordingly- the basic structural HTML tags (DOCTYPE, HTML, HEADER, BODY).",'cftp_admin'); ?></p>

						<div class="options_divide"></div>

						<div class="form-group">
							<div class="col-sm-12">
								<label for="email_header_footer_customize">
									<input type="checkbox" value="1" id="email_header_footer_customize" name="email_header_footer_customize" <?php echo (EMAIL_HEADER_FOOTER_CUSTOMIZE == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Use custom header / footer','cftp_admin'); ?>
								</label>
							</div>
						</div>

						<div class="form-group">
							<div class="col-sm-12">
								<label for="email_header_text"><?php _e('Header','cftp_admin'); ?></label>
								<textarea name="email_header_text" id="email_header_text" class="form-control textarea_high"><?php echo EMAIL_HEADER_TEXT; ?></textarea>
								<p class="field_note"><?php _e('You can use HTML tags here.','cftp_admin'); ?></p>
							</div>
						</div>

						<div class="preview_button">
							<button type="button" class="btn btn-default load_default" data-textarea="email_header_text" data-file="<?php echo EMAIL_TEMPLATE_HEADER; ?>"><?php _e('Replace with default','cftp_admin'); ?></button>
						</div>
						
						<hr />

						<div class="form-group">
							<div class="col-sm-12">
								<label for="email_footer_text"><?php _e('Footer','cftp_admin'); ?></label>
								<textarea name="email_footer_text" id="email_footer_text" class="form-control textarea_high"><?php echo EMAIL_FOOTER_TEXT; ?></textarea>
								<p class="field_note"><?php _e('You can use HTML tags here.','cftp_admin'); ?></p>
							</div>
						</div>

						<div class="preview_button">
							<button type="button" class="btn btn-default load_default" data-textarea="email_footer_text" data-file="<?php echo EMAIL_TEMPLATE_FOOTER; ?>"><?php _e('Replace with default','cftp_admin'); ?></button>
						</div>
				<?php
					}

					/** All other templates */
					if ( array_key_exists( $section, $options_groups ) ) {
						$group = $options_groups[$section];
				?>
						<p class="text-warning"><?php echo $group['description']; ?></p>

						<div class="options_divide"></div>

						<div class="form-group">
							<div class="col-sm-12">
								<label for="<?php echo $group['subject_checkbox']; ?>">
									<input type="checkbox" value="1" name="<?php echo $group['subject_checkbox']; ?>" id="<?php echo $group['subject_checkbox']; ?>" class="checkbox_options" <?php echo ($group['subject_check'] == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Use custom subject','cftp_admin'); ?>
								</label>
							</div>
						</div>

						<div class="form-group">
							<div class="col-sm-12">
								<input type="text" name="<?php echo $group['subject']; ?>" id="<?php echo $group['subject']; ?>" class="form-control" placeholder="<?php _e('Add your custom subject','cftp_admin'); ?>" value="<?php echo $group['subject_text']; ?>" />
							</div>
						</div>	

						<div class="options_divide"></div>

						<div class="form-group">
							<div class="col-sm-12">
								<label for="<?php echo $group['body_checkbox']; ?>">
									<input type="checkbox" value="1" name="<?php echo $group['body_checkbox']; ?>" id="<?php echo $group['body_checkbox']; ?>" class="checkbox_options" <?php echo ($group['body_check'] == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Use custom template','cftp_admin'); ?>
								</label>
							</div>
						</div>

						<div class="form-group">
							<div class="col-sm-12">
								<label for="<?php echo $group['body_textarea']; ?>"><?php _e('Template text','cftp_admin'); ?></label>
								<textarea name="<?php echo $group['body_textarea']; ?>" id="<?php echo $group['body_textarea']; ?>"  class="form-control textarea_high"><?php echo $group['body_text']; ?></textarea>
								<p class="field_note"><?php _e('You can use HTML tags here.','cftp_admin'); ?></p>
							</div>
						</div>	

						<?php
							if (!empty($group['tags'])) {
						?>
								<p><strong><?php _e("The following tags can be used on this e-mails' body.",'cftp_admin'); ?></strong></p>
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

						<hr />
						<div class="preview_button">
							<button type="button" class="btn btn-default load_default" data-textarea="<?php echo $group['body_textarea']; ?>" data-file="<?php echo $group['default_text']; ?>"><?php _e('Replace with default','cftp_admin'); ?></button>
							<button type="button" data-preview="<?php echo $section; ?>" class="btn btn-wide btn-primary preview"><?php _e('Preview this template','cftp_admin'); ?></button>
							<?php
								$message = __("Before trying this function, please save your changes to see them reflected on the preview.",'cftp_admin');
								echo system_message('info', $message);
							?>
						</div>
				<?php
					}
				?>

				<div class="after_form_buttons">
					<button type="submit" name="submit" class="btn btn-wide btn-primary empty"><?php _e('Save options','cftp_admin'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
	$(document).ready(function(e) {
		$('.load_default').click(function(e) {
			e.preventDefault();
			var file		= jQuery(this).data('file');
			var textarea	= '#'+jQuery(this).data('textarea');
			var accept		= confirm('<?php _e('Please confirm: replace the custom template text with the default one?','cftp_admin'); ?>');
			if ( accept ) {
				$.ajax({
					url: "<?php echo EMAIL_TEMPLATES_URI; ?>"+file,
					async: false,
					cache: false,
					success: function (data){
						$(textarea).text(data);
					},
					error: function() {
						alert("<?php _e('Error: the content could not be loaded','cftp_admin'); ?>");
					}
				});
			}
		});

		$('.preview').click(function(e) {
			e.preventDefault();
			var type	= jQuery(this).data('preview');
			var url		= '<?php echo BASE_URI; ?>email-preview.php?t=' + type;
		    window.open(url, "previewWindow", "width=800,height=600,scrollbars=yes");
		});
	});
</script>

<?php
	include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
