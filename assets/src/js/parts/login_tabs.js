(function () {
    'use strict';

    admin.parts.loginTabs = function () {
        $(document).ready(function() {
            // Initialize Bootstrap tabs if not already initialized
            if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                // Bootstrap 5 style
                var triggerTabList = [].slice.call(document.querySelectorAll('#local-tab, #ldap-tab'));
                triggerTabList.forEach(function (triggerEl) {
                    var tabTrigger = new bootstrap.Tab(triggerEl);
                    
                    triggerEl.addEventListener('click', function (event) {
                        event.preventDefault();
                        tabTrigger.show();
                    });
                });
            } else {
                // Fallback for older Bootstrap or jQuery tabs
                $('#local-tab, #ldap-tab').on('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active classes from all tabs and content
                    $('.nav-link').removeClass('active');
                    $('.tab-pane').removeClass('active show');
                    
                    // Add active class to clicked tab
                    $(this).addClass('active');
                    
                    // Show corresponding content
                    var target = $(this).data('bs-target') || $(this).attr('href');
                    $(target).addClass('active show');
                    
                    // Clear any previous form data and focus on the appropriate field
                    if (target === '#local') {
                        $('#username').focus();
                        $('#ldap_email, #ldap_password').val('');
                    } else if (target === '#ldap') {
                        $('#ldap_email').focus();
                        $('#username, #password').val('');
                    }
                });
            }
            
            // Handle URL hash for direct tab linking
            if (window.location.hash === '#ldap') {
                $('#ldap-tab').click();
            }
            
            // Clear form fields when switching tabs
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                var target = $(e.target).data('bs-target');
                
                if (target === '#local') {
                    $('#ldap_email, #ldap_password').val('');
                    setTimeout(function() {
                        $('#username').focus();
                    }, 150);
                } else if (target === '#ldap') {
                    $('#username, #password').val('');
                    setTimeout(function() {
                        $('#ldap_email').focus();
                    }, 150);
                }
                
                // Clear any error messages
                $('.ajax_response').removeClass('alert-danger alert-success').html('').hide();
            });
            
            // Update browser history when switching tabs
            $('button[data-bs-toggle="tab"]').on('click', function (e) {
                var target = $(e.target).data('bs-target');
                if (target === '#ldap') {
                    history.replaceState(null, null, '#ldap');
                } else {
                    history.replaceState(null, null, window.location.pathname);
                }
            });
        });
    };
})();