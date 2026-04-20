(function () {
    'use strict';

    admin.pages.loginLdapForm = function () {

        $(document).ready(function(){
            var validator = $("#login_ldap_form").validate({
                rules: {
                    ldap_email: {
                        required: true,
                    },
                    ldap_password: {
                        required: true,
                    },
                },
                messages: {
                    ldap_email: {
                        required: json_strings.validation.no_email,
                    },
                    ldap_password: {
                        required: json_strings.validation.no_pass,
                    }
                },
                errorPlacement: function(error, element) {
                    error.appendTo(element.parent('div'));
                },
                submitHandler: function(form) {
                    form.submit();

                    var button_loading_text = json_strings.login.logging_in;
                    $('#ldap_submit').html('<i class="fa fa-cog fa-spin fa-fw"></i><span class="sr-only"></span> '+button_loading_text+'...').attr('disabled', 'disabled');
                }
            });
        });
    };
})();