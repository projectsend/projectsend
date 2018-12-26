var dataExtraction = function(node) {
	if (node.childNodes.length > 1) {
		return node.childNodes[1].innerHTML;
	} else {
		return node.innerHTML;
	}
}

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

/**
 * Backend functions
 */
function resizeChosen() {
    $(".chosen-container").each(function() {
        $(this).attr('style', 'width: 100%');
    });
 }
 
 function prepare_sidebar() {
     var window_width = jQuery(window).width();
     if ( window_width < 769 ) {
         $('.main_menu .active .dropdown_content').hide();
         $('.main_menu li').removeClass('active');
 
         if ( !$('body').hasClass('menu_contracted') ) {
             $('body').addClass('menu_contracted');
         }
     }
 }

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


/**
 * Very simple custom modal dialog
 */
$.fn.psendmodal = function() {
	var modal_structure = '<div class="modal_overlay"></div>'+
							'<div class="modal_psend">'+
								'<div class="modal_title">'+
									'<span>&nbsp;</span>'+
									'<a href="#" class="modal_close">&times;</a>'+
								'</div>'+
								'<div class="modal_content"></div>'+
							'</div>';

	$('body').append(modal_structure);
	show_modal();

	function show_modal() {
		$('.modal_overlay').stop(true, true).fadeIn();
		$('.modal_psend').stop(true, true).fadeIn();
	}

	window.remove_modal = function() {
		$('.modal_overlay').stop(true, true).fadeOut(500, function() { $(this).remove(); });
		$('.modal_psend').stop(true, true).fadeOut(500, function() { $(this).remove(); });
		return false;
	}

	$(".modal_close").click(function(e) {
		e.preventDefault();
		remove_modal();
	});

	$(".modal_overlay").click(function(e) {
		e.preventDefault();
		remove_modal();
	});

	$(document).keyup(function(e) {
		if (e.keyCode == 27) { // Esc
			remove_modal();
		}
	});
};


// ProjectSend News
function ajax_widget_news() {
    var target = $('.widget_news');
    target.html('<div class="loading-graph">'+
                    '<img src="'+json_strings.uri.assets_img+'ajax-loader.gif" alt="Loading" />'+
                '</div>'
            );
    $.ajax({
        url: json_strings.uri.widgets+'news.php',
        data: { ajax_call:true },
        success: function(response){
                    target.html(response);
                },
        cache:false
    });
}

// Statistics
function ajax_widget_statistics( days ) {
    var target = $('.statistics_graph');
    target.html('<div class="loading-graph">'+
                    '<img src="'+json_strings.uri.assets_img+'ajax-loader.gif" alt="Loading" />'+
                '</div>'
            );
    $.ajax({
        url: json_strings.uri.widgets+'statistics.php',
        data: { days:days, ajax_call:true },
        success: function(response){
                    target.html(response);
                },
        cache:false
    });
}

// Action log
function ajax_widget_log( action ) {
    var target = $('.activities_log');
    target.html('<div class="loading-graph">'+
                    '<img src="'+json_strings.uri.assets_img+'ajax-loader.gif" alt="Loading" />'+
                '</div>'
            );
    $.ajax({
        url: json_strings.uri.widgets+'actions-log.php',
        data: { action:action, ajax_call:true },
        success: function(response){
                    target.html(response);
                },
        cache:false
    });
}

/** AJAX calls */
$(document).ready(function(){
	/** Click events */

	// Statistics
	$('.stats_days').click(function(e) {
		e.preventDefault();

		if ($(this).hasClass('btn-inverse')) {
			return false;
		}
		else {
			var days = $(this).data('days');
			$('.stats_days').removeClass('btn-inverse');
			$(this).addClass('btn-inverse');
			ajax_widget_statistics(days);
		}
	});

	// Action log
	$('.log_action').click(function(e) {
		e.preventDefault();

		if ($(this).hasClass('btn-inverse')) {
			return false;
		}
		else {
			var action = $(this).data('action');
			$('.log_action').removeClass('btn-inverse');
			$(this).addClass('btn-inverse');
			ajax_widget_log(action);
		}
	});
});


/**
 * Javascript form validations
 */
var error_count = 0;
var error_count_options = 0;
var ignore_columns;

$(document).ready(function() {
	$('input:first').focus();
});

