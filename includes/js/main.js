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

/************************************************************************************************************************************************************************************
EVENT: SCROLL
************************************************************************************************************************************************************************************/
jQuery(window).scroll(function(event) {
});


/************************************************************************************************************************************************************************************
EVENT: RESIZE
************************************************************************************************************************************************************************************/
jQuery(window).resize(function($) {
	prepare_sidebar();
});


/************************************************************************************************************************************************************************************
EVENT: DOCUMENT READY
************************************************************************************************************************************************************************************/
$(document).ready(function() {
	/** Main side menu */
	prepare_sidebar();

   resizeChosen();
   jQuery(window).on('resize', resizeChosen);

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


	$('button').click(function() {
		$(this).blur();
	});
});

var dataExtraction = function(node) {
	if (node.childNodes.length > 1) {
		return node.childNodes[1].innerHTML;
	} else {
		return node.innerHTML;
	}
}
