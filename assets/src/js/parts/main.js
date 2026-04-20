(function () {
    'use strict';

    admin.parts.main = function () {

        $(document).ready(function() {
            // Add asterisks to labels for required fields
            $('.form-control.required, .form-select[required], input.required').each(function() {
                var $field = $(this);
                var $formGroup = $field.closest('.form-group');

                if ($formGroup.length) {
                    var $label = $formGroup.find('label').first();

                    // Check if asterisk already exists to avoid duplicates
                    if ($label.length && !$label.find('.required-indicator').length) {
                        $label.append('<span class="required-indicator" aria-label="required">*</span>');
                    }
                }
            });
        });
    };
})();