<?php
/**
 * This file contains javascript functions that are repeated on 2 or more
 * files and use text from translation resources or conditions via php
 *
 * @package		ProjectSend
 * @subpackage	javascript
 *
 */
    header("Content-Type: application/javascript");
	require_once('../../sys.includes.php');
?>
$(document).ready(function(e) {
	/***********************************************************************************
		MODAL: SHOW PUBLIC FILE URL
	***********************************************************************************/
	$('body').on('click', '.public_link', function(e) {
		$(document).psendmodal();
		var type		= $(this).data('type');
		var id		= $(this).data('id');
		var token	= $(this).data('token');

		if ( type == 'group' ) {
			var link_base = '<?php echo PUBLIC_GROUP_URI; ?>?';
			var note_text = '<?php _e('Send this URL to someone to view the allowed group contents according to your privacy settings.','cftp_admin'); ?>';
		}
		else if ( type == 'file' ) {
			var link_base = '<?php echo PUBLIC_DOWNLOAD_URI; ?>?';
			var note_text = '<?php _e('Send this URL to someone to download the file without registering or logging in.','cftp_admin'); ?>';
		}

		var content =  '<div class="public_link_modal">'+
							'<strong><?php _e('Click to select and copy','cftp_admin'); ?></strong>'+
							'<div class="copied"><?php _e('Succesfully copied to clipboard','cftp_admin'); ?></div>'+
							'<div class="copied_not"><?php _e('Content could not be copied to clipboard','cftp_admin'); ?></div>'+
							'<div class="form-group">'+
								'<textarea class="input-large public_link_copy form-control" rows="4" readonly>' + link_base + 'id=' + id + '&token=' + token + '</textarea>'+
							'</div>'+
							'<span class="note">' + note_text + '</span>'+
						'</div>';
		var title 	= '<?php _e('Public URL','cftp_admin'); ?>';
		$('.modal_title span').html(title);
		$('.modal_content').html(content);
	});

	/***********************************************************************************
		APPLY FORM BULK ACTIONS
	***********************************************************************************/
	$("#do_action").click(function() {
		var checks = $("td>input:checkbox").serializeArray();
		var action = $('#action').val();
		if (action != 'none') {
			<?php
				/** GENERIC ACTIONS */
			?>
				if (action == 'delete') {
					if (checks.length == 0) {
						alert('<?php _e('Please select at least one item to proceed.','cftp_admin'); ?>');
						return false;
					}
					else {
						var msg_1 = '<?php _e("You are about to delete",'cftp_admin'); ?>';
						var msg_2 = '<?php _e("items. Are you sure you want to continue?",'cftp_admin'); ?>';
						if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
							return true;
						} else {
							return false;
						}
					}
				}

			<?php
				/** ACTIVITIES LOG ACTIONS */
			?>
				if (action == 'log_clear') {
					var msg = '<?php _e("You are about to delete all activities from the log. Only those used for statistics will remain. Are you sure you want to continue?",'cftp_admin'); ?>';
					if (confirm(msg)) {
						return true;
					} else {
						return false;
					}
				}

				if (action == 'log_download') {
					$(document).psendmodal();
					//Cookies.set('log_download_started', 0, { expires: 100 });
					//setTimeout(check_log_download_cookie, 1000);
					$('.modal_content').html('<p class="loading-img">'+
												'<img src="<?php echo BASE_URI; ?>img/ajax-loader.gif" alt="Loading" /></p>'+
												'<p class="lead text-center text-info"><?php _e('Please wait while your download is prepared.','cftp_admin'); ?></p>'
											);
					$('.modal_content').append('<iframe src="<?php echo BASE_URI; ?>includes/actions.log.export.php?format=csv"></iframe>');

					return false;
				}

			<?php
				/** MANAGE FILES ACTIONS */
			?>
				if (action == 'unassign') {
					var msg_1 = '<?php _e("You are about to unassign",'cftp_admin'); ?>';
					var msg_2 = '<?php _e("files from this account. Are you sure you want to continue?",'cftp_admin'); ?>';
					if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
						return true;
					} else {
						return false;
					}
				}

			<?php
				/** TEMPLATES */
			?>
				if (action == 'zip') {

					var checkboxes = $.map($('input:checkbox:checked'), function(e,i) {
						if (e.value != '0') {
							return +e.value;
						}
					});

					$(document).psendmodal();

					Cookies.set('download_started', 0, { expires: 100 });
					setTimeout(check_download_cookie, 1000);
					$('.modal_content').html('<p class="loading-img"><img src="<?php echo BASE_URI; ?>img/ajax-loader.gif" alt="Loading" /></p>'+
												'<p class="lead text-center text-info"><?php _e('Please wait while your download is prepared.','cftp_admin'); ?></p>'+
												'<p class="text-center text-info"><?php _e('This operation could take a few minutes, depending on the size of the files.','cftp_admin'); ?></p>'
											);
					$.get('<?php echo BASE_URI; ?>process.php', { do:"zip_download", files:checkboxes },
						function(data) {
							var url = '<?php echo BASE_URI; ?>process-zip-download.php?ids=' + data;
							$('.modal_content').append("<iframe id='modal_zip'></iframe>");
							$('#modal_zip').attr('src', url);
						}
					);
				}
		}
		else {
			return false;
		}
	});


	<?php /** CLOSE THE ZIP DOWNLOAD MODAL */ ?>
	/**
	 * Solution to close the modal. Suggested by remez, based on
	 * https://stackoverflow.com/questions/29532788/how-to-display-a-loading-animation-while-file-is-generated-for-download
	 */
	var downloadTimeout;
	var check_download_cookie = function() {
		if (Cookies.get("download_started") == 1) {
			Cookies.set("download_started", "false", { expires: 100 });
			remove_modal();
		} else {
			downloadTimeout = setTimeout(check_download_cookie, 1000);
		}
	};

	<?php /** CLOSE THE LOG CSV DOWNLOAD MODAL */ ?>
	var logdownloadTimeout;
	var check_log_download_cookie = function() {
		if (Cookies.get("log_download_started") == 1) {
			Cookies.set("log_download_started", "false", { expires: 100 });
			remove_modal();
		} else {
			logdownloadTimeout = setTimeout(check_log_download_cookie, 1000);
		}
	};


	<?php
		/** EDIT FILE + UPLOAD FORM */
	?>
		if ( $.isFunction($.fn.chosen) ) {
			$('.chosen-select').chosen({
				no_results_text	: "<?php _e('No results where found.','cftp_admin'); ?>",
				width			: "100%",
				search_contains	: true
			});
		}


	<?php
		/** CKEditor */
	?>
		if ( typeof CKEDITOR !== "undefined" ) {
			CKEDITOR.replaceAll( 'ckeditor' );
		}

});
