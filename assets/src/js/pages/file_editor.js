(function () {
    'use strict';

    admin.pages.fileEditor = function () {

        $(document).ready(function(){
            // Datepicker
            if ( $.isFunction($.fn.datepicker) ) {
                $('.date-container .date-field').datepicker({
                    format : 'dd-mm-yyyy',
                    autoclose : true,
                    todayHighlight : true
                });
            }

            // Validation
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

            // Copy settings to other files
            function copySettingsToCheckboxes(el, to, question)
            {
                if ( confirm( question ) ) {
                    $(to).each(function(i, obj) {
                        var from_element = document.getElementById($(el).data('copy-from'));
                        $(this).prop('checked', from_element.checked);
                    });
                }
            }

            $('.copy-expiration-settings').on('click', function() {
                copySettingsToCheckboxes($(this), '.checkbox_setting_expires', json_strings.translations.upload_form.copy_expiration);
                // Copy date
                var element = $('#'+$(this).data('copy-date-from'));
                var date = element.val();
                $('.date-field').each(function(i, obj) {
                    console.log(date);
                    $('.date-field').datepicker('update', date);
                });
            });

            $('.copy-public-settings').on('click', function() {
                copySettingsToCheckboxes($(this), '.checkbox_setting_public', json_strings.translations.upload_form.copy_public);
            });

            $('.copy-hidden-settings').on('click', function() {
                copySettingsToCheckboxes($(this), '.checkbox_setting_hidden', json_strings.translations.upload_form.copy_hidden);
            });

            // Collapse - expand single item
            $('.toggle_file_editor').on('click', function(e) {
                let wrapper = $(this).parents('.file_editor_wrapper');
                wrapper.toggleClass('collapsed');
            });

            // Collapse all
            document.getElementById('files_collapse_all').addEventListener('click', function(e) {
                let wrappers = document.querySelectorAll('.file_editor_wrapper');
                wrappers.forEach(wrapper => {
                    wrapper.classList.add('collapsed');
                });
                    
            })

            // Expand all
            document.getElementById('files_expand_all').addEventListener('click', function(e) {
                let wrappers = document.querySelectorAll('.file_editor_wrapper');
                wrappers.forEach(wrapper => {
                    wrapper.classList.remove('collapsed');
                });
                    
            })
        });
    };
})();