(function () {
    'use strict';

    admin.pages.custom_fields = function () {
        var draggedElement = null;
        var draggedOver = null;

        function initCustomFields() {
            // Handle delete confirmations
            var deleteButtons = document.querySelectorAll('.delete-confirm');

            deleteButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    var deleteUrl = this.getAttribute('href');

                    Swal.fire({
                        title: 'Are you sure?',
                        text: "This will permanently delete this custom field and all its data. This action cannot be undone!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel',
                        showClass: {
                            popup: 'animate__animated animate__fadeIn'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__fadeOut'
                        }
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            window.location.href = deleteUrl;
                        }
                    });
                });
            });

            // Initialize drag and drop functionality
            initDragAndDrop();
        }

        function initDragAndDrop() {
            var tbody = document.querySelector('#custom_fields_tbl tbody');
            if (!tbody) return;

            var rows = tbody.querySelectorAll('tr');

            rows.forEach(function(row, index) {
                // Make the entire row draggable but only when dragging from the handle
                var dragHandle = row.querySelector('.drag-handle');
                if (!dragHandle) {
                    return;
                }

                // Set up drag handle mouse events
                dragHandle.addEventListener('mousedown', function(e) {
                    row.draggable = true;
                });

                // Make sure row is draggable
                row.draggable = true;

                row.addEventListener('dragstart', function(e) {
                    draggedElement = this;
                    this.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', this.outerHTML);
                });

                row.addEventListener('dragend', function(e) {
                    this.classList.remove('dragging');
                    row.draggable = false; // Disable dragging when not actively dragging

                    // Remove all dragover effects
                    var allRows = tbody.querySelectorAll('tr');
                    allRows.forEach(function(r) {
                        r.classList.remove('drag-over');
                    });

                    draggedElement = null;
                    draggedOver = null;
                });

                row.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';

                    if (draggedElement && draggedElement !== this) {
                        // Remove drag-over from all rows first
                        var allRows = tbody.querySelectorAll('tr');
                        allRows.forEach(function(r) {
                            r.classList.remove('drag-over');
                        });

                        // Add to current row
                        this.classList.add('drag-over');
                        draggedOver = this;
                    }
                });

                row.addEventListener('dragleave', function(e) {
                    // Only remove if we're actually leaving the row (not entering a child element)
                    var rect = this.getBoundingClientRect();
                    var x = e.clientX;
                    var y = e.clientY;

                    if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
                        this.classList.remove('drag-over');
                    }
                });

                row.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (draggedElement && draggedElement !== this) {
                        var rect = this.getBoundingClientRect();
                        var insertAfter;

                        // Check if this is the first row in the table
                        var isFirstRow = this.previousElementSibling === null;

                        if (isFirstRow) {
                            // For the first row, use a smaller threshold (top quarter)
                            // This makes it easier to place items at the very beginning
                            insertAfter = e.clientY > rect.top + rect.height / 4;
                        } else {
                            // For other rows, use the middle as threshold
                            insertAfter = e.clientY > rect.top + rect.height / 2;
                        }

                        // Move the dragged element in the DOM
                        if (insertAfter) {
                            this.parentNode.insertBefore(draggedElement, this.nextSibling);
                        } else {
                            this.parentNode.insertBefore(draggedElement, this);
                        }

                        // Update sort order on server
                        updateSortOrder();
                    }

                    // Clean up
                    this.classList.remove('drag-over');
                    var allRows = tbody.querySelectorAll('tr');
                    allRows.forEach(function(r) {
                        r.classList.remove('drag-over');
                    });
                });
            });
        }

        function updateSortOrder() {
            var tbody = document.querySelector('#custom_fields_tbl tbody');
            if (!tbody) return;

            var rows = tbody.querySelectorAll('tr');
            var sortData = [];

            rows.forEach(function(row, index) {
                // Try to get field ID from the row's data attribute first
                var fieldId = parseInt(row.getAttribute('data-field-id'));

                // If not found on row, try the first cell
                if (isNaN(fieldId)) {
                    var firstCell = row.querySelector('.drag-handle-cell');
                    if (firstCell) {
                        fieldId = parseInt(firstCell.getAttribute('data-field-id'));
                    }
                }

                // Last fallback: try the hidden span
                if (isNaN(fieldId)) {
                    var hiddenSpan = row.querySelector('.field-id-hidden');
                    if (hiddenSpan) {
                        fieldId = parseInt(hiddenSpan.textContent);
                    }
                }

                // Only add if we have a valid field ID
                if (!isNaN(fieldId) && fieldId > 0) {
                    sortData.push({
                        id: fieldId,
                        sort_order: index + 1
                    });
                }
            });

            // Show loading indicator
            toastr.info('Updating field order...', '', {
                timeOut: 0,
                extendedTimeOut: 0,
                closeButton: false,
                tapToDismiss: false
            });

            // Send AJAX request to update sort order
            fetch(json_strings.uri.base + 'process.php?do=update_custom_fields_order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    csrf_token: document.getElementById('csrf_token').value,
                    sort_data: sortData
                })
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                toastr.clear();

                if (data.status === 'success') {
                    toastr.success(data.message);
                } else {
                    toastr.error(data.message || 'Failed to update order');

                    // Reload page to restore original order
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                }
            })
            .catch(function(error) {
                toastr.clear();

                toastr.error('Failed to update order. Please try again.');

                // Reload page to restore original order
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            });
        }

        // Check if DOM is already loaded or wait for it
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCustomFields);
        } else {
            initCustomFields();
        }
    };
})();