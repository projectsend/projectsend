(function () {
    'use strict';

    /**
     * Shared Control Bar Component
     * Handles select all and other controls for both table and card views
     */
    class SharedControlBar {
        constructor() {
            this.selectAllBtn = document.querySelector('#shared-select-all');
            this.currentView = this.getCurrentView();

            this.init();
        }

        init() {
            if (!this.selectAllBtn) return;

            this.setupEventListeners();
            this.updateSelectAllState();
        }

        setupEventListeners() {
            // Shared select all button
            this.selectAllBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleSelectAll();
            });

            // Listen for checkbox changes to update select all state
            document.addEventListener('change', (e) => {
                if (e.target.type === 'checkbox' &&
                    (e.target.classList.contains('card-checkbox') ||
                     e.target.name === 'batch[]')) {
                    this.updateSelectAllState();
                }
            });

            // Listen for view changes
            document.addEventListener('click', (e) => {
                if (e.target.closest('.view-toggle-btn')) {
                    setTimeout(() => {
                        this.currentView = this.getCurrentView();
                        this.updateSelectAllState();
                    }, 100);
                }
            });
        }

        getCurrentView() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('view') === 'cards' ? 'cards' : 'table';
        }

        getCheckboxes() {
            if (this.currentView === 'cards') {
                return document.querySelectorAll('.card-checkbox');
            } else {
                return document.querySelectorAll('input[name="batch[]"]');
            }
        }

        handleSelectAll() {
            const checkboxes = this.getCheckboxes();
            const selectedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            const totalCheckboxes = checkboxes.length;

            // Determine what action to take based on current state
            let shouldCheck;
            if (selectedCount === 0) {
                // Nothing selected - select all
                shouldCheck = true;
            } else if (selectedCount === totalCheckboxes) {
                // All selected - deselect all
                shouldCheck = false;
            } else {
                // Some selected - deselect all (clear selection)
                shouldCheck = false;
            }

            checkboxes.forEach(checkbox => {
                if (checkbox.checked !== shouldCheck) {
                    checkbox.checked = shouldCheck;

                    // Trigger change event for other components
                    const changeEvent = new Event('change', { bubbles: true });
                    checkbox.dispatchEvent(changeEvent);
                }
            });

            this.updateSelectAllState();
        }

        updateSelectAllState() {
            const checkboxes = this.getCheckboxes();
            const totalCheckboxes = checkboxes.length;
            const selectedCount = Array.from(checkboxes).filter(cb => cb.checked).length;

            if (!this.selectAllBtn) return;

            const icon = this.selectAllBtn.querySelector('i');
            const text = this.selectAllBtn.querySelector('span');

            if (selectedCount === 0) {
                // None selected
                this.selectAllBtn.classList.remove('active');
                if (icon) icon.className = 'fa fa-square-o';
                if (text) text.textContent = 'Select All';
            } else if (selectedCount === totalCheckboxes) {
                // All selected
                this.selectAllBtn.classList.add('active');
                if (icon) icon.className = 'fa fa-check-square-o';
                if (text) text.textContent = 'Deselect All';
            } else {
                // Some selected
                this.selectAllBtn.classList.add('active');
                if (icon) icon.className = 'fa fa-minus-square-o';
                if (text) text.textContent = `Deselect (${selectedCount})`;
            }
        }

        refresh() {
            this.currentView = this.getCurrentView();
            this.updateSelectAllState();
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new SharedControlBar();
        });
    } else {
        new SharedControlBar();
    }

    // Export for external use
    window.SharedControlBar = SharedControlBar;

})();