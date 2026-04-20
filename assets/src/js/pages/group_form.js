(function () {
    'use strict';

    admin.pages.groupForm = function () {
        var form = document.getElementById('group_form');
        var formChanged = false;
        var formSubmitting = false;
        var initialFormData = form ? new FormData(form) : null;

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

        // Track form changes for unsaved changes warning
        if (form) {
            // Track changes to all form inputs
            form.addEventListener('change', function() {
                formChanged = true;
            });

            // Also track text input changes
            var textInputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], input[type="number"], input[type="checkbox"], textarea, select');
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
                if (!formData1 || !formData2) return false;

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
    };
})();