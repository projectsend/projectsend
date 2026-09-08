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
    /**
     * Whether this client may edit / delete this row, decided per file by
     * FilePolicy. Not the same question as `is_mine`: holding the file is
     * half of it and the role's keys are the other half, so a theme reads
     * these and never derives them.
     */
    can_update: boolean;
    can_delete: boolean;
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
    /**
     * The public URL for a file of this client's own, where the install
     * made one. Null on a file somebody shared with them — that link is
     * the person who shared it's decision about who may reach the file,
     * and handing the recipient the URL would turn "you may download
     * this" into "you may pass this on to anyone". The server decides;
     * a theme must never derive this from `is_mine`.
     */
    share_url: string | null;
    /**
     * How often this file has been downloaded and when it last was —
     * present only on files this client uploaded. Null means "not yours
     * to know", never "nobody has": a count on a file shared with several
     * clients would tell each of them about the others' activity, so the
     * server sends nothing rather than a zero.
     */
    downloads: { count: number; last_at: string | null } | null;
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
    /**
     * Whether this install lets clients look at a file as well as take it
     * (Setting::ClientsCanPreviewFiles). Per page, not per file: which
     * types can be shown is decided from the row's mime type by
     * previewKind(), so all a theme needs from the server is whether the
     * affordance is offered here at all. With it false a row is exactly
     * what it was before preview existed — a name and a download.
     */
    preview_enabled: boolean;
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
