/**
 * These functions are temporarily separated since they read strings
 * from the json object and need testing.
 */
$(document).ready(function(e) {
	/***********************************************************************************
		MODAL: SHOW PUBLIC FILE URL
	***********************************************************************************/
	$('body').on('click', '.public_link', function(e) {
		$(document).psendmodal();
		var type	= $(this).data('type');
		var id		= $(this).data('id');
		var token	= $(this).data('token');

		if ( type == 'group' ) {
            var link_base = json_strings.uri.public_group + '?';
			var note_text = json_strings.translations.public_group_note;
		}
		else if ( type == 'file' ) {
			var link_base = json_strings.uri.public_download + '?';
			var note_text = json_strings.translations.public_file_note;
		}

		var content =  '<div class="public_link_modal">'+
							'<strong>'+json_strings.translations.copy_click_select+'</strong>'+
							'<div class="copied">'+json_strings.translations.copy_ok+'</div>'+
							'<div class="copied_not">'+json_strings.translations.copy_error+'</div>'+
							'<div class="form-group">'+
								'<textarea class="input-large public_link_copy form-control" rows="4" readonly>' + link_base + 'id=' + id + '&token=' + token + '</textarea>'+
							'</div>'+
							'<span class="note">' + note_text + '</span>'+
						'</div>';
		var title 	= json_strings.translations.public_url;
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
				/** GENERIC ACTIONS */
				if (action == 'delete') {
					if (checks.length == 0) {
						alert(json_strings.translations.select_one_or_more);
						return false;
					}
					else {
                        _formatted = sprintf(json_strings.translations.confirm_delete, checks.length);
                        if (confirm(_formatted)) {
							return true;
						} else {
							return false;
						}
					}
				}

				/** ACTIVITIES LOG ACTIONS */
				if (action == 'log_clear') {
					var msg = json_strings.translations.confirm_delete_log;
					if (confirm(msg)) {
						return true;
					} else {
						return false;
					}
				}

				if (action == 'log_download') {
					$(document).psendmodal();
					Cookies.set('log_download_started', 0, { expires: 100 });
                    setTimeout(check_log_download_cookie, 1000);

                    $('.modal_content').html('<p class="loading-img">'+
												'<img src="'+json_strings.uri.assets_img+'ajax-loader.gif" alt="Loading" /></p>'+
												'<p class="lead text-center text-info">'+json_strings.translations.download_wait+'</p>'
											);
					$('.modal_content').append('<iframe src="'+json_strings.uri.base+'app/includes/actions.log.export.php?format=csv"></iframe>');

					return false;
				}

				/** MANAGE FILES ACTIONS */
				if (action == 'unassign') {
                    _formatted = sprintf(json_strings.translations.confirm_unassign, checks.length);
					if (confirm(_formatted)) {
						return true;
					} else {
						return false;
					}
				}

				/** TEMPLATES */
				if (action == 'zip') {

					var checkboxes = $("td>input:checkbox").serializeArray();

					$(document).psendmodal();

					Cookies.set('download_started', 0, { expires: 100 });
					setTimeout(check_download_cookie, 1000);
					$('.modal_content').html('<p class="loading-img"><img src="'+json_strings.uri.assets_img+'ajax-loader.gif" alt="Loading" /></p>'+
												'<p class="lead text-center text-info">'+json_strings.translations.download_wait+'</p>'+
												'<p class="text-center text-info">'+json_strings.translations.download_long_wait+'</p>'
											);

					$.ajax({
						method: 'GET',
						url: json_strings.uri.base + 'process.php',
						data: { do:"download_zip", files:checkboxes }
					}).done( function(rsp) {
						var url = json_strings.uri.base + 'app/includes/download.zip.process.php?ids=' + rsp;
						$('.modal_content').append("<iframe id='modal_zip'></iframe>");
						$('#modal_zip').attr('src', url);
					});
					return false;
				}
		}
		else {
			return false;
		}
	});

	/**
     * CLOSE THE ZIP DOWNLOAD MODAL
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

	/** CLOSE THE LOG CSV DOWNLOAD MODAL */
	var logdownloadTimeout;
	var check_log_download_cookie = function() {
		if (Cookies.get("log_download_started") == 1) {
			Cookies.set("log_download_started", "false", { expires: 100 });
			remove_modal();
		} else {
			logdownloadTimeout = setTimeout(check_log_download_cookie, 1000);
		}
	};


    /** EDIT FILE + UPLOAD FORM */
    if ( $.isFunction($.fn.chosen) ) {
        $('.chosen-select').chosen({
            no_results_text	: json_strings.translations.no_results,
            width			: "100%",
            search_contains	: true
        });
    }

    /** CKEditor */
    if ( typeof CKEDITOR !== "undefined" ) {
        CKEDITOR.replaceAll( 'ckeditor' );
    }
});
