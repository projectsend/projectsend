(function () {
    'use strict';

    admin.pages.rolesAdd = function () {

        document.addEventListener('DOMContentLoaded', function() {
            const roleLevel = document.getElementById('role_level');
            const permissionsPreview = document.getElementById('permissions-preview');
            const previewLevel = document.getElementById('preview-level');
            const permissionsList = document.getElementById('permissions-list');

            // Default permissions for each level (simplified)
            const defaultPermissions = {
                9: ['Full system access', 'Manage users', 'Manage settings', 'Manage roles'],
                8: ['Manage clients', 'Manage files', 'View statistics'],
                7: ['Upload files', 'Manage own files'],
                6: ['Upload files', 'View own files'],
                5: ['Upload files', 'View own files'],
                4: ['Upload files', 'View own files'],
                3: ['Upload files', 'View own files'],
                2: ['Upload files', 'View own files'],
                1: ['Upload files', 'View own files']
            };

            if (roleLevel) {
                roleLevel.addEventListener('change', function() {
                    const level = parseInt(this.value);

                    if (level && defaultPermissions[level]) {
                        previewLevel.textContent = level;

                        const perms = defaultPermissions[level] || ['Basic file access'];
                        permissionsList.innerHTML = perms.map(perm =>
                            '<span class="badge bg-secondary me-1 mb-1">' + perm + '</span>'
                        ).join('');

                        permissionsPreview.style.display = 'block';
                    } else {
                        permissionsPreview.style.display = 'none';
                    }
                });
            }

            // Permission management for role creation
            const permissionCheckboxes = document.querySelectorAll('.permission-checkbox-create');
            const selectAllBtn = document.getElementById('select-all-permissions');
            const selectNoneBtn = document.getElementById('select-none-permissions');
            const categoryToggles = document.querySelectorAll('.category-toggle-create');

            // Select all permissions
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    permissionCheckboxes.forEach(cb => cb.checked = true);
                });
            }

            // Select no permissions
            if (selectNoneBtn) {
                selectNoneBtn.addEventListener('click', function() {
                    permissionCheckboxes.forEach(cb => cb.checked = false);
                });
            }

            // Category toggles
            categoryToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const category = this.dataset.category;
                    const categoryCheckboxes = document.querySelectorAll(`.permission-checkbox-create[data-category="${category}"]`);
                    const allChecked = Array.from(categoryCheckboxes).every(cb => cb.checked);

                    categoryCheckboxes.forEach(cb => cb.checked = !allChecked);
                });
            });
        });
    };
})();