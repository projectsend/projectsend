import { useEffect, useState } from 'react';

/**
 * The theme the app has actually applied (the .dark class the
 * appearance system maintains), reactive to the sidebar toggle and to
 * OS changes while in "system" mode.
 */
export function useAppliedTheme(): 'light' | 'dark' {
    const [isDark, setIsDark] = useState(() => document.documentElement.classList.contains('dark'));

    useEffect(() => {
        const observer = new MutationObserver(() => {
            setIsDark(document.documentElement.classList.contains('dark'));
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        return () => observer.disconnect();
    }, []);

    return isDark ? 'dark' : 'light';
}
