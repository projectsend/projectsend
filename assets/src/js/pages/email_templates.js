(function () {
    'use strict';

    admin.pages.emailTemplates = function () {

        $(document).ready(function(){
            $(document).on('click', '.load_default', function(e) {
                e.preventDefault();

                var file = jQuery(this).data('file');
                var textarea = document.getElementById(jQuery(this).data('textarea'));
                var accept = confirm(json_strings.translations.email_templates.confirm_replace);
                
                if ( accept ) {
                    $.ajax({
                        url: json_strings.uri.base + "emails/"+file,
                        cache: false,
                        success: function (data){
                            textarea.value = data;
                        },
                        error: function() {
                            alert(json_strings.translations.email_templates.loading_error);
                        }
                    });
                }
            });
    
            $('.preview').click(function(e) {
                e.preventDefault();
                var type = jQuery(this).data('preview');
                var url = json_strings.uri.base+ 'email-preview.php?t=' + type;
                window.open(url, "previewWindow", "width=800,height=600,scrollbars=yes");
            });
        });

        $('.insert_tag').on('click', function(e) {
            var target = jQuery(this).data('target');
            var tag = $(this).data('tag');

            // Check if we have a CodeMirror editor for this textarea
            if (editors[target]) {
                var editor = editors[target];
                var doc = editor.getDoc();
                var cursor = doc.getCursor();
                doc.replaceRange(tag, cursor);
                editor.focus();
            } else {
                // Fallback to original function for regular textareas
                insertAtCaret(target, tag);
            }
        });

        // Template Gallery functionality
        $(document).on('click', '.btn-template-preview', function(e) {
            e.preventDefault();
            var templateId = $(this).data('template-id');
            showTemplatePreview(templateId);
        });

        // Template Apply functionality
        $(document).on('click', '.btn-template-apply', function(e) {
            e.preventDefault();
            var templateId = $(this).data('template-id');
            var templateName = $(this).data('template-name');
            showTemplateApplyConfirmation(templateId, templateName);
        });

        function showTemplatePreview(templateId) {
            // Create modal if it doesn't exist
            if ($('.template-preview-modal').length === 0) {
                var modalHtml = `
                    <div class="template-preview-modal">
                        <div class="template-preview-content">
                            <div class="template-preview-header">
                                <h3 class="template-preview-title">Template Preview</h3>
                                <button class="template-preview-close">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                            <div class="template-preview-body">
                                <iframe class="template-preview-iframe" src=""></iframe>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(modalHtml);
            }

            // Set iframe source
            var previewUrl = json_strings.uri.base + 'email-template-preview.php?template=' + templateId;
            $('.template-preview-iframe').attr('src', previewUrl);

            // Show modal
            $('.template-preview-modal').addClass('active');
        }

        // Close template preview modal
        $(document).on('click', '.template-preview-close', function(e) {
            e.preventDefault();
            $('.template-preview-modal').removeClass('active');
            // Clear iframe to stop any animations
            setTimeout(function() {
                $('.template-preview-iframe').attr('src', '');
            }, 300);
        });

        // Close modal when clicking outside content
        $(document).on('click', '.template-preview-modal', function(e) {
            if (e.target === this) {
                $('.template-preview-modal').removeClass('active');
                // Clear iframe to stop any animations
                setTimeout(function() {
                    $('.template-preview-iframe').attr('src', '');
                }, 300);
            }
        });

        // Prevent modal from closing when clicking inside content
        $(document).on('click', '.template-preview-content', function(e) {
            e.stopPropagation();
        });

        // Close modal with escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('.template-preview-modal').hasClass('active')) {
                $('.template-preview-modal').removeClass('active');
                setTimeout(function() {
                    $('.template-preview-iframe').attr('src', '');
                }, 300);
            }
        });

        function showTemplateApplyConfirmation(templateId, templateName) {
            Swal.fire({
                title: 'Apply Email Template?',
                html: `
                    <div class="text-start">
                        <p class="mb-3">This will apply the <strong>"${templateName}"</strong> template to your email headers and footers.</p>
                        <div class="alert alert-warning mb-3">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will replace your current custom header and footer content.
                        </div>
                        <p class="mb-0">The following will happen:</p>
                        <ul class="list-unstyled mt-2">
                            <li><i class="fa fa-check text-success me-2"></i>Template header will be loaded into custom header</li>
                            <li><i class="fa fa-check text-success me-2"></i>Template footer will be loaded into custom footer</li>
                            <li><i class="fa fa-check text-success me-2"></i>Custom header/footer option will be enabled</li>
                        </ul>
                    </div>
                `,
                icon: 'question',
                showCloseButton: false,
                showCancelButton: true,
                showConfirmButton: true,
                focusCancel: true,
                cancelButtonText: 'Cancel',
                confirmButtonText: 'Apply Template',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-success me-2',
                    cancelButton: 'btn btn-secondary'
                },
                showClass: {
                    popup: 'animate__animated animate__fadeIn'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOut'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    applyEmailTemplate(templateId, templateName);
                }
            });
        }

        function applyEmailTemplate(templateId, templateName) {
            // Show loading state
            Swal.fire({
                title: 'Applying Template...',
                text: 'Please wait while the template is being applied.',
                icon: 'info',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                showClass: {
                    popup: 'animate__animated animate__fadeIn'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOut'
                }
            });

            $.ajax({
                url: json_strings.uri.base + 'apply-email-template.php',
                type: 'POST',
                data: {
                    template_id: templateId,
                    csrf_token: document.getElementById('csrf_token').value
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Template Applied Successfully!',
                            text: `The "${templateName}" template has been applied to your email headers and footers.`,
                            icon: 'success',
                            showConfirmButton: true,
                            confirmButtonText: 'OK',
                            buttonsStyling: false,
                            customClass: {
                                confirmButton: 'btn btn-success'
                            },
                            showClass: {
                                popup: 'animate__animated animate__fadeIn'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOut'
                            }
                        }).then(() => {
                            // Optionally redirect to header/footer page
                            // window.location.href = json_strings.uri.base + 'email-templates.php?section=header_footer';
                        });
                    } else {
                        Swal.fire({
                            title: 'Error Applying Template',
                            text: response.message || 'An error occurred while applying the template.',
                            icon: 'error',
                            showConfirmButton: true,
                            confirmButtonText: 'OK',
                            buttonsStyling: false,
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            showClass: {
                                popup: 'animate__animated animate__fadeIn'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOut'
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        title: 'Connection Error',
                        text: 'Could not connect to the server. Please try again.',
                        icon: 'error',
                        showConfirmButton: true,
                        confirmButtonText: 'OK',
                        buttonsStyling: false,
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        showClass: {
                            popup: 'animate__animated animate__fadeIn'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__fadeOut'
                        }
                    });
                }
            });
        }

        // Initialize HTML editors for email template textareas
        var htmlTextareas = document.querySelectorAll('#form_email_template textarea.textarea_high');
        var editors = {};

        htmlTextareas.forEach(function(textarea) {
            if (typeof CodeMirror !== 'undefined') {
                editors[textarea.id] = CodeMirror.fromTextArea(textarea, {
                    mode: 'htmlmixed',
                    lineNumbers: true,
                    lineWrapping: true,
                    autoCloseTags: true,
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    theme: 'default',
                    extraKeys: {
                        "Ctrl-Space": "autocomplete",
                        "F11": function(cm) {
                            cm.setOption("fullScreen", !cm.getOption("fullScreen"));
                        },
                        "Esc": function(cm) {
                            if (cm.getOption("fullScreen")) cm.setOption("fullScreen", false);
                        }
                    }
                });
            }
        });

        // Check if each tag is used or not
        var tags_dt = document.querySelectorAll('#email_available_tags dt button');
        var tags = [];
        Array.prototype.forEach.call(tags_dt, function(tag) {
            tags.push(tag.dataset.tag);
        });

        var textareas = document.querySelectorAll('#form_email_template textarea');

        const check_tags_usage = setInterval(() => {
            tags.forEach(tag => {
                checkTagsUsage(tag);
            });
        }, 1000);

        function checkTagsUsage(tag)
        {
            textareas.forEach(element => {
                const el = document.querySelector('button[data-tag="'+tag+'"]');
                if (!element.value.includes(tag)) {
                    el.classList.add('btn-warning');
                    el.classList.remove('btn-pslight');
                } else {
                    el.classList.add('btn-pslight');
                    el.classList.remove('btn-warning');
                }
            });
        }
    };
})();