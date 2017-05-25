// VERY Simple Modal

$.fn.psendmodal = function() {
	$('body').append('<div class="modal_overlay"></div><div class="modal_psend"><div class="modal_title"><a href="#" class="modal_close">&times;</a></div><div class="modal_content"></div></div>');
	show_modal();

	function show_modal() {
		$('.modal_overlay').stop(true, true).fadeIn();
		$('.modal_psend').stop(true, true).fadeIn();
	}

	window.remove_modal = function() {
		$('.modal_overlay').stop(true, true).fadeOut();
		$('.modal_psend').stop(true, true).fadeOut();
		$('.modal_overlay').remove();
		$('.modal_psend').remove();
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