(function () {
    'use strict';

    /**
     * View Toggle Component
     * Simple component that handles view toggle button states
     * Layout switching is now handled by URL parameters and server-side cookie persistence
     */
    class ViewToggle {
        constructor() {
            this.init();
        }

        init() {
            // View toggle is now handled by simple links
            // No JavaScript interaction needed - the server handles everything via URL params and cookies

            // Optional: Add any visual enhancements like hover effects
            this.setupHoverEffects();
        }

        setupHoverEffects() {
            const toggleButtons = document.querySelectorAll('.view-toggle-buttons a');
            toggleButtons.forEach(button => {
                button.addEventListener('mouseenter', () => {
                    if (!button.classList.contains('btn-primary')) {
                        button.style.transform = 'translateY(-1px)';
                    }
                });

                button.addEventListener('mouseleave', () => {
                    button.style.transform = '';
                });
            });
        }
    }

    // Initialize view toggle when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new ViewToggle();
        });
    } else {
        new ViewToggle();
    }

    // Export for external use
    window.ViewToggle = ViewToggle;

})();