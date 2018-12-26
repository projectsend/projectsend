/**
 * Apply bulk actions
 */
$(document).ready(function(e) {
	$("#do_action").click(function() {
		var checks = $("td>input:checkbox").serializeArray();
		var action = $('#action').val();
		if (action != 'none') {
				// Generic actions
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

				// Activities log actions
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
					$('.modal_content').append('<iframe src="'+json_strings.uri.base+'src/includes/actions.log.export.php?format=csv"></iframe>');

					return false;
				}

				// Manage files actions
				if (action == 'unassign') {
					_formatted = sprintf(json_strings.translations.confirm_unassign, checks.length);
					if (confirm(_formatted)) {
						return true;
					} else {
						return false;
					}
				}

				// Templates
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
						data: { do:"return_files_ids", files:checkboxes }
					}).done( function(rsp) {
						var url = json_strings.uri.base + 'process.php?do=download_zip&files=' + rsp;
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
});
