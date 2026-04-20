(function () {
    'use strict';

    admin.pages.updates = function () {
        function initUpdates() {
            console.log('Updates page loaded');
            console.log('json_strings available:', typeof json_strings !== 'undefined');
            if (typeof json_strings !== 'undefined') {
                console.log('Base URI:', json_strings.uri.base);
            }
            var requirementsCheck = document.getElementById('requirements-check');
            var requirementsCard = document.getElementById('requirements-card');
            var progressCard = document.getElementById('update-progress-card');
            var startUpdateButton = document.getElementById('start-update');
            var progressBar = document.querySelector('.progress-bar');
            var progressStatus = document.getElementById('progress-status');
            var updateError = document.getElementById('update-error');
            var errorMessage = document.getElementById('error-message');
            var updateSuccess = document.getElementById('update-success');
            var requirementsList = document.querySelector('.requirements-list');

            var updateSteps = ['download', 'backup', 'extract', 'finalize'];
            var currentStep = 0;

            // Check system requirements on page load
            checkRequirements();

            // Handle update button click
            if (startUpdateButton) {
                startUpdateButton.addEventListener('click', startUpdate);
            }

            function checkRequirements() {
                console.log('Starting requirements check');
                console.log('URL:', json_strings.uri.base + 'process.php?do=check_update_requirements');

                // Prepare request data with CSRF token for POST request
                var formData = new FormData();
                var csrfToken = document.getElementById('csrf_token');
                if (csrfToken) {
                    formData.append('csrf_token', csrfToken.value);
                }

                fetch(json_strings.uri.base + 'process.php?do=check_update_requirements', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        // Hide loading indicator
                        requirementsCheck.querySelector('.text-center').style.display = 'none';
                        requirementsList.style.display = 'block';
                        requirementsList.innerHTML = '';

                        // Display requirements
                        if (data.requirements) {
                            data.requirements.forEach(function(req) {
                                var li = document.createElement('li');
                                li.className = req.status ? 'requirement-pass' : 'requirement-fail';
                                li.innerHTML = '<strong>' + req.name + '</strong>: ' + req.message;
                                requirementsList.appendChild(li);
                            });
                        }

                        // Enable update button if all requirements pass
                        if (data.can_update) {
                            startUpdateButton.disabled = false;
                            startUpdateButton.classList.remove('btn-secondary');
                            startUpdateButton.classList.add('btn-primary');
                        } else {
                            startUpdateButton.disabled = true;
                            startUpdateButton.innerHTML = '<i class="fa fa-times"></i> ' + json_strings.updates.cannot_update;
                        }
                    })
                    .catch(error => {
                        console.error('Error checking requirements:', error);
                        console.log('Full error details:', error);
                        requirementsCheck.querySelector('.text-center').style.display = 'none';
                        requirementsList.style.display = 'block';
                        requirementsList.innerHTML = '<li class="requirement-fail">' + json_strings.updates.error_checking_requirements + '</li>';
                    });
            }

            function startUpdate() {
                // Disable button
                startUpdateButton.disabled = true;

                // Hide requirements card and show progress card
                requirementsCard.style.display = 'none';
                progressCard.style.display = 'block';
                updateError.style.display = 'none';
                updateSuccess.style.display = 'none';

                // Smooth scroll to top
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });

                // Start with first step
                currentStep = 0;
                processUpdateStep();
            }

            function processUpdateStep() {
                if (currentStep >= updateSteps.length) {
                    // Update completed
                    showSuccess();
                    return;
                }

                var step = updateSteps[currentStep];
                updateStepIndicator(step, 'active');
                updateProgressBar((currentStep / updateSteps.length) * 100);

                var stepMessages = {
                    'download': json_strings.updates.downloading_update,
                    'backup': json_strings.updates.creating_backup,
                    'extract': json_strings.updates.installing_files,
                    'finalize': json_strings.updates.finalizing_update
                };

                progressStatus.textContent = stepMessages[step] || json_strings.updates.processing;

                // Prepare request data
                var formData = new FormData();
                formData.append('step', step);

                // Add CSRF token
                var csrfToken = document.getElementById('csrf_token');
                if (csrfToken) {
                    formData.append('csrf_token', csrfToken.value);
                }

                if (step === 'download' && typeof update_download_url !== 'undefined') {
                    formData.append('url', update_download_url);
                    // Add SHA256 hash if available
                    if (typeof update_data !== 'undefined' && update_data.sha256) {
                        formData.append('hash', update_data.sha256);
                    }
                }

                // Execute step
                fetch(json_strings.uri.base + 'process.php?do=perform_system_update', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        updateStepIndicator(step, 'complete');
                        currentStep++;

                        // Continue to next step
                        setTimeout(function() {
                            processUpdateStep();
                        }, 500);
                    } else {
                        // Error occurred - redirect with error message
                        console.error('Update step failed:', data);
                        console.log('Showing error:', data.message || json_strings.updates.update_failed);

                        var errorMsg = data.message || json_strings.updates.update_failed;
                        window.location.href = json_strings.uri.base + 'updates.php?error=' + encodeURIComponent(errorMsg);
                    }
                })
                .catch(error => {
                    console.error('Update error:', error);
                    var errorMsg = json_strings.updates.update_failed + ': ' + error.message;
                    window.location.href = json_strings.uri.base + 'updates.php?error=' + encodeURIComponent(errorMsg);
                });
            }

            function attemptRollback() {
                progressStatus.textContent = json_strings.updates.rolling_back;
                updateProgressBar(0);

                var formData = new FormData();
                formData.append('step', 'rollback');

                // Add CSRF token
                var csrfToken = document.getElementById('csrf_token');
                if (csrfToken) {
                    formData.append('csrf_token', csrfToken.value);
                }

                fetch(json_strings.uri.base + 'process.php?do=perform_system_update', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        errorMessage.innerHTML += '<br>' + json_strings.updates.rollback_successful;
                    } else {
                        errorMessage.innerHTML += '<br>' + json_strings.updates.rollback_failed;
                    }
                })
                .catch(error => {
                    console.error('Rollback error:', error);
                    errorMessage.innerHTML += '<br>' + json_strings.updates.rollback_failed;
                });
            }

            function updateStepIndicator(step, status) {
                var indicator = document.querySelector('.step-indicator[data-step="' + step + '"]');
                if (indicator) {
                    // Remove all status classes
                    indicator.classList.remove('step-active', 'step-complete', 'step-error');

                    // Add appropriate class
                    if (status === 'active') {
                        indicator.classList.add('step-active');
                    } else if (status === 'complete') {
                        indicator.classList.add('step-complete');
                    } else if (status === 'error') {
                        indicator.classList.add('step-error');
                    }
                }
            }

            function updateProgressBar(percent) {
                progressBar.style.width = percent + '%';
                progressBar.setAttribute('aria-valuenow', percent);
            }

            function showError(message) {
                progressCard.style.display = 'block';
                updateError.style.display = 'block';
                errorMessage.textContent = message;

                // Mark current step as error
                if (currentStep < updateSteps.length) {
                    updateStepIndicator(updateSteps[currentStep], 'error');
                }
            }

            function showSuccess() {
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.add('bg-success');
                updateProgressBar(100);
                progressStatus.textContent = json_strings.updates.update_complete;
                updateSuccess.style.display = 'block';

                // Mark all steps as complete
                updateSteps.forEach(function(step) {
                    updateStepIndicator(step, 'complete');
                });

                // Redirect to dashboard after 5 seconds
                setTimeout(function() {
                    window.location.href = json_strings.uri.base + 'dashboard.php';
                }, 5000);
            }
        }

        // Check if DOM is already loaded or wait for it
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initUpdates);
        } else {
            initUpdates();
        }
    };
})();