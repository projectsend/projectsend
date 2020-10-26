(function () {
    'use strict';

    admin.pages.fileEditor = function () {

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
                    var target = $(this).data('target');
                    var type = $(this).data('type');
                    var selector = $('#'+target);
                    var val;
    
                    var selected = new Array();
                    $(selector).find('option:selected').each(function() {
                        selected.push($(this).val().toString());
                    });

                    $('.chosen-select[data-type="'+type+'"]').not(selector).each(function() {
                        $(this).find('option').each(function() {
                            val = $(this).val().toString();
                            if (selected.includes(val)) {
                                $(this).prop('selected', 'selected');
                            } else {
                                $(this).removeAttr('selected');
                            }
                        });
                        $(this).trigger('chosen:updated');
                    });
                }

                return false;
            });
        });
    };
})();