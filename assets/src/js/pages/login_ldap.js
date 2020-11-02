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
                    var button_text = json_strings.login.button_text;
                    var button_loading_text = json_strings.login.logging_in;
                    var button_redirecting_text = json_strings.login.redirecting;

                    var url = $(form).attr('action');
                    $('.ajax_response').html('').removeClass('alert-danger alert-success').slideUp();
                    $('#ldap_submit').html('<i class="fa fa-cog fa-spin fa-fw"></i><span class="sr-only"></span> '+button_loading_text+'...');
                    $.ajax({
                        cache: false,
                        type: "post",
                        url: url,
                        data: $(form).serialize(),
                        success: function(response)
                        {
                            var json = jQuery.parseJSON(response);
                            if ( json.status == 'success' ) {
                                $('#ldap_submit').html('<i class="fa fa-check"></i><span class="sr-only"></span> '+button_redirecting_text+'...');
                                $('#ldap_submit').removeClass('btn-primary').addClass('btn-success');
                                setTimeout('window.location.href = "'+json.location+'"', 1000);
                            }
                            else {
                                $('.ajax_response').addClass('alert-danger').slideDown().html(json.message);
                                $('#ldap_submit').html("'"+button_text+"'");
                            }
                        }
                    });

                    return false;
                }
            });
        });
    };
})();