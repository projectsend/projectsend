(function () {
    'use strict';

    admin.parts.generatePassword = function () {
        $(document).ready(function () {
            var hdl = new Jen(true);
            hdl.hardening(true);

            $('.btn_generate_password').click(function(e) {
                var target = $(this).parents('.form-group').find('.attach_password_toggler');
                var min_chars = $(this).data('min');
                var max_chars = $(this).data('max');
                $(target).val( hdl.password( min_chars, max_chars ) );
            });
        });
    };
})();