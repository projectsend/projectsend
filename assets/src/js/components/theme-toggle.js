(function () {
    'use strict';

    /**
     * Theme Toggle Component
     * Handles switching between light and dark themes
     */
    class ThemeToggle {
        constructor() {
            this.storageKey = 'projectsend-theme';
            this.toggleButton = document.getElementById('theme-toggle');
            this.darkIcon = document.querySelector('.theme-icon-dark');
            this.lightIcon = document.querySelector('.theme-icon-light');

            this.init();
        }

        init() {
            // Set initial theme based on stored preference or system preference
            this.setInitialTheme();

            // Add event listener to toggle button
            if (this.toggleButton) {
                this.toggleButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleTheme();
                });
            }

            // Listen for system theme changes
            if (window.matchMedia) {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                mediaQuery.addEventListener('change', () => {
                    if (!this.hasStoredPreference()) {
                        this.applyTheme(this.getSystemTheme());
                    }
                });
            }
        }

        setInitialTheme() {
            const storedTheme = this.getStoredTheme();
            const systemTheme = this.getSystemTheme();
            const theme = storedTheme || systemTheme;

            this.applyTheme(theme);
        }

        getStoredTheme() {
            try {
                return localStorage.getItem(this.storageKey);
            } catch (e) {
                return null;
            }
        }

        getSystemTheme() {
            // Always default to light mode, ignoring system preference
            // Uncomment the lines below to respect system preference
            // if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            //     return 'dark';
            // }
            return 'light';
        }

        hasStoredPreference() {
            return this.getStoredTheme() !== null;
        }

        getCurrentTheme() {
            return document.documentElement.getAttribute('data-theme') || 'light';
        }

        toggleTheme() {
            const currentTheme = this.getCurrentTheme();
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            this.applyTheme(newTheme);
            this.storeTheme(newTheme);
        }

        applyTheme(theme) {
            // Apply theme to document
            document.documentElement.setAttribute('data-theme', theme);

            // Update button icons
            this.updateIcons(theme);

            // Update button title
            this.updateButtonTitle(theme);

            // Dispatch theme change event
            this.dispatchThemeChangeEvent(theme);
        }

        updateIcons(theme) {
            if (!this.darkIcon || !this.lightIcon) return;

            if (theme === 'dark') {
                this.darkIcon.style.display = 'none';
                this.lightIcon.style.display = 'block';
            } else {
                this.darkIcon.style.display = 'block';
                this.lightIcon.style.display = 'none';
            }
        }

        updateButtonTitle(theme) {
            if (!this.toggleButton) return;

            const titleText = theme === 'dark'
                ? 'Switch to light theme'
                : 'Switch to dark theme';

            this.toggleButton.setAttribute('title', titleText);
        }

        storeTheme(theme) {
            try {
                localStorage.setItem(this.storageKey, theme);
            } catch (e) {
                console.warn('Failed to store theme preference:', e);
            }
        }

        dispatchThemeChangeEvent(theme) {
            const event = new CustomEvent('themechange', {
                detail: { theme: theme }
            });
            document.dispatchEvent(event);
        }
    }

    // Initialize theme toggle when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new ThemeToggle();
        });
    } else {
        new ThemeToggle();
    }

    // Export for potential external usage
    window.ThemeToggle = ThemeToggle;

})();