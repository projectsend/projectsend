(function () {
    'use strict';

    admin.pages.uploadForm = function () {

        $(document).ready(function(){
            var file_ids = [];
            var errors = 0;
            var successful = 0;

            // Send a keep alive action every 1 minute
            setInterval(function(){
                var timestamp = new Date().getTime()
                $.ajax({
                    type:	'GET',
                    cache:	false,
                    url:	json_strings.uri.base + 'includes/ajax-keep-alive.php',
                    data:	'timestamp='+timestamp,
                    success: function(result) {
                        var dummy = result;
                    }
                });
            },1000*60);

            var uploader = $('#uploader').pluploadQueue();

            // Bind event handlers once, outside submit handler to prevent duplicate bindings
            uploader.bind('StateChanged', function() {
                if (uploader.files.length === (uploader.total.uploaded + uploader.total.failed)) {
                    var action = $('#upload_form').attr('action') + '?ids=' + file_ids.toString() + '&type=new';
                    $('#upload_form').attr('action', action);
                    if (successful > 0) {
                        if (errors == 0) {
                            window.location = action;
                        } else {
                            $(`
                                <div class="alert alert-info">`+json_strings.translations.upload_form.some_files_had_errors+`</div>
                                <a class="btn btn-wide btn-primary" href="`+action+`">`+json_strings.translations.upload_form.continue_to_editor+`</a>
                            `).insertBefore( "#upload_form" );
                        }
                        return;
                    }
                }
            });

            uploader.bind('Error', function(uploader, error) {
                errors++;
                var message = '';
                try {
                    var obj = JSON.parse(error.response);
                    if (obj.error && typeof obj.error === 'object') {
                        message = obj.error.filename + ': ' + obj.error.message;
                    } else if (obj.error) {
                        message = obj.error;
                    } else {
                        message = error.message || 'Unknown error';
                    }
                } catch (e) {
                    message = error.message || 'Upload failed';
                }
                $(
                    '<div class="alert alert-danger">' + message + '</div>'
                ).insertBefore("#upload_form");
            });

            uploader.bind('FileUploaded', function (uploader, file, info) {
                var obj = JSON.parse(info.response);
                file_ids.push(obj.info.id);
                successful++;
            });

            $('#upload_form').on('submit', function(e) {
                if (uploader.files.length > 0) {
                    uploader.start();
                    $("#btn-submit").hide();
                    $(".message_uploading").fadeIn();
                    return false;
                } else {
                    alert(json_strings.translations.upload_form.no_files);
                }

                return false;
            });

            window.onbeforeunload = function (e) {
                var e = e || window.event;

                console.log('state? ' + uploader.state);

                // if uploading
                if(uploader.state === 2) {
                    //IE & Firefox
                    if (e) {
                        e.returnValue = json_strings.translations.upload_form.leave_confirm;
                    }

                    // For Safari
                    return json_strings.translations.upload_form.leave_confirm;
                }

            };

        });
    };
})();
