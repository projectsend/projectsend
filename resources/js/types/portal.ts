import { type CategoryTag } from '@/components/files/category-badges';
import { type VersionLinks } from '@/components/files/version-badge';
import { type PaginationMeta } from '@/components/pagination';

export type { CategoryTag };

/**
 * The props MyFilesController sends to whichever theme is active, and the
 * row shapes inside them.
 *
 * Every public/portal theme renders the same data — what differs is how it
 * looks — so these live here rather than being restated per theme, where
 * four copies would only ever agree by luck.
 */

export interface Crumb {
    id: number;
    name: string;
}

export interface FolderRow {
    id: number;
    name: string;
    is_mine: boolean;
    /** Effective: the folder's own flag, or inherited from a public ancestor. */
    public: boolean;
    can_update: boolean;
    can_delete: boolean;
}

export interface FileRow {
    id: number;
    name: string;
    description: string | null;
    original_name: string;
    mime_type: string;
    size: number;
    created_at: string | null;
    is_mine: boolean;
    public: boolean;
    /** Comments this client can see on the file — the number on its row. */
    comments_count: number;
    /** How many of those they have not read yet. */
    unread_comments_count: number;
    /**
     * What this file replaces and what replaced it — already narrowed by
     * the controller to the counterparts this client may be told about, so
     * a null end means "no such version, or not yours to know". A theme
     * renders it or not; it must never filter it.
     */
    version: VersionLinks;
    categories: CategoryTag[];
    /**
     * The download cap on this file, already decided for this client by
     * DownloadAllowance — `blocked` means they may no longer take a copy
     * and `left` is how many they have. Both are null when the file is
     * uncapped for them, which includes a file they uploaded themselves.
     *
     * A theme renders it and never works it out: the per-user scope and
     * the uploader's exemption are server-side rules.
     */
    download_limit: DownloadLimit;
}

export interface DownloadLimit {
    limit: number | null;
    left: number | null;
    blocked: boolean;
}

export interface MyFilesProps {
    folder: Crumb | null;
    breadcrumb: Crumb[];
    folders: FolderRow[];
    files: FileRow[];
    pagination: PaginationMeta;
    search: string;
    searching: boolean;
    category: number | null;
    categories: CategoryTag[];
    owner: 'mine' | 'shared' | null;
    sort: 'name' | 'size' | 'date';
    direction: 'asc' | 'desc';
    can_upload: boolean;
    /**
     * Whether this install has commenting switched on at all. Every theme
     * must gate its comment affordance on this — a file row must not offer
     * a conversation the settings have turned off.
     */
    comments_enabled: boolean;
}

/**
 * The controller sends these to every theme, but only themes that actually
 * offer folder management read them — today just `default`. Kept separate so
 * that asymmetry is visible in the types rather than being something you only
 * discover by grepping.
 */
export interface MyFilesFolderManagementProps extends MyFilesProps {
    can_upload_here: boolean;
    can_create_folders: boolean;
}

/** The combined sort+direction choices offered by the toolbar's one select. */
export const SORT_OPTIONS = [
    ['date-desc', 'Newest first'],
    ['date-asc', 'Oldest first'],
    ['name-asc', 'Name (A–Z)'],
    ['name-desc', 'Name (Z–A)'],
    ['size-desc', 'Largest first'],
    ['size-asc', 'Smallest first'],
] as const;
