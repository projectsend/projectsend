(function () {
    'use strict';

    admin.parts.misc = function () {

        $(document).ready(function() {
            // Focus the first input on the page. Generally, it's the search box
            $('input:first').focus();

            // Generic confirm alert
            $('.confirm_generic').on('click', function(e) {
                if (!confirm(json_strings.translations.confirm_generic)) {
                    e.preventDefault();
                }
            });
    
            // Dismiss messages
            $('.message .close').on('click', function () {
                $(this).closest('.message').transition('fade');
            });

            // Common for all tables
            $("#select_all").click(function(){
                var status = $(this).prop("checked");
                /** Uncheck all first in case you used pagination */
                $("tr td input[type=checkbox].batch_checkbox").prop("checked",false);
                $("tr:visible td input[type=checkbox].batch_checkbox").prop("checked",status);
            });

            // Loose focus after clicking buttons
            $('button').on('click', function() {
                $(this).blur();
            });

            $('.copy_text').on('click', function(e) {
                let target_id = $(this).data('target');
                let target = document.getElementById(target_id);
                copyTextToClipboard(target.innerHTML);
            });
        });
    };
})();