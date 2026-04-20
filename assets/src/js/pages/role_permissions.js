(function () {
    'use strict';

    admin.pages.rolePermissions = function () {
        const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
        const selectAllBtn = document.getElementById('select-all');
        const selectNoneBtn = document.getElementById('select-none');
        const categoryToggles = document.querySelectorAll('.category-toggle');

        // Store initial state
        const initialState = Array.from(permissionCheckboxes).map(cb => cb.checked);
        let changeCount = 0;

        function updateCounts() {
            // Calculate changes for unsaved warning functionality
            changeCount = 0;
            permissionCheckboxes.forEach((cb, index) => {
                if (cb.checked !== initialState[index]) {
                    changeCount++;
                }
            });
            // Note: Visual count display removed with Permission Summary box
        }

        // Select all permissions
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                permissionCheckboxes.forEach(cb => {
                    cb.checked = true;
                });
                updateCounts();
            });
        }

        // Select no permissions
        if (selectNoneBtn) {
            selectNoneBtn.addEventListener('click', function(e) {
                e.preventDefault();
                permissionCheckboxes.forEach(cb => {
                    cb.checked = false;
                });
                updateCounts();
            });
        }

        // Category toggles
        categoryToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const category = this.dataset.category;
                const categoryCheckboxes = document.querySelectorAll(`.permission-checkbox[data-category="${category}"]`);
                const allChecked = Array.from(categoryCheckboxes).every(cb => cb.checked);

                categoryCheckboxes.forEach(cb => {
                    cb.checked = !allChecked;
                });
                updateCounts();
            });
        });

        // Update counts when checkboxes change
        permissionCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateCounts);
        });

        // Warn about unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (changeCount > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Don't warn when submitting form
        const permissionsForm = document.getElementById('permissions_form');
        if (permissionsForm) {
            permissionsForm.addEventListener('submit', function() {
                changeCount = 0; // Reset change count to prevent warning
            });
        }

        // Initial count update
        updateCounts();
    };
})();