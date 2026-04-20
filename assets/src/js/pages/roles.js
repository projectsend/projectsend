(function () {
    'use strict';

    admin.pages.roles = function () {
        let currentRoleId = null;
        let currentRoleName = null;

        function initRoles() {
            console.log('Initializing roles page');
            console.log('Modal element exists:', !!document.getElementById('reassignUsersModal'));
            console.log('Bootstrap available:', typeof bootstrap !== 'undefined');

            // Handle role deletion
            console.log('Setting up role deletion handlers');
            const deleteButtons = document.querySelectorAll('.delete-role');
            console.log('Found delete buttons:', deleteButtons.length);

            deleteButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    console.log('Delete button clicked');
                    const roleId = this.dataset.role;
                    const roleName = this.dataset.name;
                    const userCount = parseInt(this.dataset.userCount);

                    console.log('Role data:', { roleId, roleName, userCount });

                    currentRoleId = roleId;
                    currentRoleName = roleName;

                    if (userCount === 0) {
                        // Direct deletion for roles with no users
                        console.log('Direct deletion for role with 0 users');
                        if (confirm('Are you sure you want to delete the role "' + roleName + '"?\n\nThis action cannot be undone.')) {
                            deleteRole(roleId);
                        }
                    } else {
                        // Show reassignment modal for roles with users
                        console.log('Showing modal for role with users');
                        showReassignmentModal(roleId, roleName);
                    }
                });
            });

            // Handle role selection change in modal
            const roleSelect = document.getElementById('new-role-select');
            const confirmButton = document.getElementById('confirm-role-delete');

            if (roleSelect && confirmButton) {
                roleSelect.addEventListener('change', function() {
                    confirmButton.disabled = !this.value;
                });

                // Handle confirm deletion with reassignment
                confirmButton.addEventListener('click', function() {
                    const newRoleId = roleSelect.value;
                    if (newRoleId && currentRoleId) {
                        deleteRoleWithReassignment(currentRoleId, newRoleId);
                    }
                });
            }
        }

        function showReassignmentModal(roleId, roleName) {
            console.log('Showing reassignment modal for role:', roleId, roleName);

            // Check if modal element exists
            const modalElement = document.getElementById('reassignUsersModal');
            if (!modalElement) {
                console.error('Modal element not found!');
                return;
            }

            // Update modal title
            const titleElement = document.getElementById('reassignUsersModalLabel');
            if (titleElement) {
                titleElement.textContent = 'Delete Role "' + roleName + '" - Reassign Users';
            }

            // Load available roles and users
            Promise.all([
                loadAvailableRoles(roleId),
                loadRoleUsers(roleId)
            ]).then(() => {
                console.log('Data loaded, showing modal');
                // Show modal
                try {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                } catch (error) {
                    console.error('Error showing modal:', error);
                    // Fallback to jQuery if Bootstrap 5 doesn't work
                    if (typeof $ !== 'undefined') {
                        $(modalElement).modal('show');
                    }
                }
            }).catch(error => {
                console.error('Error loading modal data:', error);
            });
        }

        function loadAvailableRoles(excludeRoleId) {
            return fetch(json_strings.uri.base + 'process.php?do=get_roles_for_reassignment&exclude_role=' + excludeRoleId)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('new-role-select');
                    select.innerHTML = '<option value="">Select a role...</option>';

                    if (data.roles) {
                        data.roles.forEach(role => {
                            const option = document.createElement('option');
                            option.value = role.id;
                            option.textContent = role.name;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading roles:', error);
                });
        }

        function loadRoleUsers(roleId) {
            return fetch(json_strings.uri.base + 'process.php?do=get_role_users&role_id=' + roleId)
                .then(response => response.json())
                .then(data => {
                    const usersList = document.getElementById('users-list');

                    if (data.users && data.users.length > 0) {
                        const userItems = data.users.map(user =>
                            `<div class="d-flex align-items-center mb-2">
                                <i class="fa fa-user me-2"></i>
                                <strong>${user.name}</strong>
                                <span class="text-muted ms-2">(${user.user})</span>
                            </div>`
                        ).join('');
                        usersList.innerHTML = userItems;
                    } else {
                        usersList.innerHTML = '<em class="text-muted">No users found</em>';
                    }
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                    document.getElementById('users-list').innerHTML = '<em class="text-danger">Error loading users</em>';
                });
        }

        function deleteRole(roleId) {
            submitRoleDeletion(roleId);
        }

        function deleteRoleWithReassignment(roleId, newRoleId) {
            submitRoleDeletion(roleId, newRoleId);
        }

        function submitRoleDeletion(roleId, newRoleId = null) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = json_strings.uri.base + 'process.php?do=delete_role';

            const existingCsrfInput = document.querySelector('input[name="csrf_token"]');
            const csrfValue = existingCsrfInput ? existingCsrfInput.value : '';

            let formHTML = `
                <input type="hidden" name="role_id" value="${roleId}">
                <input type="hidden" name="csrf_token" value="${csrfValue}">
            `;

            if (newRoleId) {
                formHTML += `<input type="hidden" name="reassign_to_role" value="${newRoleId}">`;
            }

            form.innerHTML = formHTML;
            document.body.appendChild(form);
            form.submit();
        }

        // Check if DOM is already loaded or wait for it
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initRoles);
        } else {
            initRoles();
        }
    };
})();