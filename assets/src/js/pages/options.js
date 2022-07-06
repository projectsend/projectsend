(function () {
    'use strict';

    admin.pages.options = function () {
        var tagifyContainer = document.getElementById('allowed_file_types');
        var tagify = new Tagify (tagifyContainer);
        //tagifyContainer.addEventListener('change', tagifyOnChange)

        function tagifyOnChange(e){
            console.log(e.target.value)
        }

        $(document).ready(function(){
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