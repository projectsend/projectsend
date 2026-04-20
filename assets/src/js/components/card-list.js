(function () {
    'use strict';

    /**
     * Modern Card List Component
     * Replaces FooTable functionality with enhanced UX
     */
    class CardList {
        constructor(container) {
            this.container = container;
            this.checkboxes = container.querySelectorAll('.card-checkbox');
            this.selectAllCheckbox = document.querySelector('#select_all');
            this.cardSelectAllBtn = document.querySelector('#shared-select-all');
            this.batchBar = container.querySelector('.card-list-batch-bar');
            this.batchCount = container.querySelector('.batch-count');
            this.cards = container.querySelectorAll('.card-list-item');

            this.selectedItems = new Set();
            this.isInitialized = false;

            this.init();
        }

        init() {
            if (this.isInitialized) return;

            this.setupEventListeners();
            this.setupKeyboardNavigation();
            this.updateBatchState();
            this.isInitialized = true;

            // Add loading state removal
            this.container.classList.remove('card-list-loading');
        }

        setupEventListeners() {
            // Individual checkbox handling
            this.checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', (e) => {
                    this.handleCheckboxChange(e.target);
                });

                // Add card click to select
                const card = checkbox.closest('.card-list-item');
                if (card) {
                    card.addEventListener('click', (e) => {
                        // Only trigger if not clicking on buttons or links
                        if (!e.target.closest('.btn') && !e.target.closest('a') &&
                            !e.target.closest('.card-checkbox-container')) {
                            checkbox.checked = !checkbox.checked;
                            this.handleCheckboxChange(checkbox);
                        }
                    });
                }
            });

            // Select all checkbox (traditional table header)
            if (this.selectAllCheckbox) {
                this.selectAllCheckbox.addEventListener('change', (e) => {
                    this.handleSelectAll(e.target.checked);
                });
            }

            // Note: Card view select all button is now handled by SharedControlBar component
            // No event listener needed here as SharedControlBar handles the #shared-select-all button

            // Batch operations
            this.setupBatchOperations();

            // Add hover effects
            this.setupHoverEffects();
        }

        setupKeyboardNavigation() {
            document.addEventListener('keydown', (e) => {
                if (!this.isCardListFocused()) return;

                switch (e.key) {
                    case 'Escape':
                        this.clearSelection();
                        break;
                    case 'a':
                        if (e.ctrlKey || e.metaKey) {
                            e.preventDefault();
                            this.selectAll();
                        }
                        break;
                }
            });
        }

        setupHoverEffects() {
            this.cards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.classList.add('card-hover');
                });

                card.addEventListener('mouseleave', () => {
                    card.classList.remove('card-hover');
                });
            });
        }

        setupBatchOperations() {
            // Add batch operation buttons dynamically based on existing batch operations
            const existingBatchForm = document.querySelector('form[action*="batch"]');
            if (existingBatchForm && this.batchBar) {
                const batchActions = this.batchBar.querySelector('.batch-actions');
                if (batchActions) {
                    // Copy existing batch buttons and modify them
                    const existingButtons = existingBatchForm.querySelectorAll('.btn');
                    existingButtons.forEach(btn => {
                        if (btn.type === 'submit') {
                            const newBtn = btn.cloneNode(true);
                            newBtn.classList.add('btn-sm');
                            newBtn.addEventListener('click', (e) => {
                                e.preventDefault();
                                this.executeBatchOperation(btn.name, btn.value);
                            });
                            batchActions.appendChild(newBtn);
                        }
                    });
                }
            }
        }

        handleCheckboxChange(checkbox) {
            const card = checkbox.closest('.card-list-item');
            const value = checkbox.value;

            // Add selection animation
            card.classList.add('selecting');
            setTimeout(() => card.classList.remove('selecting'), 150);

            if (checkbox.checked) {
                this.selectedItems.add(value);
                card.classList.add('selected');
            } else {
                this.selectedItems.delete(value);
                card.classList.remove('selected');
            }

            this.updateBatchState();
            this.updateSelectAllState();
        }

        handleSelectAll(checked) {
            this.checkboxes.forEach(checkbox => {
                const wasChecked = checkbox.checked;
                checkbox.checked = checked;

                if (wasChecked !== checked) {
                    this.handleCheckboxChange(checkbox);
                }
            });
        }

        updateBatchState() {
            const selectedCount = this.selectedItems.size;

            if (selectedCount > 0) {
                this.showBatchBar();
                this.updateBatchCount(selectedCount);
                this.container.classList.add('batch-mode');
            } else {
                this.hideBatchBar();
                this.container.classList.remove('batch-mode');
            }
        }

        updateBatchCount(count) {
            if (this.batchCount) {
                const text = count === 1 ?
                    `${count} item selected` :
                    `${count} items selected`;
                this.batchCount.textContent = text;
            }
        }

        updateSelectAllState() {
            const totalCheckboxes = this.checkboxes.length;
            const selectedCount = this.selectedItems.size;

            // Update traditional select all checkbox
            if (this.selectAllCheckbox) {
                if (selectedCount === 0) {
                    this.selectAllCheckbox.checked = false;
                    this.selectAllCheckbox.indeterminate = false;
                } else if (selectedCount === totalCheckboxes) {
                    this.selectAllCheckbox.checked = true;
                    this.selectAllCheckbox.indeterminate = false;
                } else {
                    this.selectAllCheckbox.checked = false;
                    this.selectAllCheckbox.indeterminate = true;
                }
            }

            // Update card select all button
            if (this.cardSelectAllBtn) {
                const icon = this.cardSelectAllBtn.querySelector('i');
                const text = this.cardSelectAllBtn.querySelector('span');

                if (selectedCount === 0) {
                    // None selected
                    this.cardSelectAllBtn.classList.remove('active');
                    if (icon) icon.className = 'fa fa-square-o';
                    if (text) text.textContent = 'Select All';
                } else if (selectedCount === totalCheckboxes) {
                    // All selected
                    this.cardSelectAllBtn.classList.add('active');
                    if (icon) icon.className = 'fa fa-check-square-o';
                    if (text) text.textContent = 'Deselect All';
                } else {
                    // Some selected
                    this.cardSelectAllBtn.classList.add('active');
                    if (icon) icon.className = 'fa fa-minus-square-o';
                    if (text) text.textContent = `Deselect (${selectedCount})`;
                }
            }
        }

        showBatchBar() {
            if (this.batchBar) {
                this.batchBar.style.display = 'block';
                // Trigger reflow for animation
                this.batchBar.offsetHeight;
                this.batchBar.classList.add('show');
            }
        }

        hideBatchBar() {
            if (this.batchBar) {
                this.batchBar.classList.remove('show');
                setTimeout(() => {
                    this.batchBar.style.display = 'none';
                }, 300);
            }
        }

        selectAll() {
            if (this.selectAllCheckbox) {
                this.selectAllCheckbox.checked = true;
                this.handleSelectAll(true);
            }
        }

        clearSelection() {
            this.selectedItems.clear();
            this.checkboxes.forEach(checkbox => {
                checkbox.checked = false;
                const card = checkbox.closest('.card-list-item');
                card.classList.remove('selected');
            });
            this.updateBatchState();
            this.updateSelectAllState();
        }

        executeBatchOperation(action, value) {
            if (this.selectedItems.size === 0) {
                return;
            }

            // Create form data
            const formData = new FormData();
            formData.append(action, value);

            this.selectedItems.forEach(item => {
                formData.append('batch[]', item);
            });

            // Add loading state
            this.container.classList.add('card-list-loading');

            // Submit the form (using existing form submission logic)
            const existingForm = document.querySelector('form[action*="batch"]');
            if (existingForm) {
                // Clear existing batch checkboxes
                existingForm.querySelectorAll('input[name="batch[]"]').forEach(input => {
                    input.remove();
                });

                // Add selected items to form
                this.selectedItems.forEach(item => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'batch[]';
                    input.value = item;
                    existingForm.appendChild(input);
                });

                // Add action button
                const actionBtn = document.createElement('input');
                actionBtn.type = 'hidden';
                actionBtn.name = action;
                actionBtn.value = value;
                existingForm.appendChild(actionBtn);

                // Submit form
                existingForm.submit();
            }
        }

        isCardListFocused() {
            const activeElement = document.activeElement;
            return this.container.contains(activeElement) ||
                   activeElement === document.body;
        }

        // Public API
        getSelectedItems() {
            return Array.from(this.selectedItems);
        }

        setSelectedItems(items) {
            this.clearSelection();
            items.forEach(item => {
                const checkbox = this.container.querySelector(`input[value="${item}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    this.handleCheckboxChange(checkbox);
                }
            });
        }

        refresh() {
            this.clearSelection();
            this.checkboxes = this.container.querySelectorAll('.card-checkbox');
            this.cardSelectAllBtn = this.container.querySelector('#card-select-all');
            this.cards = this.container.querySelectorAll('.card-list-item');
            this.setupEventListeners();
        }
    }

    /**
     * Auto-initialize card lists on page load
     */
    function initializeCardLists() {
        const cardLists = document.querySelectorAll('.card-list');
        cardLists.forEach(container => {
            if (!container.cardListInstance) {
                container.cardListInstance = new CardList(container);
            }
        });
    }

    /**
     * Enhanced animations and transitions
     */
    function setupCardAnimations() {
        // Intersection Observer for entrance animations
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            document.querySelectorAll('.card-list-item').forEach(card => {
                observer.observe(card);
            });
        }
    }

    /**
     * Smooth scrolling for batch operations
     */
    function setupSmoothScrolling() {
        const batchBars = document.querySelectorAll('.card-list-batch-bar');
        batchBars.forEach(bar => {
            if (bar.classList.contains('show')) {
                bar.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initializeCardLists();
            setupCardAnimations();
        });
    } else {
        initializeCardLists();
        setupCardAnimations();
    }

    // Re-initialize on dynamic content changes
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        const cardList = node.querySelector ? node.querySelector('.card-list') : null;
                        if (cardList && !cardList.cardListInstance) {
                            cardList.cardListInstance = new CardList(cardList);
                        }
                    }
                });
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Export for external use
    window.CardList = CardList;

})();