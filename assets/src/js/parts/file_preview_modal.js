(function () {
    'use strict';

    admin.parts.filePreviewModal = function () {

        $(document).ready(function(e) {
            // Append modal
            var modal_layout = `<div id="preview_modal" class="modal fade" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                        </div>
                        <div class="modal-body">
                        </div>
                        <div class="modal-footer">
                        </div>
                    </div>
                </div>
            </div>`;
            $('body').append(modal_layout);

            // Button trigger
            $('.get-preview').on('click', function(e) {
                e.preventDefault();
                var url = $(this).data("url"); 
                var content = '';

                $.ajax({
                    method: "GET",
                    url: url,
                    cache: false,
                }).done(function(response) {
                    var obj = JSON.parse(response);
                    switch (obj.type) {
                        case 'video':
                            content = `
                                <div class="embed-responsive embed-responsive-16by9">
                                    <video controls>
                                        <source src="`+obj.file_url+`" format="`+obj.mime_type+`">
                                    </video>
                                </div>`;
                            break;
                        case 'audio':
                            content = `
                                <audio controls>
                                    <source src="`+obj.file_url+`" format="`+obj.mime_type+`">
                                </audio>`;
                            break;
                        case 'pdf':
                            content = `
                                <div class="embed-responsive embed-responsive-16by9">
                                    <iframe src="`+obj.file_url+`"></iframe>
                                </div>
                            `;
                            break;
                        case 'image':
                            content = `<img src="`+obj.file_url+`" class="img-responsive">`
                            break;
                        }
                    $('.modal-header h5').html(obj.name);
                    $('.modal-body').html(content);
                    // show modal
                    $('#preview_modal').modal('show');
                }).fail(function(response) {
                    alert(json_strings.translations.preview_failed);
                }).always(function() {
                });    
            });

            // Remove content when closing modal
            $('#preview_modal').on('hidden.bs.modal', function (e) {
                $('.modal-body').html('');
            })
        });
    };
})();