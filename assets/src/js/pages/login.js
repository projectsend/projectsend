(function () {
    'use strict';

    admin.pages.loginForm = function () {

        $(document).ready(function(){
            var initial = $('.seconds_countdown').text();
            if (initial) {
                $('#btn_submit').attr('disabled', 'disabled');
            }
            var downloadTimer = setInterval(function(){
                if (initial <= 0) {
                    clearInterval(downloadTimer);
                    $('#btn_submit').removeAttr('disabled');
                    $('#message_countdown').slideUp();
                }
                $('.seconds_countdown').text(initial);
                initial -= 1;
            }, 1000);

            var validator = $("#login_form").validate({
                rules: {
                    username: {
                        required: true,
                    },
                    password: {
                        required: true,
                    },
                },
                messages: {
                    username: {
                        required: json_strings.validation.no_user,
                    },
                    password: {
                        required: json_strings.validation.no_pass,
                    }
                },
                errorPlacement: function(error, element) {
                    error.appendTo(element.parent('div'));
                },
                submitHandler: function(form) {
                    form.submit();

                    var button_loading_text = json_strings.login.logging_in;
                    $('#btn_submit').html('<i class="fa fa-cog fa-spin fa-fw"></i><span class="sr-only"></span> '+button_loading_text+'...').attr('disabled', 'disabled');
                    /*
                    
                    var button_text = json_strings.login.button_text;
                    var button_redirecting_text = json_strings.login.redirecting;
                    var url = $(form).attr('action');
                    $('.ajax_response').html('').removeClass('alert alert-danger alert-success').slideUp();
                    

                    $.ajax({
                        cache: false,
                        method: 'POST',
                        url: url,
                        data: $(form).serialize(),
                    }).done( function(data) {
                        var obj = JSON.parse(data);
                        if ( obj.status == 'success' ) {
                            $('#submit').html('<i class="fa fa-check"></i><span class="sr-only"></span> '+button_redirecting_text+'...');
                            $('#submit').removeClass('btn-primary').addClass('btn-success');
                            setTimeout('window.location.href = "'+obj.location+'"', 1000);
                        } else {
                            $('.ajax_response').addClass('alert alert-danger').slideDown().html(obj.message);
                            $('#submit').html(button_text).removeAttr('disabled');
                        }
                    }).fail( function(data) {
                        $('.ajax_response').addClass('alert alert-danger').slideDown().html('Uncaught error');
                        $('#submit').html(button_text).removeAttr('disabled');
                    }).always( function() {
                    });

                    return false;
                    */
                }
            });
        });
    };
})();