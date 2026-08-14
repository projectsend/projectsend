/**
 * The one contract every theme's comment shell implements.
 *
 * Identical props on purpose: a theme page picks its shell by importing
 * one, never by branching on a theme key, and adding a fifth theme means
 * writing one more file that satisfies this — nothing else changes.
 *
 * A shell decides *where* comments appear and what surrounds them (a
 * dialog, a sheet, a collapsible row). It never decides what a comment
 * looks like or how one is written; that is `components/comments/*`,
 * shared by all of them.
 */
export interface CommentsShellProps {
    /** Omitted on the public page, which passes `endpoint` instead. */
    fileId?: number;
    fileName: string;
    /** Defaults to the authenticated endpoint; the public pages pass their own. */
    endpoint?: string;
    /** Comments this viewer can see, for the row affordance. */
    count?: number;
    /** How many of those are new to them. */
    unread?: number;
    /**
     * Render the thread in place rather than behind a trigger. A page
     * about one file — the public file page — should show its
     * conversation, not hide it; a list of many files cannot. Each shell
     * still supplies its own container, so inline stays as themed as the
     * overlay it replaces.
     */
    inline?: boolean;
    /**
     * Open on mount, without a click. Only used when a comment
     * notification deep-links to this file — see
     * App\Modules\Comments\Http\Controllers\CommentDeepLinkController.
     */
    defaultOpen?: boolean;
}
