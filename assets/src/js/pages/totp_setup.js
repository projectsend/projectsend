(function () {
    'use strict';

    admin.pages.totpSetup = function () {
            // Copy secret to clipboard
            var copySecretBtn = document.getElementById('copy_secret_btn');
            if (copySecretBtn) {
                copySecretBtn.addEventListener('click', function () {
                    var secretText = document.getElementById('totp_secret_text').textContent;
                    navigator.clipboard.writeText(secretText).then(function () {
                        copySecretBtn.innerHTML = '<i class="fa fa-check"></i>';
                        setTimeout(function () {
                            copySecretBtn.innerHTML = '<i class="fa fa-clipboard"></i>';
                        }, 2000);
                    });
                });
            }

            // Copy backup codes to clipboard
            var copyBackupBtn = document.getElementById('copy_backup_codes_btn');
            if (copyBackupBtn) {
                copyBackupBtn.addEventListener('click', function () {
                    var codes = getBackupCodesText();
                    navigator.clipboard.writeText(codes).then(function () {
                        copyBackupBtn.innerHTML = '<i class="fa fa-check"></i> Copied!';
                        setTimeout(function () {
                            copyBackupBtn.innerHTML = '<i class="fa fa-clipboard"></i> Copy all codes';
                        }, 2000);
                    });
                });
            }

            // Download backup codes as text file
            var downloadBackupBtn = document.getElementById('download_backup_codes_btn');
            if (downloadBackupBtn) {
                downloadBackupBtn.addEventListener('click', function () {
                    var codes = getBackupCodesText();
                    var blob = new Blob([codes], { type: 'text/plain' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'projectsend-backup-codes.txt';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                });
            }

            function getBackupCodesText() {
                var container = document.getElementById('backup_codes_container');
                if (!container) return '';
                var codeElements = container.querySelectorAll('.backup-code code');
                var codes = [];
                codeElements.forEach(function (el) {
                    codes.push(el.textContent);
                });
                return 'ProjectSend Backup Codes\n========================\n' + codes.join('\n') + '\n\nKeep these codes in a safe place.\nEach code can only be used once.';
            }

            // Section navigation - scroll tracking (same pattern as options.js)
            var optionsNav = document.querySelector('.options-section-nav');
            if (optionsNav) {
                var navLinks = optionsNav.querySelectorAll('.nav-link');
                var sections = [];

                navLinks.forEach(function (link) {
                    var href = link.getAttribute('href');
                    if (href && href.startsWith('#')) {
                        var sectionId = href.substring(1);
                        var sectionElement = document.getElementById(sectionId);
                        if (sectionElement) {
                            sections.push({
                                id: sectionId,
                                element: sectionElement,
                                navLink: link
                            });
                        }
                    }
                });

                function updateActiveNav() {
                    var scrollPosition = window.scrollY + 120;
                    var activeSection = null;

                    sections.forEach(function (section) {
                        var sectionTop = section.element.offsetTop;
                        if (scrollPosition >= sectionTop) {
                            activeSection = section;
                        }
                    });

                    if (!activeSection && sections.length > 0) {
                        activeSection = sections[0];
                    }

                    if (activeSection) {
                        sections.forEach(function (section) {
                            if (section.id === activeSection.id) {
                                section.navLink.classList.add('active');
                            } else {
                                section.navLink.classList.remove('active');
                            }
                        });
                    }
                }

                var scrollTimeout;
                window.addEventListener('scroll', function () {
                    if (scrollTimeout) {
                        window.cancelAnimationFrame(scrollTimeout);
                    }
                    scrollTimeout = window.requestAnimationFrame(updateActiveNav);
                });

                navLinks.forEach(function (link) {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        var href = this.getAttribute('href');
                        if (href && href.startsWith('#')) {
                            var targetElement = document.getElementById(href.substring(1));
                            if (targetElement) {
                                var offset = 100;
                                window.scrollTo({
                                    top: targetElement.offsetTop - offset,
                                    behavior: 'smooth'
                                });
                                navLinks.forEach(function (l) { l.classList.remove('active'); });
                                link.classList.add('active');
                            }
                        }
                    });
                });

                updateActiveNav();
            }
    };
})();
