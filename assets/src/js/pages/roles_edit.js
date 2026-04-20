(function () {
    'use strict';

    admin.pages.rolesEdit = function () {

        document.addEventListener('DOMContentLoaded', function() {
            const deleteButton = document.getElementById('delete-role');

            if (deleteButton) {
                deleteButton.addEventListener('click', function() {
                    const roleId = this.dataset.roleId || this.getAttribute('data-role-id');

                    if (confirm('Are you sure you want to delete this role?\n\nThis action cannot be undone.')) {
                        // Create form and submit
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'process.php';

                        // Get CSRF token from any existing form on the page
                        const existingCsrfInput = document.querySelector('input[name="csrf_token"]');
                        const csrfValue = existingCsrfInput ? existingCsrfInput.value : '';

                        form.innerHTML = `
                            <input type="hidden" name="do" value="delete_role">
                            <input type="hidden" name="role_id" value="${roleId}">
                            <input type="hidden" name="csrf_token" value="${csrfValue}">
                        `;

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }
        });
    };
})();