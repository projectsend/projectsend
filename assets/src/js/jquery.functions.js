/**
 * These functions are temporarily separated since they read strings
 * from the json object and need testing.
 */
$(document).ready(function(e) {
	/**
     * Modal: show a public file's URL
     */
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

    // Close the log CSV download modal
	var logdownloadTimeout;
	var check_log_download_cookie = function() {
		if (Cookies.get("log_download_started") == 1) {
			Cookies.set("log_download_started", "false", { expires: 100 });
			remove_modal();
		} else {
			logdownloadTimeout = setTimeout(check_log_download_cookie, 1000);
		}
	};

    // Edit file + upload form
    if ( $.isFunction($.fn.chosen) ) {
        $('.chosen-select').chosen({
            no_results_text	: json_strings.translations.no_results,
            width			: "100%",
            search_contains	: true
        });
    }

    // CKEditor
    if ( typeof CKEDITOR !== "undefined" ) {
        CKEDITOR.replaceAll( 'ckeditor' );
    }
});