function clean_form(this_form) {
	$(this_form).find(':input').each(function() {
		if($(this).hasClass('field_error')) {
			$(this).removeClass('field_error');
		}
	});
	$(this_form).find('.validation_error_group').each(function() {
		$(this).remove();
	});
}

function is_complete_all_options(this_form,error) {
	var error_count_options = 0;
	$(this_form).find(':input:not(.empty)').each(function() {
		if ( $(this).attr('id') == 'allowed_file_types_tag' ) {
			// Exclude every Textboxlist generated input
		}
		else {
			if ($(this).val().length == 0) {
				$(this).addClass('field_error');
				error_count_options++;
			}
		}
	});
	if(error_count_options > 0) {
		error_count++;
	}
}

function add_error_to_field(field, error) {
	error_count++;
	$(field).addClass('field_error');
	var this_field_name = $(field).attr('name');
	this_field_msg_name = this_field_name.replace(/\[/g,'_');
	this_field_msg_name = this_field_msg_name.replace(/\]/g,'_');
	if ($('#error_for_'+this_field_name).length == 0) {
		var location = $(field).parents('.form-group');

		var classes = 'field_error_msg';

		if ( ignore_columns == null ) {
			classes = classes + ' col-sm-8 col-sm-offset-4';
		}

		$(location).after('<div class="form-group validation_error_group"><div class="' + classes + '" id="error_for_'+this_field_msg_name+'"><ul></ul></div></div>');
	}
	$('#error_for_'+this_field_msg_name+' ul').append('<li><i class="glyphicon glyphicon-exclamation-sign"></i> '+error+'</li>');
}

function is_complete(field,error) {
	if ($(field).val().length == 0) {
		add_error_to_field(field, error);
	}
}

function is_selected(field,error) {
	if ($(field).val() == 'ps_empty_value') {
		add_error_to_field(field, error);
	}
}

function is_length(field,minsize,maxsize,error) {
	if ($(field).val().length < minsize || $(field).val().length > maxsize) {
		add_error_to_field(field, error);
	}
}

function is_email(field,error) {
	var reg = /^([^@])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,})$/;
	var address = field.value;
	if (reg.test(address) == false) {
		add_error_to_field(field, error);
	}
}

function is_alpha(field,error) {
	var checkme = field.value;
	if (!(checkme.match(/^[a-zA-Z0-9]+$/))) {
		add_error_to_field(field, error);
	}
}

function is_number(field,error) {
	var checkme = field.value;
	if (!(checkme.match(/^[0-9]+$/))) {
		add_error_to_field(field, error);
	}
}

function is_alpha_or_dot(field,error) {
	var checkme = field.value;
	if (!(checkme.match(/^[a-zA-Z0-9.]+$/))) {
		add_error_to_field(field, error);
	}
}

