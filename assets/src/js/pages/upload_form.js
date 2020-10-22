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
                    url:	'includes/ajax-keep-alive.php',
                    data:	'timestamp='+timestamp,
                    success: function(result) {
                        var dummy = result;
                    }
                });
            },1000*60);

            var uploader = $('#uploader').pluploadQueue();

            $('#upload_form').on('submit', function(e) {
                if (uploader.files.length > 0) {
                    uploader.bind('StateChanged', function() {
                        if (uploader.files.length === (uploader.total.uploaded + uploader.total.failed)) {
                            var action = $('#upload_form').attr('action') + '?ids=' + file_ids.toString();
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
                            // $('#upload_form')[0].submit();
                        }
                    });

                    uploader.start();

                    $("#btn-submit").hide();
                    $(".message_uploading").fadeIn();

                    uploader.bind('Error', function(uploader, error) {
                        var obj = JSON.parse(error.response);
                        $(
                            `<div class="alert alert-danger">`+obj.error.filename+`: `+obj.error.message+`</div>`
                        ).insertBefore( "#upload_form" );
                        //console.log(obj);
                    });
        
                    uploader.bind('FileUploaded', function (uploader, file, info) {
                        var obj = JSON.parse(info.response);
                        file_ids.push(obj.info.id);
                        successful++;
                    });

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