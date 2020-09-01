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
    var reg = /^(([^<>()\[\]\.,;:\s@\"]+(\.[^<>()\[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
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
