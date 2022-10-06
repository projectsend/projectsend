(function () {
    'use strict';

    admin.parts.select2 = function () {

        $(document).ready(function(){
            $('.select2').select2({
                width: '100%'
            });

            $('.add-all').on('click', function() {
                var target = $(this).data('target');
                var selector = $('#'+target);
                $(selector).hide();
                $(selector).find('option').each(function() {
                    $(this).prop('selected', true);
                });
                $(selector).trigger('change');
                return false;
            });

            $('.remove-all').on('click', function() {
                var target = $(this).data('target');
                var selector = $('#'+target);
                selector.val(null).trigger('change');
                return false;
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

                    $('.select2[data-type="'+type+'"]').not(selector).each(function() {
                        $(this).find('option').each(function() {
                            val = $(this).val().toString();
                            if (selected.includes(val)) {
                                $(this).prop('selected', 'selected');
                            } else {
                                $(this).removeAttr('selected');
                            }
                        });
                        $(this).trigger('change');
                    });
                }

                return false;
            });
        });
    };
})();