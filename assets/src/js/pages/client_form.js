(function () {
    'use strict';

    admin.pages.clientForm = function () {

        $(document).ready(function(){
            var form_type = $("#client_form").data('form-type');

            var validator = $("#client_form").validate({
                rules: {
                    name: {
                        required: true
                    },
                    username: {
                        required: true,
                        minlength: json_strings.character_limits.user_min,
                        maxlength: json_strings.character_limits.user_max,
                        alphanumericUsername: true
                    },
                    email: {
                        required: true,
                        email: true
                    },
                    max_file_size: {
                        required: {
                            param: true,
                            depends: function(element) {
                                return form_type != 'new_client_self';
                            }
                        },
                        digits: true
                    },
                    password: {
                        required: {
                            param: true,
                            depends: function(element) {
                                if (form_type == 'new_client' || form_type == 'new_client_self') {
                                    return true;
                                }
                                if (form_type == 'edit_client' || form_type == 'edit_client_self') {
                                    if ($.trim($("#password").val()).length > 0) {
                                        return true;
                                    }
                                }
                                return false;
                            }
                        },
                        minlength: json_strings.character_limits.password_min,
                        maxlength: json_strings.character_limits.password_max,
                        passwordValidCharacters: true
                    }
                },
                messages: {
                    name: {
                        required: json_strings.validation.no_name
                    },
                    username: {
                        required: json_strings.validation.no_user,
                        minlength: json_strings.validation.length_user,
                        maxlength: json_strings.validation.length_user,
                        alphanumericUsername: json_strings.validation.alpha_user
                    },
                    email: {
                        required: json_strings.validation.no_email,
                        email: json_strings.validation.invalid_email
                    },
                    max_file_size: {
                        digits: json_strings.validation.file_size
                    },
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