import { useCallback } from 'react';

export function useMobileNavigation() {
    const cleanup = useCallback(() => {
        // Remove pointer-events style from body...
        document.body.style.removeProperty('pointer-events');
    }, []);

    return cleanup;
}
