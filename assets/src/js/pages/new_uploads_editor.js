(function () {
    'use strict';

    admin.pages.newUploadsEditor = function () {

        $(document).ready(function(){
            var validator = $("#files").validate({
                errorPlacement: function(error, element) {
                    error.appendTo(element.parent('div'));
                }
            });

            var file = $('input[name^="file"]');

            file.filter('input[name$="[name]"]').each(function() {
                $(this).rules("add", {
                    required: true,
                    messages: {
                        required: json_strings.validation.no_name
                    }
                });
            });


            $('.copy-all').on('click', function() {
                if ( confirm( json_strings.translations.upload_form.copy_selection ) ) {
                    var type = $(this).data('type');
                    var id = $(this).data('file-id');
                    var selector = $('select[data-type="' + type + '"][data-file-id="'+id+'"]');

                    var selected = new Array();
                    $(selector).find('option:selected').each(function() {
                        selected.push($(this).val());
                    });
                    console.log(selected);

                    $('.chosen-select[data-type="'+type+'"]').each(function() {
                        $(this).find('option').each(function() {
                            if ($.inArray($(this).val(), selected) === -1) {
                                $(this).removeAttr('selected');
                            }
                            else {
                                $(this).attr('selected', 'selected');
                            }
                        });
                    });
                    $('select').trigger('chosen:updated');
                }

                return false;
            });

            // Autoclick the continue button
            //$('#upload-continue').click();

        });
    };
})();