function is_password(field,error) {
	var checkme = field.value;
	if (!(checkme.match(/^[0-9a-zA-Z`!"?$%\^&*()_\-+={\[}\]:;@~#|<,>.'\/\\]+$/))) {
		add_error_to_field(field, error);
	}
}

function is_match(field,field2,error) {
	if ($(field).val() != $(field2).val()) {
		add_error_to_field(field, error);
		add_error_to_field(field2, error);
	}
}

function show_form_errors() {
	if (error_count > 0) {
		error_count = 0;
		return false;
	}
}

/**
 * Adapted from http://jsfiddle.net/Ngtp7/2/
 */
$(function () {
  $(".password_toggle").each(function (index, input) {
    var $input		= $(input);
    var $container	= $($input).next('.password_toggle');
    $(".password_toggler button").click(function () {
      var change = "";
	  var icon = $(this).find('i');
      if ($(this).hasClass('pass_toggler_show')) {
        $(this).removeClass('pass_toggler_show');
        $(this).addClass('pass_toggler_hide');
        $(icon).removeClass('glyphicon glyphicon-eye-open');
        $(icon).addClass('glyphicon glyphicon-eye-close');
        change = "text";
      } else {
        $(this).removeClass('pass_toggler_hide');
        $(this).addClass('pass_toggler_show');
        $(icon).removeClass('glyphicon glyphicon-eye-close');
        $(icon).addClass('glyphicon glyphicon-eye-open');
        change = "password";
      }
      var rep = $("<input type='" + change + "' />")
        .attr("id", $input.attr("id"))
        .attr("name", $input.attr("name"))
        .attr('class', $input.attr('class'))
        .attr('maxlength', $input.attr('maxlength'))
        .val($input.val())
        .insertBefore($input);
      $input.remove();
      $input = rep;
    }).insertBefore($container);
  });
  return false;
});


/**
 * Event: Doccument ready
 */
$(document).ready(function() {
	/** Main side menu */
	prepare_sidebar();

   resizeChosen();

	$('.main_menu > li.has_dropdown .nav_top_level').click(function(e) {
		e.preventDefault();

		var parent = $(this).parents('.has_dropdown');
		if ( $(parent).hasClass('active') ) {
			$(parent).removeClass('active');
			$(parent).find('.dropdown_content').stop().slideUp();
		}
		else {
			if ( $('body').hasClass('menu_contracted') ) {
				$('.main_menu li').removeClass('active');
				$('.main_menu').find('.dropdown_content').stop().slideUp(100);
			}
			$(parent).addClass('active');
			$(parent).find('.dropdown_content').stop().slideDown();
		}
	});

	$('.toggle_main_menu').click(function(e) {
		e.preventDefault();

		var window_width = jQuery(window).width();
		if ( window_width > 768 ) {
			$('body').toggleClass('menu_contracted');
			if ( $('body').hasClass('menu_contracted') ) {
				Cookies.set("menu_contracted", 'true', { expires: 365 } );
				$('.main_menu li').removeClass('active');
				$('.main_menu').find('.dropdown_content').stop().hide();
			}
			else {
				Cookies.set("menu_contracted", 'false', { expires: 365 } );
				$('.current_nav').addClass('active');
				$('.current_nav').find('.dropdown_content').stop().show();
			}
		}
		else {
			$('body').toggleClass('menu_hidden');
			$('.main_menu li').removeClass('active');

			if ( $('body').hasClass('menu_hidden') ) {
				//Cookies.set("menu_hidden", 'true', { expires: 365 } );
				$('.main_menu').find('.dropdown_content').stop().hide();
			}
			else {
				//Cookies.set("menu_hidden", 'false', { expires: 365 } );
			}
		}
	});

	/** Used on the public link modal on both manage files and the upload results */
	$(document).on('click', '.public_link_copy', function(e) {
		$(this).select();
		if ( document.execCommand("copy") ) {
			var copied = '.copied';
		}
		else {
			var copied = '.copied_not';
		}
		$(this).parents('.public_link_modal').find(copied).stop().fadeIn().delay(2000).fadeOut();
		$(this).mouseup(function() {
			$(this).unbind("mouseup");
			return false;
		});
	});


	/** Common for all tables */
	$("#select_all").click(function(){
		var status = $(this).prop("checked");
		/** Uncheck all first in case you used pagination */
		$("tr td input[type=checkbox].batch_checkbox").prop("checked",false);
		$("tr:visible td input[type=checkbox].batch_checkbox").prop("checked",status);
	});

	if ( $.isFunction($.fn.footable) ) {
		$('.footable').footable().find('> tbody > tr:not(.footable-row-detail):nth-child(even)').addClass('odd');
	}


	/** Password generator */
	var hdl = new Jen(true);
	hdl.hardening(true);

	$('.btn_generate_password').click(function(e) {
		var target = $(this).parents('.form-group').find('.password_toggle');
		var min_chars = $(this).data('min');
		var max_chars = $(this).data('max');
		$(target).val( hdl.password( min_chars, max_chars ) );
	});


	/** File editor */
	if ( $.isFunction($.fn.datepicker) ) {
		$('.date-container .date-field').datepicker({
			format			: 'dd-mm-yyyy',
			autoclose		: true,
			todayHighlight	: true
		});
	}

	$('.add-all').click(function(){
		var type = $(this).data('type');
		var selector = $(this).closest('.' + type).find('select');
		$(selector).find('option').each(function(){
			$(this).prop('selected', true);
		});
		$(selector).trigger('chosen:updated');
		return false;
	});

	$('.remove-all').click(function(){
		var type = $(this).data('type');
		var selector = $(this).closest('.' + type).find('select');
		$(selector).find('option').each(function(){
			$(this).prop('selected', false);
		});
		$(selector).trigger('chosen:updated');
		return false;
	});


	/** Misc */
	$('button').click(function() {
		$(this).blur();
	});

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

/**
 * Event: Scroll
 */
jQuery(window).scroll(function(event) {
});

/**
 * Event: Resize
 */
jQuery(window).resize(function($) {
	prepare_sidebar();

    resizeChosen();
});
