(function () {
    'use strict';

    admin.pages.import_external = function () {
        const selectAllCheckbox = document.getElementById('select_all_checkbox');
        const fileCheckboxes = document.querySelectorAll('.file_checkbox');
        const selectAllBtn = document.getElementById('select_all');
        const selectNoneBtn = document.getElementById('select_none');

        // Handle select all checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                fileCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }

        // Handle select all button
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                fileCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
                if (selectAllCheckbox) selectAllCheckbox.checked = true;
            });
        }

        // Handle select none button
        if (selectNoneBtn) {
            selectNoneBtn.addEventListener('click', function() {
                fileCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
            });
        }

        // Update select all checkbox when individual checkboxes change
        fileCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (selectAllCheckbox) {
                    const checkedCount = document.querySelectorAll('.file_checkbox:checked').length;
                    selectAllCheckbox.checked = (checkedCount === fileCheckboxes.length);
                    selectAllCheckbox.indeterminate = (checkedCount > 0 && checkedCount < fileCheckboxes.length);
                }
            });
        });
    };
})();