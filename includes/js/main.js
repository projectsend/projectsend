$(document).ready(function() {
	$('button').click(function() {
		$(this).blur();
	});

	$('.btn_generate_password').click(function(e) {
		var hdl = new Jen(true);
		hdl.hardening(true);

		var target = $(this).parents('.form-group').find('input[type=password]');
		var min_chars = $(this).data('min');
		var max_chars = $(this).data('max');
		$(target).val( hdl.password( min_chars, max_chars ) );
	});
});
