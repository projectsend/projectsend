(function () {
    'use strict';

    admin.pages.customFieldForm = function () {
        document.addEventListener('DOMContentLoaded', function() {
            const fieldTypeSelect = document.getElementById('field_type');
            const fieldOptionsContainer = document.getElementById('field_options_container');
            const fieldOptionsTextarea = document.getElementById('field_options');
            const fieldNameInput = document.getElementById('field_name');
            const fieldLabelInput = document.getElementById('field_label');

            // Show/hide field options based on field type
            function toggleFieldOptions() {
                if (fieldTypeSelect.value === 'select') {
                    fieldOptionsContainer.style.display = 'block';
                    fieldOptionsTextarea.setAttribute('required', 'required');
                } else {
                    fieldOptionsContainer.style.display = 'none';
                    fieldOptionsTextarea.removeAttribute('required');
                }
            }

            // Auto-generate field name from label
            function generateFieldName() {
                const label = fieldLabelInput.value.toLowerCase();
                const fieldName = label
                    .replace(/[^\w\s]/g, '') // Remove special characters
                    .replace(/\s+/g, '_') // Replace spaces with underscores
                    .substring(0, 50); // Limit length

                if (fieldName && !fieldNameInput.value) {
                    fieldNameInput.value = fieldName;
                }
            }

            // Validate field name format
            function validateFieldName() {
                const fieldName = fieldNameInput.value;
                const validPattern = /^[a-z0-9_]+$/;

                if (fieldName && !validPattern.test(fieldName)) {
                    fieldNameInput.setCustomValidity('Field name can only contain lowercase letters, numbers, and underscores.');
                } else {
                    fieldNameInput.setCustomValidity('');
                }
            }

            // Event listeners
            if (fieldTypeSelect) {
                fieldTypeSelect.addEventListener('change', toggleFieldOptions);
                // Initialize on page load
                toggleFieldOptions();
            }

            if (fieldLabelInput) {
                fieldLabelInput.addEventListener('blur', generateFieldName);
            }

            if (fieldNameInput) {
                fieldNameInput.addEventListener('input', validateFieldName);
                // Convert to lowercase and replace spaces with underscores as user types
                fieldNameInput.addEventListener('input', function() {
                    const cursorPosition = this.selectionStart;
                    const originalValue = this.value;
                    const newValue = originalValue.toLowerCase().replace(/\s+/g, '_');

                    if (originalValue !== newValue) {
                        this.value = newValue;
                        this.setSelectionRange(cursorPosition, cursorPosition);
                    }
                });
            }
        });
    };
})();