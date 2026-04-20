(function () {
    'use strict';

    admin.pages.thumbnailsRegenerate = function () {
        var isProcessing = false;
        var currentBatch = 0;
        var totalFiles = 0;
        var processedFiles = 0;
        var failedFiles = 0;

        $(document).ready(function(){
            // Button click handler for regeneration
            $('#regenerate-btn').on('click', function(e) {
                e.preventDefault();

                var selectedFormats = $('input[name="formats[]"]:checked').length;
                if (selectedFormats === 0) {
                    Swal.fire({
                        title: json_strings.thumbnails_regenerate.no_formats_selected,
                        text: json_strings.thumbnails_regenerate.select_one_format,
                        icon: 'warning',
                        showConfirmButton: true,
                        confirmButtonText: json_strings.translations.ok,
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
                    return false;
                }

                var width = parseInt($('#thumbnail_width').val());
                var height = parseInt($('#thumbnail_height').val());

                if (width < 50 || width > 1000 || height < 50 || height > 1000) {
                    Swal.fire({
                        title: json_strings.thumbnails_regenerate.invalid_dimensions_title,
                        text: json_strings.thumbnails_regenerate.invalid_dimensions,
                        icon: 'error',
                        showConfirmButton: true,
                        confirmButtonText: json_strings.translations.ok,
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
                    return false;
                }

                // Show SweetAlert2 confirmation and start processing
                Swal.fire({
                    title: json_strings.thumbnails_regenerate.start_regeneration_title,
                    html: '<div class="text-start">' +
                          '<p class="mb-3">' + json_strings.thumbnails_regenerate.process_will_message + '</p>' +
                          '<ul class="">' +
                          '<li>' + json_strings.thumbnails_regenerate.replace_thumbnails + '</li>' +
                          '<li>' + json_strings.thumbnails_regenerate.take_minutes + '</li>' +
                          '<li>' + json_strings.thumbnails_regenerate.run_background + '</li>' +
                          '<li>' + json_strings.thumbnails_regenerate.keep_page_open + '</li>' +
                          '</ul>' +
                          '</div>',
                    icon: 'warning',
                    showCloseButton: false,
                    showCancelButton: true,
                    showConfirmButton: true,
                    focusCancel: true,
                    cancelButtonText: json_strings.translations.cancel,
                    confirmButtonText: json_strings.thumbnails_regenerate.start_regeneration,
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary me-2',
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
                        startThumbnailRegeneration();
                    }
                });

                return false;
            });
            
            // Date validation for filter form
            $('#filter_start_date, #filter_end_date').on('change', function() {
                var startDate = $('#filter_start_date').val();
                var endDate = $('#filter_end_date').val();
                
                if (startDate && endDate && startDate > endDate) {
                    Swal.fire({
                        title: json_strings.thumbnails_regenerate.invalid_date_range_title,
                        text: json_strings.thumbnails_regenerate.invalid_date_range,
                        icon: 'error',
                        showConfirmButton: true,
                        confirmButtonText: json_strings.translations.ok,
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
                    $(this).val('');
                }
            });
            
            // Update counts when format selection changes (placeholder for future enhancement)
            $('input[name="formats[]"]').on('change', function() {
                // TODO: Update filtered count via AJAX
                console.log('Format selection changed - will implement dynamic count update later');
            });
        });

        function startThumbnailRegeneration() {
            if (isProcessing) {
                return;
            }

            isProcessing = true;
            currentBatch = 0;
            totalFiles = 0;
            processedFiles = 0;
            failedFiles = 0;

            var regenerateButton = $('#regenerate-btn');
            var form = $('#thumbnails-form');

            // Disable form and show progress
            regenerateButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + json_strings.thumbnails_regenerate.processing);
            form.find('input, button').prop('disabled', true);

            // Create progress container
            createProgressContainer();

            // Get selected formats
            var selectedFormats = [];
            $('input[name="formats[]"]:checked').each(function() {
                selectedFormats.push($(this).val());
            });

            // Get form data
            var formData = {
                formats: selectedFormats,
                width: parseInt($('#thumbnail_width').val()),
                height: parseInt($('#thumbnail_height').val()),
                start_date: $('input[name="filtered_start_date"]').val() || null,
                end_date: $('input[name="filtered_end_date"]').val() || null
            };

            // Start processing
            processNextBatch(formData);
        }

        function createProgressContainer() {
            var progressHtml = '<div id="thumbnail-progress" class="mt-4">' +
                '<div class="ps-card">' +
                '<div class="ps-card-body">' +
                '<h6>' + json_strings.thumbnails_regenerate.processing_title + '</h6>' +
                '<div class="progress mb-3" style="height: 25px;">' +
                '<div class="progress-bar progress-bar-striped progress-bar-animated" ' +
                'role="progressbar" style="width: 0%;" id="progress-bar">' +
                '<span id="progress-text">0%</span>' +
                '</div>' +
                '</div>' +
                '<div class="row">' +
                '<div class="col-md-4">' +
                '<div class="text-center">' +
                '<div class="h4 text-primary" id="processed-count">0</div>' +
                '<small class="text-muted">' + json_strings.thumbnails_regenerate.processed + '</small>' +
                '</div>' +
                '</div>' +
                '<div class="col-md-4">' +
                '<div class="text-center">' +
                '<div class="h4 text-danger" id="failed-count">0</div>' +
                '<small class="text-muted">' + json_strings.thumbnails_regenerate.failed + '</small>' +
                '</div>' +
                '</div>' +
                '<div class="col-md-4">' +
                '<div class="text-center">' +
                '<div class="h4 text-info" id="total-count">0</div>' +
                '<small class="text-muted">' + json_strings.thumbnails_regenerate.total + '</small>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<div id="current-file" class="mt-3 text-muted"></div>' +
                '<div id="process-log" class="mt-3" style="max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px; background: #f8f9fa; padding: 10px; border-radius: 4px; display: none;"></div>' +
                '<button type="button" class="btn btn-sm btn-secondary mt-2" id="toggle-log">' + json_strings.thumbnails_regenerate.show_log + '</button>' +
                '</div>' +
                '</div>' +
                '</div>';
            
            $('#thumbnails-form').after(progressHtml);

            // Add toggle log functionality
            $('#toggle-log').on('click', function() {
                $('#process-log').toggle();
                $(this).text($('#process-log').is(':visible') ? 
                    json_strings.thumbnails_regenerate.hide_log : 
                    json_strings.thumbnails_regenerate.show_log);
            });
        }

        function processNextBatch(formData) {
            var batchSize = 5; // Process 5 files at a time
            var offset = currentBatch * batchSize;

            // Get files for this batch
            $.ajax({
                url: json_strings.uri.base + 'includes/ajax.process.php?do=thumbnails_regenerate_get_files',
                type: 'POST',
                data: {
                    formats: formData.formats,
                    start_date: formData.start_date,
                    end_date: formData.end_date,
                    batch_size: batchSize,
                    offset: offset,
                    csrf_token: document.getElementById('csrf_token').value
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        var files = response.data.files;
                        
                        // Update total count on first batch
                        if (currentBatch === 0) {
                            totalFiles = response.data.total;
                            $('#total-count').text(totalFiles);
                            
                            if (totalFiles === 0) {
                                logMessage('No files found to process', 'info');
                                finishProcessing();
                                return;
                            }
                        }

                        if (files.length === 0) {
                            // No more files to process
                            finishProcessing();
                            return;
                        }

                        // Process files in this batch
                        processBatchFiles(files, formData, function() {
                            // Continue with next batch if there are more files
                            if (response.data.has_more) {
                                currentBatch++;
                                processNextBatch(formData);
                            } else {
                                finishProcessing();
                            }
                        });
                    } else {
                        logMessage('Error getting files: ' + response.message, 'error');
                        finishProcessing();
                    }
                },
                error: function(xhr, status, error) {
                    logMessage('AJAX Error: ' + error, 'error');
                    finishProcessing();
                }
            });
        }

        function processBatchFiles(files, formData, callback) {
            var fileIndex = 0;

            function processNextFile() {
                if (fileIndex >= files.length) {
                    callback();
                    return;
                }

                var file = files[fileIndex];
                $('#current-file').text('Processing: ' + file.original_filename);

                $.ajax({
                    url: json_strings.uri.base + 'includes/ajax.process.php?do=thumbnails_regenerate_process',
                    type: 'POST',
                    data: {
                        file_id: file.id,
                        width: formData.width,
                        height: formData.height,
                        csrf_token: document.getElementById('csrf_token').value
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            processedFiles++;
                            logMessage('✓ ' + response.data.filename, 'success');
                        } else {
                            failedFiles++;
                            logMessage('✗ ' + (response.data ? response.data.filename : 'Unknown file') + ': ' + response.message, 'error');
                        }
                        
                        updateProgress();
                        fileIndex++;
                        
                        // Small delay between files to prevent overwhelming the server
                        setTimeout(processNextFile, 100);
                    },
                    error: function(xhr, status, error) {
                        failedFiles++;
                        logMessage('✗ ' + file.original_filename + ': AJAX Error - ' + error, 'error');
                        updateProgress();
                        fileIndex++;
                        setTimeout(processNextFile, 100);
                    }
                });
            }

            processNextFile();
        }

        function updateProgress() {
            var totalProcessed = processedFiles + failedFiles;
            var percentage = totalFiles > 0 ? Math.round((totalProcessed / totalFiles) * 100) : 0;
            
            $('#progress-bar').css('width', percentage + '%');
            $('#progress-text').text(percentage + '%');
            $('#processed-count').text(processedFiles);
            $('#failed-count').text(failedFiles);
        }

        function logMessage(message, type) {
            var className = type === 'error' ? 'text-danger' : (type === 'success' ? 'text-success' : '');
            var timestamp = new Date().toLocaleTimeString();
            $('#process-log').append('<div class="' + className + '">[' + timestamp + '] ' + message + '</div>');
            $('#process-log').scrollTop($('#process-log')[0].scrollHeight);
        }

        function finishProcessing() {
            isProcessing = false;
            
            var regenerateButton = $('#regenerate-btn');
            var form = $('#thumbnails-form');
            
            $('#current-file').text('');
            regenerateButton.prop('disabled', false).html('<i class="fa fa-refresh"></i> ' + json_strings.thumbnails_regenerate.start_regeneration);
            form.find('input, button').prop('disabled', false);

            var message = json_strings.thumbnails_regenerate.process_completed
                .replace('{processed}', processedFiles)
                .replace('{failed}', failedFiles)
                .replace('{total}', processedFiles + failedFiles);

            logMessage(message, 'info');

            // Show completion message
            if (failedFiles > 0) {
                Swal.fire({
                    title: json_strings.thumbnails_regenerate.process_complete_errors_title,
                    text: json_strings.thumbnails_regenerate.completed_with_errors
                        .replace('{processed}', processedFiles)
                        .replace('{failed}', failedFiles),
                    icon: 'warning',
                    showConfirmButton: true,
                    confirmButtonText: json_strings.translations.ok,
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
            } else {
                Swal.fire({
                    title: json_strings.thumbnails_regenerate.process_complete_title,
                    text: json_strings.thumbnails_regenerate.completed_successfully
                        .replace('{processed}', processedFiles),
                    icon: 'success',
                    showConfirmButton: true,
                    confirmButtonText: json_strings.translations.ok,
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
        }
    };
})();