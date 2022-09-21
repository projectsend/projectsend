(function () {
    'use strict';

    admin.pages.publicFilesList = function () {

        $(document).ready(function(){
            $('select[name="group"]').on('change', function(e) {
                var token = $(this).find(':selected').data('token');
                $('input[name="token"]').val(token);
            });
        });
    };
})();