(function () {
    'use strict';

    admin.pages.groupForm = function () {

        $(document).ready(function(){
            var validator = $("#group_form").validate({
                rules: {
                    name: {
                        required: true
                    },
                },
                messages: {
                    name: {
                        required: json_strings.validation.no_name
                    },
                },
                errorPlacement: function(error, element) {
                    error.appendTo(element.parent('div'));
                }
            });
        });
    };
})();