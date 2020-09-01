(function () {
    'use strict';

    admin.pages.uploadForm = function () {

        $(document).ready(function(){

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

            $('#upload_form').submit(function(e) {

                if (uploader.files.length > 0) {
                    uploader.bind('StateChanged', function() {
                        if (uploader.files.length === (uploader.total.uploaded + uploader.total.failed)) {
                            $('#upload_form')[0].submit();
                        }
                    });

                    uploader.start();

                    $("#btn-submit").hide();
                    $(".message_uploading").fadeIn();

                    uploader.bind('FileUploaded', function (up, file, info) {
                        var obj = JSON.parse(info.response);
                        var new_file_field = '<input type="hidden" name="file_ids[]" value="'+obj.id+'" />'
                        // var new_file_field = '<input type="hidden" name="finished_files[]" value="'+obj.NewFileName+'" />'
                        $('#upload_form').append(new_file_field);
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