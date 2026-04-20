(function () {
    'use strict';

    admin.pages.options = function () {
        var tagifyContainer = document.getElementById('allowed_file_types');
        var tagify = new Tagify (tagifyContainer);
        //tagifyContainer.addEventListener('change', tagifyOnChange)

        function tagifyOnChange(e){
            console.log(e.target.value)
        }

        $(document).ready(function(){
            var validator = $("#options").validate({
                errorPlacement: function(error, element) {
                    error.appendTo(element.parent('div'));
                },
            });

            $('#download_method').on('change', function(e) {
                var method = $(this).find('option:selected').val();
                $('.method_note').hide();
                $('.method_note[data-method="'+method+'"]').show();
            });

            $('#download_method').trigger('change');
        });

        const captchaMethodSelect = document.getElementById('captcha_method');
        const captchaOptionsBlocks = document.querySelectorAll('.captcha_options_block');
        if (elementExists(captchaMethodSelect)) {
            captchaMethodSelect.addEventListener('change', function(e) {
                const showOptionsBlock = document.getElementById('captcha_' + e.target.value)
                console.log(showOptionsBlock);
                for (let i = 0; i < captchaOptionsBlocks.length; i++) {
                    const captchaOptionsBlock = captchaOptionsBlocks[i];
                    captchaOptionsBlock.classList.add('d-none');
                }

                if (elementExists(showOptionsBlock)) {
                    showOptionsBlock.classList.remove('d-none');
                }
            });
        }

        // Mail system options visibility control
        const mailSystemSelect = document.getElementById('mail_system_use');
        if (elementExists(mailSystemSelect)) {
            function toggleMailFields() {
                const selectedValue = mailSystemSelect.value;
                const authFields = document.querySelectorAll('.mail-auth-field');
                const smtpFields = document.querySelectorAll('.mail-smtp-field');

                // Find SMTP section header
                const h3Elements = document.querySelectorAll('h3');
                let smtpSection = null;
                h3Elements.forEach(h3 => {
                    if (h3.textContent.includes('SMTP options')) {
                        smtpSection = h3;
                    }
                });

                // Hide all conditional fields initially
                authFields.forEach(field => field.style.display = 'none');
                smtpFields.forEach(field => field.style.display = 'none');

                // Hide SMTP section header
                if (smtpSection) {
                    smtpSection.style.display = 'none';
                }

                // Show username/password fields for SMTP and Gmail
                if (selectedValue === 'smtp' || selectedValue === 'gmail') {
                    authFields.forEach(field => field.style.display = '');
                }

                // Show SMTP-specific fields and section only for SMTP
                if (selectedValue === 'smtp') {
                    smtpFields.forEach(field => field.style.display = '');
                    if (smtpSection) {
                        smtpSection.style.display = '';
                    }
                }
            }

            // Set initial state
            toggleMailFields();

            // Listen for changes
            mailSystemSelect.addEventListener('change', toggleMailFields);
        }

        // Branding section - Logo preview and upload functionality
        const logoPreview = document.getElementById('logo-preview-img');
        if (elementExists(logoPreview)) {
            // Debug SVG visibility
            console.log('Logo preview element found:', logoPreview);
            console.log('Logo src:', logoPreview.src);

            // Add error handling for logo loading
            logoPreview.addEventListener('load', function() {
                console.log('Logo loaded successfully');
            });

            logoPreview.addEventListener('error', function() {
                console.error('Logo failed to load');
                console.error('Failed URL:', logoPreview.src);
            });
        }

        const logoInput = document.getElementById('select_logo');
        const logoWarning = document.getElementById('logo-upload-warning');
        if (elementExists(logoInput) && elementExists(logoPreview)) {
            logoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file type
                    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml'];
                    if (!validTypes.includes(file.type)) {
                        alert('Please select a valid image file (JPG, PNG, GIF, SVG)');
                        logoInput.value = '';
                        return;
                    }

                    // Validate file size (10MB)
                    if (file.size > 10 * 1024 * 1024) {
                        alert('File size must be less than 10MB');
                        logoInput.value = '';
                        return;
                    }

                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        logoPreview.src = e.target.result;
                        logoPreview.classList.add('preview-selected');
                        if (elementExists(logoWarning)) {
                            logoWarning.style.display = 'block';
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Reset preview if no file selected
                    logoPreview.classList.remove('preview-selected');
                    if (elementExists(logoWarning)) {
                        logoWarning.style.display = 'none';
                    }
                }
            });
        }

        // Branding section - Favicon preview and upload functionality
        const faviconInput = document.getElementById('select_favicon');
        const faviconPreview = document.getElementById('favicon-preview-img');
        const faviconWarning = document.getElementById('favicon-upload-warning');
        if (elementExists(faviconInput) && elementExists(faviconPreview)) {
            faviconInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file type
                    const validTypes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/gif', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
                    if (!validTypes.includes(file.type)) {
                        alert('Please select a valid favicon file (ICO, PNG, GIF, JPG, SVG)');
                        faviconInput.value = '';
                        return;
                    }

                    // Validate file size (1MB)
                    if (file.size > 1024 * 1024) {
                        alert('Favicon file size must be less than 1MB');
                        faviconInput.value = '';
                        return;
                    }

                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        faviconPreview.src = e.target.result;
                        faviconPreview.classList.add('preview-selected');
                        if (elementExists(faviconWarning)) {
                            faviconWarning.style.display = 'block';
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Reset preview if no file selected
                    faviconPreview.classList.remove('preview-selected');
                    if (elementExists(faviconWarning)) {
                        faviconWarning.style.display = 'none';
                    }
                }
            });
        }

        // Hide upload warnings when form is submitted
        const form = document.getElementById('options');
        if (elementExists(form)) {
            form.addEventListener('submit', function() {
                if (elementExists(logoWarning)) {
                    logoWarning.style.display = 'none';
                }
                if (elementExists(faviconWarning)) {
                    faviconWarning.style.display = 'none';
                }
            });
        }

        // Options section navigation - scroll tracking and active state
        const optionsNav = document.querySelector('.options-section-nav');
        if (elementExists(optionsNav)) {
            const navLinks = optionsNav.querySelectorAll('.nav-link');
            const sections = [];

            // Build sections array from nav links
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.startsWith('#')) {
                    const sectionId = href.substring(1);
                    const sectionElement = document.getElementById(sectionId);
                    if (sectionElement) {
                        sections.push({
                            id: sectionId,
                            element: sectionElement,
                            navLink: link
                        });
                    }
                }
            });

            // Function to update active nav item based on scroll position
            function updateActiveNav() {
                // Get current scroll position with offset for sticky nav (90px from top + nav height)
                const scrollPosition = window.scrollY + 120;

                let activeSection = null;

                // Find which section is currently in view
                sections.forEach(section => {
                    const sectionTop = section.element.offsetTop;
                    const sectionBottom = sectionTop + section.element.offsetHeight;

                    if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                        activeSection = section;
                    }
                });

                // If we're at the top of the page, activate first section
                if (!activeSection && window.scrollY < 200) {
                    activeSection = sections[0];
                }

                // Update active classes
                if (activeSection) {
                    sections.forEach(section => {
                        if (section.id === activeSection.id) {
                            section.navLink.classList.add('active');
                        } else {
                            section.navLink.classList.remove('active');
                        }
                    });
                }
            }

            // Throttle scroll event for performance
            let scrollTimeout;
            window.addEventListener('scroll', function() {
                if (scrollTimeout) {
                    window.cancelAnimationFrame(scrollTimeout);
                }
                scrollTimeout = window.requestAnimationFrame(function() {
                    updateActiveNav();
                });
            });

            // Handle nav link clicks
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    if (href && href.startsWith('#')) {
                        const targetId = href.substring(1);
                        const targetElement = document.getElementById(targetId);
                        if (targetElement) {
                            // Scroll to section with offset for sticky nav
                            const offset = 100;
                            const elementPosition = targetElement.offsetTop - offset;
                            window.scrollTo({
                                top: elementPosition,
                                behavior: 'smooth'
                            });

                            // Update active state immediately
                            navLinks.forEach(l => l.classList.remove('active'));
                            this.classList.add('active');
                        }
                    }
                });
            });

            // Initialize active state on page load
            updateActiveNav();
        }

        // Encryption settings - handle dependency between enabled and required
        const encryptionEnabledCheckbox = document.getElementById('files_encryption_enabled');
        const encryptionRequiredCheckbox = document.getElementById('files_encryption_required');

        if (elementExists(encryptionEnabledCheckbox) && elementExists(encryptionRequiredCheckbox)) {
            function toggleEncryptionRequired() {
                if (!encryptionEnabledCheckbox.checked) {
                    encryptionRequiredCheckbox.checked = false;
                    encryptionRequiredCheckbox.disabled = true;
                    // Add visual feedback
                    const label = encryptionRequiredCheckbox.closest('.col-sm-8');
                    if (label) {
                        label.style.opacity = '0.5';
                    }
                } else {
                    encryptionRequiredCheckbox.disabled = false;
                    const label = encryptionRequiredCheckbox.closest('.col-sm-8');
                    if (label) {
                        label.style.opacity = '1';
                    }
                }
            }

            // Set initial state
            toggleEncryptionRequired();

            // Listen for changes
            encryptionEnabledCheckbox.addEventListener('change', toggleEncryptionRequired);
        }

        // SMTP ports - default values (adapted from PR #1442 by @JazzMalar)
        const authSelect = document.getElementById("mail_smtp_secure");
        const portInput = document.getElementById("mail_smtp_port");
        if (elementExists(portInput)) {
            console.log(authSelect);
            const defaults = {
                "none": "25",   // plain SMTP
                "ssl": "465",   // implicit TLS
                "tls": "587"    // STARTTLS
            };

            authSelect.addEventListener("change", function () {
                const selected = this.value;
                if (defaults[selected]) {
                    portInput.value = defaults[selected];
                }
            });
        }
    };
})();