(function () {
    'use strict';

    admin.pages.fileEditor = function () {
        var form = document.getElementById('files');
        var formChanged = false;
        var formSubmitting = false;
        var initialFormData = form ? new FormData(form) : null;

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

            // Download limit settings toggle
            $('.checkbox_download_limit_enabled').on('change', function() {
                var settings = $(this).closest('.file_data').find('.download_limit_settings');
                if ($(this).is(':checked')) {
                    settings.slideDown();
                } else {
                    settings.slideUp();
                }
            });

            // Copy download limit settings (legacy - kept for backwards compatibility)
            $('.copy-download-limit-settings').on('click', function() {
                if (confirm(json_strings.translations.upload_form.copy_download_limits || 'Apply these download limit settings to all files?')) {
                    var from_element = document.getElementById($(this).data('copy-from'));
                    var from_wrapper = $(from_element).closest('.file_data');

                    // Copy enabled checkbox state
                    $('.checkbox_download_limit_enabled').each(function(i, obj) {
                        $(this).prop('checked', from_element.checked);
                        // Show/hide settings based on checkbox
                        var settings = $(this).closest('.file_data').find('.download_limit_settings');
                        if (from_element.checked) {
                            settings.show();
                        } else {
                            settings.hide();
                        }
                    });

                    // Copy limit type
                    var from_type = from_wrapper.find('input[type="radio"]:checked').val();
                    $('input[name$="[download_limit_type]"]').each(function() {
                        if ($(this).val() === from_type) {
                            $(this).prop('checked', true);
                        }
                    });

                    // Copy limit count
                    var from_count = from_wrapper.find('input[type="number"]').val();
                    $('input[name$="[download_limit_count]"]').val(from_count);
                }
            });

            // ===== BULK ACTIONS PANEL =====

            // Toggle bulk actions panel
            $('#toggleBulkActions, .bulk-actions-header').on('click', function(e) {
                if ($(e.target).is('select') || $(e.target).is('button:not(#toggleBulkActions)')) {
                    return; // Don't toggle if clicking on controls
                }
                $('#bulkActionsPanel').toggleClass('collapsed');
            });

            // Helper function to get source file index
            function getSourceFileIndex(selectId) {
                var sourceIndex = $('#' + selectId).val();
                if (!sourceIndex) {
                    alert('Please select a source file first.');
                    return null;
                }
                return parseInt(sourceIndex);
            }

            // Helper function to get file editor wrapper by index
            function getFileWrapperByIndex(index) {
                return $('.file_editor_wrapper').eq(index - 1);
            }

            // Copy all settings from selected file
            $('#bulkCopyAllSettings').on('click', function() {
                var sourceIndex = getSourceFileIndex('bulkCopySourceFile');
                if (!sourceIndex) return;

                if (!confirm('Copy all settings from the selected file to all other files?')) {
                    return;
                }

                var sourceWrapper = getFileWrapperByIndex(sourceIndex);

                // Copy expiration
                bulkCopyExpiration(sourceWrapper);

                // Copy download limits
                bulkCopyDownloadLimits(sourceWrapper);

                // Copy public settings
                bulkCopyPublic(sourceWrapper);

                // Copy assignments
                bulkCopyClients(sourceWrapper);
                bulkCopyGroups(sourceWrapper);
                bulkCopyHidden(sourceWrapper);

                // Copy organization
                bulkCopyCategories(sourceWrapper);
                bulkCopyFolder(sourceWrapper);

                alert('All settings copied successfully!');
            });

            // Individual bulk copy functions
            function bulkCopyExpiration(sourceWrapper) {
                var sourceCheckbox = sourceWrapper.find('.checkbox_setting_expires');
                var sourceDateField = sourceWrapper.find('.date-field');

                if (sourceCheckbox.length) {
                    var isChecked = sourceCheckbox.is(':checked');
                    var dateValue = sourceDateField.val();

                    $('.checkbox_setting_expires').prop('checked', isChecked);
                    $('.date-field').datepicker('update', dateValue);
                }
            }

            function bulkCopyDownloadLimits(sourceWrapper) {
                var sourceCheckbox = sourceWrapper.find('.checkbox_download_limit_enabled');

                if (sourceCheckbox.length) {
                    var isEnabled = sourceCheckbox.is(':checked');
                    var limitType = sourceWrapper.find('input[name$="[download_limit_type]"]:checked').val();
                    var limitCount = sourceWrapper.find('input[name$="[download_limit_count]"]').val();

                    $('.checkbox_download_limit_enabled').prop('checked', isEnabled).trigger('change');

                    $('input[name$="[download_limit_type]"]').each(function() {
                        if ($(this).val() === limitType) {
                            $(this).prop('checked', true);
                        }
                    });

                    $('input[name$="[download_limit_count]"]').val(limitCount);
                }
            }

            function bulkCopyPublic(sourceWrapper) {
                var sourceCheckbox = sourceWrapper.find('.checkbox_setting_public');

                if (sourceCheckbox.length) {
                    var isChecked = sourceCheckbox.is(':checked');
                    $('.checkbox_setting_public').prop('checked', isChecked);
                }
            }

            function bulkCopyClients(sourceWrapper) {
                var sourceSelect = sourceWrapper.find('.assignments_clients');

                if (sourceSelect.length) {
                    var selectedValues = sourceSelect.val() || [];

                    $('.assignments_clients').each(function() {
                        $(this).val(selectedValues).trigger('change');
                    });
                }
            }

            function bulkCopyGroups(sourceWrapper) {
                var sourceSelect = sourceWrapper.find('.assignments_groups');

                if (sourceSelect.length) {
                    var selectedValues = sourceSelect.val() || [];

                    $('.assignments_groups').each(function() {
                        $(this).val(selectedValues).trigger('change');
                    });
                }
            }

            function bulkCopyHidden(sourceWrapper) {
                var sourceCheckbox = sourceWrapper.find('.checkbox_setting_hidden');

                if (sourceCheckbox.length) {
                    var isChecked = sourceCheckbox.is(':checked');
                    $('.checkbox_setting_hidden').prop('checked', isChecked);
                }
            }

            function bulkCopyCategories(sourceWrapper) {
                var sourceSelect = sourceWrapper.find('select[name$="[categories][]"]');

                if (sourceSelect.length) {
                    var selectedValues = sourceSelect.val() || [];

                    $('select[name$="[categories][]"]').each(function() {
                        $(this).val(selectedValues).trigger('change');
                    });
                }
            }

            function bulkCopyFolder(sourceWrapper) {
                var sourceSelect = sourceWrapper.find('select[name$="[folder_id]"]');

                if (sourceSelect.length) {
                    var selectedValue = sourceSelect.val();

                    $('select[name$="[folder_id]"]').each(function() {
                        $(this).val(selectedValue).trigger('change');
                    });
                }
            }

            // Individual bulk action buttons
            $('.bulk-copy-expiration').on('click', function() {
                var sourceIndex = getSourceFileIndex('bulkExpirationSource');
                if (!sourceIndex) return;

                if (confirm('Copy expiration settings from the selected file to all other files?')) {
                    bulkCopyExpiration(getFileWrapperByIndex(sourceIndex));
                    alert('Expiration settings copied!');
                }
            });

            $('.bulk-copy-download-limits').on('click', function() {
                var sourceIndex = getSourceFileIndex('bulkDownloadLimitSource');
                if (!sourceIndex) return;

                if (confirm('Copy download limit settings from the selected file to all other files?')) {
                    bulkCopyDownloadLimits(getFileWrapperByIndex(sourceIndex));
                    alert('Download limit settings copied!');
                }
            });

            $('.bulk-copy-public').on('click', function() {
                var sourceIndex = getSourceFileIndex('bulkVisibilitySource');
                if (!sourceIndex) return;

                if (confirm('Copy public visibility settings from the selected file to all other files?')) {
                    bulkCopyPublic(getFileWrapperByIndex(sourceIndex));
                    alert('Visibility settings copied!');
                }
            });

            $('.bulk-copy-clients').on('click', function() {
                var sourceIndex = getSourceFileIndex('bulkAssignmentSource');
                if (!sourceIndex) return;

                if (confirm('Copy client assignments from the selected file to all other files?')) {
                    bulkCopyClients(getFileWrapperByIndex(sourceIndex));
                    alert('Client assignments copied!');
                }
            });

            $('.bulk-copy-groups').on('click', function() {
                var sourceIndex = getSourceFileIndex('bulkAssignmentSource');
                if (!sourceIndex) return;

                if (confirm('Copy group assignments from the selected file to all other files?')) {
                    bulkCopyGroups(getFileWrapperByIndex(sourceIndex));
                    alert('Group assignments copied!');
                }
            });

            $('.bulk-copy-hidden').on('click', function() {
                var sourceIndex = getSourceFileIndex('bulkAssignmentSource');
                if (!sourceIndex) return;

                if (confirm('Copy hidden status from the selected file to all other files?')) {
                    bulkCopyHidden(getFileWrapperByIndex(sourceIndex));
                    alert('Hidden status copied!');
                }
            });

            $('.bulk-copy-categories').on('click', function() {
                var sourceIndex = getSourceFileIndex('bulkOrganizationSource');
                if (!sourceIndex) return;

                if (confirm('Copy category assignments from the selected file to all other files?')) {
                    bulkCopyCategories(getFileWrapperByIndex(sourceIndex));
                    alert('Category assignments copied!');
                }
            });

            $('.bulk-copy-folder').on('click', function() {
                var sourceIndex = getSourceFileIndex('bulkOrganizationSource');
                if (!sourceIndex) return;

                if (confirm('Copy folder assignment from the selected file to all other files?')) {
                    bulkCopyFolder(getFileWrapperByIndex(sourceIndex));
                    alert('Folder assignment copied!');
                }
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