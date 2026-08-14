import { useEffect, useState } from 'react';

export type ViewMode = 'list' | 'grid';

// Shared across every file-listing surface (staff /files, client /my-files)
// deliberately — same as Google Drive, a user's list/grid preference isn't
// per-page, it's how they always want to see their files.
const STORAGE_KEY = 'file-view-mode';

export function useViewMode() {
    const [viewMode, setViewModeState] = useState<ViewMode>('list');

    useEffect(() => {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'list' || saved === 'grid') setViewModeState(saved);
    }, []);

    const setViewMode = (mode: ViewMode) => {
        setViewModeState(mode);
        localStorage.setItem(STORAGE_KEY, mode);
    };

    return { viewMode, setViewMode };
}
