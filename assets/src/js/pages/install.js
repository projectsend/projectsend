(function () {
    'use strict';

    admin.pages.install = function () {

        $(document).ready(function(){
            var validator = $("#install_form").validate({
                rules: {
                    install_title: {
                        required: true,
                    },
                    base_uri: {
                        required: true
                        // url: true // Does not work on localhost
                    },
                    admin_name: {
                        required: true,
                    },
                    admin_email: {
                        required: true,
                        email: true
                    },
                    admin_username: {
                        required: true,
                        minlength: json_strings.character_limits.user_min,
                        maxlength: json_strings.character_limits.user_max,
                        alphanumericUsername: true
                    },
                    admin_pass: {
                        required: true,
                        minlength: json_strings.character_limits.password_min,
                        maxlength: json_strings.character_limits.password_max,
                        passwordValidCharacters: true
                    },
                },
                messages: {
                    category_name: {
                        required: json_strings.validation.no_name,
                    }
                },
                errorPlacement: function(error, element) {
                    error.appendTo(element.parent('div'));
                },
            });
        });
    };
})();