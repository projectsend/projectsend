(function () {
    'use strict';

    admin.pages.options = function () {

        $(document).ready(function(){
            $('#allowed_file_types')
            .tagify()
            .on('add', function(e, tagName){
                console.log('added', tagName)
            });

            var validator = $("#options").validate({
                errorPlacement: function(error, element) {
                    error.appendTo(element.parent('div'));
                },
            });

            $('#download_method').on('change', function(e) {
                var method = $(this).find('option:selected').val();
                $('.method_note').hide();
                $('.method_note[data-method="'+method+'"]').show();
            });

            $('#download_method').trigger('change');
        });
    };
})();