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
        });
    };
})();