(function () {
    'use strict';

    admin.pages.custom_fields_form = function () {
        function initCustomFieldsForm() {
            var fieldLabel = document.getElementById('field_label');
            var fieldName = document.getElementById('field_name');
            var fieldType = document.getElementById('field_type');
            var fieldOptionsContainer = document.getElementById('field_options_container');
            var form = document.getElementById('custom_field_form');
            var formChanged = false;
            var formSubmitting = false;
            var initialFormData = new FormData(form);

            // Function to create slug from text
            function createSlug(text) {
                return text.toString()
                    .toLowerCase()
                    .trim()
                    .replace(/[\s\-]+/g, '_')          // Replace spaces and hyphens with underscores
                    .replace(/[^\w_]+/g, '')           // Remove all non-word chars except underscores
                    .replace(/\_\_+/g, '_')            // Replace multiple underscores with single underscore
                    .replace(/^_+/, '')                // Trim underscores from start
                    .replace(/_+$/, '');               // Trim underscores from end
            }

            // Auto-generate field_name from field_label on blur (only for add form)
            if (fieldLabel && fieldName && !fieldName.hasAttribute('readonly')) {
                var autoGenerate = true;

                // Function to generate slug
                function generateSlug() {
                    if (autoGenerate && fieldName.value.trim() === '') {
                        var slug = createSlug(fieldLabel.value);
                        fieldName.value = slug;
                    }
                }

                // Generate on blur
                fieldLabel.addEventListener('blur', generateSlug);

                // Also generate on real-time typing if field_name is empty
                //fieldLabel.addEventListener('input', generateSlug);

                // Stop auto-generating once user manually edits field_name
                // But re-enable if field_name is cleared
                fieldName.addEventListener('input', function() {
                    if (fieldName.value.trim() === '') {
                        autoGenerate = true;
                    } else {
                        autoGenerate = false;
                    }
                });
            }

            // Show/hide field options based on field type
            if (fieldType && fieldOptionsContainer) {
                function toggleFieldOptions() {
                    var fieldOptionsHelp = document.getElementById('field_options_help');

                    if (fieldType.value === 'select' || fieldType.value === 'checkbox') {
                        fieldOptionsContainer.style.display = 'block';
                        // Make field_options required when visible (optional for checkbox)
                        var fieldOptions = document.getElementById('field_options');
                        if (fieldOptions) {
                            if (fieldType.value === 'select') {
                                fieldOptions.setAttribute('required', 'required');
                                fieldOptions.setAttribute('rows', '5');
                            } else {
                                // Checkbox label is optional (defaults to "Yes")
                                fieldOptions.removeAttribute('required');
                                fieldOptions.setAttribute('rows', '2');
                            }
                        }

                        // Update help text based on type
                        if (fieldOptionsHelp) {
                            if (fieldType.value === 'select') {
                                fieldOptionsHelp.innerHTML = 'For select fields: Enter one option per line.';
                            } else {
                                fieldOptionsHelp.innerHTML = 'For checkbox fields: Enter the checkbox label (e.g., "I agree to the terms"). Leave empty for default "Yes".';
                            }
                        }
                    } else {
                        fieldOptionsContainer.style.display = 'none';
                        // Remove required when hidden
                        var fieldOptions = document.getElementById('field_options');
                        if (fieldOptions) {
                            fieldOptions.removeAttribute('required');
                        }
                    }
                }

                // Initial check on page load
                toggleFieldOptions();

                // Toggle on field type change
                fieldType.addEventListener('change', toggleFieldOptions);
            }

            // Handle delete confirmation for the delete button in edit form
            var deleteButtons = document.querySelectorAll('.delete-confirm');
            deleteButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    var deleteUrl = this.getAttribute('href');

                    Swal.fire({
                        title: 'Are you sure?',
                        text: "This will permanently delete this custom field and all its data. This action cannot be undone!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel',
                        showClass: {
                            popup: 'animate__animated animate__fadeIn'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__fadeOut'
                        }
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            window.location.href = deleteUrl;
                        }
                    });
                });
            });

            // Track form changes
            if (form) {
                // Track changes to all form inputs
                form.addEventListener('change', function() {
                    formChanged = true;
                });

                // Also track text input changes
                var textInputs = form.querySelectorAll('input[type="text"], input[type="checkbox"], textarea, select');
                textInputs.forEach(function(input) {
                    input.addEventListener('input', function() {
                        // Check if form has actually changed from initial state
                        var currentFormData = new FormData(form);
                        formChanged = !areFormDataEqual(initialFormData, currentFormData);
                    });
                });

                // Set flag when form is being submitted
                form.addEventListener('submit', function() {
                    formSubmitting = true;
                });

                // Helper function to compare FormData objects
                function areFormDataEqual(formData1, formData2) {
                    var entries1 = Array.from(formData1.entries());
                    var entries2 = Array.from(formData2.entries());

                    if (entries1.length !== entries2.length) {
                        return false;
                    }

                    for (var i = 0; i < entries1.length; i++) {
                        if (entries1[i][0] !== entries2[i][0] || entries1[i][1] !== entries2[i][1]) {
                            return false;
                        }
                    }

                    return true;
                }

                // Warn user before leaving if there are unsaved changes
                window.addEventListener('beforeunload', function(e) {
                    if (formChanged && !formSubmitting) {
                        var confirmationMessage = 'You have unsaved changes. Are you sure you want to leave this page?';
                        e.returnValue = confirmationMessage;
                        return confirmationMessage;
                    }
                });

                // Handle navigation away from the page (for links within the application)
                document.addEventListener('click', function(e) {
                    // Check if it's a link that would navigate away
                    var target = e.target.closest('a');
                    if (target && target.href && !target.href.startsWith('#') && !target.classList.contains('delete-confirm')) {
                        if (formChanged && !formSubmitting) {
                            e.preventDefault();
                            var targetUrl = target.href;

                            Swal.fire({
                                title: 'Unsaved Changes',
                                text: 'You have unsaved changes. Are you sure you want to leave this page?',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#d33',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: 'Yes, leave page',
                                cancelButtonText: 'Stay on page',
                                showClass: {
                                    popup: 'animate__animated animate__fadeIn'
                                },
                                hideClass: {
                                    popup: 'animate__animated animate__fadeOut'
                                }
                            }).then(function(result) {
                                if (result.isConfirmed) {
                                    formSubmitting = true; // Prevent the beforeunload warning
                                    window.location.href = targetUrl;
                                }
                            });
                        }
                    }
                });
            }
        }

        // Check if DOM is already loaded or wait for it
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCustomFieldsForm);
        } else {
            initCustomFieldsForm();
        }
    };
})();