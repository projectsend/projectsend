import { router } from '@inertiajs/react';

import { CommentComposer } from '@/components/comments/comment-composer';
import { CommentList } from '@/components/comments/comment-list';
import { useFileComments } from '@/hooks/use-file-comments';

/**
 * A whole comment thread — the list and the composer — with the hook
 * already wired.
 *
 * This is the piece a theme shell wraps in its own chrome, and the piece
 * the staff details panel drops straight in. A shell decides whether it is
 * a dialog, a sheet, a collapsible row or an inline block; it never
 * decides what a comment looks like or how one is written.
 */
export function CommentThread({
    fileId,
    endpoint,
    enabled = true,
    dense = false,
}: {
    fileId?: number;
    endpoint?: string;
    enabled?: boolean;
    dense?: boolean;
}) {
    // Comment counts, unread dots and pending badges are rendered from the
    // page's own props, not from this component — so a comment posted here
    // leaves the row behind it showing a stale number until something
    // re-fetches them. Doing it once here means no surface has to remember
    // to; the alternative was the same callback threaded through four theme
    // shells and the details panel.
    //
    // No `only:` — the prop holding the counts is named differently on each
    // surface (and the public page has none), and one refetch per posted
    // comment is not worth knowing all of them.
    const comments = useFileComments({ fileId, endpoint, enabled, onChanged: () => router.reload() });

    return (
        <div className="grid gap-4">
            <CommentList comments={comments} dense={dense} />
            <CommentComposer comments={comments} />
        </div>
    );
}
