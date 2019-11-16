(function () {
    'use strict';

    admin.pages.resetPasswordEnterNew = function () {

        $(document).ready(function(){
            var validator = $("#reset_password_enter_new").validate({
                rules: {
                    password: {
                        required: true,
                        minlength: json_strings.character_limits.password_min,
                        maxlength: json_strings.character_limits.password_max,
                        passwordValidCharacters: true
                    }
                },
                messages: {
                    password: {
                        required: json_strings.validation.no_pass,
                        minlength: json_strings.validation.length_pass,
                        maxlength: json_strings.validation.length_pass,
                        passwordValidCharacters: json_strings.validation.alpha_pass
                    }
                },
                errorPlacement: function(error, element) {
                    error.appendTo(element.parent('div'));
                }
            });
        });
    };
})();