(function () {
    'use strict';

    admin.pages.clientForm = function () {
        var form = document.getElementById('client_form');
        var formChanged = false;
        var formSubmitting = false;
        var initialFormData = form ? new FormData(form) : null;

        $(document).ready(function(){
            var form_type = $("#client_form").data('form-type');

            var validator = $("#client_form").validate({
                rules: {
                    name: {
                        required: true
                    },
                    username: {
                        required: true,
                        minlength: json_strings.character_limits.user_min,
                        maxlength: json_strings.character_limits.user_max,
                        alphanumericUsername: true
                    },
                    email: {
                        required: true,
                        email: true
                    },
                    max_file_size: {
                        required: {
                            param: true,
                            depends: function(element) {
                                return form_type != 'new_client_self';
                            }
                        },
                        digits: true
                    },
                    password: {
                        required: {
                            param: true,
                            depends: function(element) {
                                if (form_type == 'new_client' || form_type == 'new_client_self') {
                                    return true;
                                }
                                if (form_type == 'edit_client' || form_type == 'edit_client_self') {
                                    if ($.trim($("#password").val()).length > 0) {
                                        return true;
                                    }
                                }
                                return false;
                            }
                        },
                        minlength: json_strings.character_limits.password_min,
                        maxlength: json_strings.character_limits.password_max,
                        passwordValidCharacters: true
                    }
                },
                messages: {
                    name: {
                        required: json_strings.validation.no_name
                    },
                    username: {
                        required: json_strings.validation.no_user,
                        minlength: json_strings.validation.length_user,
                        maxlength: json_strings.validation.length_user,
                        alphanumericUsername: json_strings.validation.alpha_user
                    },
                    email: {
                        required: json_strings.validation.no_email,
                        email: json_strings.validation.invalid_email
                    },
                    max_file_size: {
                        digits: json_strings.validation.file_size
                    },
                    password: {
                        required: json_strings.validation.no_pass,
                        minlength: json_strings.validation.length_pass,
                        maxlength: json_strings.validation.length_pass,
                        passwordValidCharacters: json_strings.validation.alpha_pass
                    }
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