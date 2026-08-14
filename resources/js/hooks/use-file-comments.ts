import { useCallback, useEffect, useState } from 'react';

import { useTranslation } from '@/hooks/use-translation';
import { xsrfToken } from '@/lib/uploads-api';

export interface Comment {
    id: number;
    body: string;
    author_name: string;
    author_type: 'staff' | 'client' | 'guest';
    is_mine: boolean;
    visibility: string;
    visibility_label: string;
    /** Whose conversation this is, when it is one client's. Staff only. */
    conversation: string | null;
    can_reply: boolean;
    pending: boolean;
    created_at: string | null;
    edited_at: string | null;
    can_update: boolean;
    can_delete: boolean;
    can_approve: boolean;
}

export interface CommentVisibilityOption {
    value: string;
    label: string;
    description: string;
    /** False when this file or this installation does not allow it. */
    available: boolean;
    /** Why not, when it isn't. */
    reason: string | null;
}

interface Payload {
    comments: Comment[];
    can_comment: boolean;
    /** Why not, when not — a sentence to show where the composer would be. */
    cannot_comment_reason: string | null;
    is_guest: boolean;
    guest_moderated: boolean;
    /** Whether this author has to solve a security check before posting. */
    captcha_required: boolean;
    visibilities: CommentVisibilityOption[];
    default_visibility: string | null;
    edit_window_minutes: number;
}

/**
 * Everything a comment thread does that is not markup: loading it,
 * writing to it, and holding the draft.
 *
 * Every theme renders comments differently and that is the whole point of
 * themes — but the behaviour is identical everywhere, so it lives here
 * once, the same split the files toolbar already uses (a shared
 * `<PortalFilesFilterFields>`, four themed shells around it). **No theme
 * or page should ever call fetch() for comments.**
 *
 * `endpoint` is the only thing that differs between surfaces: the
 * authenticated one is /files/{id}/comments, the public page has its own
 * under the listing slug. The shapes they return are the same.
 */
export function useFileComments({
    fileId,
    endpoint,
    enabled = true,
    onChanged,
}: {
    fileId?: number;
    endpoint?: string;
    enabled?: boolean;
    /** Called after any successful write, for whatever else went stale. */
    onChanged?: () => void;
}) {
    // One of the two is always present: the authenticated surfaces pass a
    // file id, the public page passes its own endpoint (and deliberately
    // does not expose the id, which no public URL otherwise reveals).
    const base = endpoint ?? `/files/${fileId}/comments`;

    // The only string this hook writes itself rather than passing on from
    // the server, so it is the only one it has to translate. Everything in
    // `error` is rendered through t() again by the composer, which is a
    // no-op on an already-translated sentence.
    const { t } = useTranslation();

    const [comments, setComments] = useState<Comment[]>([]);
    const [visibilities, setVisibilities] = useState<CommentVisibilityOption[]>([]);
    const [canComment, setCanComment] = useState(false);
    const [cannotCommentReason, setCannotCommentReason] = useState<string | null>(null);
    const [isGuest, setIsGuest] = useState(false);
    const [captchaRequired, setCaptchaRequired] = useState(false);
    const [captchaResetSignal, setCaptchaResetSignal] = useState(0);
    const [guestModerated, setGuestModerated] = useState(false);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [draft, setDraft] = useState('');
    const [guestName, setGuestName] = useState('');
    const [visibility, setVisibility] = useState<string>('');
    // The comment being answered, if any. Set by clicking Reply, never
    // chosen from a list — a reply inherits its audience from here.
    const [replyTo, setReplyTo] = useState<Comment | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editDraft, setEditDraft] = useState('');

    const apply = useCallback((payload: Payload) => {
        setComments(payload.comments);
        setVisibilities(payload.visibilities);
        setCanComment(payload.can_comment);
        setCannotCommentReason(payload.cannot_comment_reason);
        setIsGuest(payload.is_guest);
        setCaptchaRequired(payload.captcha_required);
        setGuestModerated(payload.guest_moderated);
        // Keep whatever the author already picked if it is still on offer;
        // otherwise take the server's default, which is the conversational
        // audience rather than the narrowest one — see
        // CommentingRules::defaultVisibility for why that way round.
        setVisibility((current) => {
            // Only an available option can be the selection — the others
            // are shown to explain themselves, not to be picked.
            const options = payload.visibilities.filter((option) => option.available).map((option) => option.value);
            if (current !== '' && options.includes(current)) return current;

            return payload.default_visibility ?? options[0] ?? '';
        });
    }, []);

    const send = useCallback(
        async (url: string, method: string, body?: Record<string, unknown>) => {
            setSaving(true);
            setError(null);

            // Bumped on every way out that is not a success, so the
            // composer redraws its challenge. A captcha token is spent by
            // the attempt that carried it, whatever the attempt's own fate
            // was, and reusing one is refused as "already used" — which
            // reads to the person as an accusation about something they
            // did not do.
            const failed = () => setCaptchaResetSignal((value) => value + 1);

            try {
                const response = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        // The CSRF cookie, whose name this installation
                        // chooses (see lib/xsrf) — never a csrf-token meta
                        // tag, which this application does not render, so a
                        // header read from one is empty and every write
                        // 419s. Tests do not catch it: Laravel skips CSRF
                        // verification while running them.
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: body === undefined ? undefined : JSON.stringify(body),
                });

                // 429 is the one status whose body is worse than useless:
                // the framework's "Too Many Attempts." is raw English with
                // no catalogue entry, and it tells the person neither what
                // they did nor when they may try again. The header does.
                if (response.status === 429) {
                    failed();

                    const seconds = Number(response.headers.get('Retry-After'));

                    setError(
                        Number.isFinite(seconds) && seconds > 0
                            ? t('You are commenting too quickly. Try again in :seconds seconds.', { seconds })
                            : t('You are commenting too quickly. Try again in a moment.'),
                    );

                    return false;
                }

                const payload = await response.json();

                if (!response.ok) {
                    failed();

                    // 422 puts the reason under `errors`; 403 and friends
                    // put it under `message`.
                    const messages = payload?.errors ? Object.values(payload.errors).flat() : [];
                    setError((messages[0] as string) ?? payload?.message ?? 'Something went wrong.');

                    return false;
                }

                apply(payload as Payload);
                onChanged?.();

                return true;
            } catch {
                failed();
                setError('Something went wrong.');

                return false;
            } finally {
                setSaving(false);
            }
        },
        [apply, onChanged, t],
    );

    useEffect(() => {
        if (!enabled) return;

        let cancelled = false;
        setLoading(true);

        fetch(base, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => response.json())
            .then((payload: Payload) => {
                if (!cancelled) apply(payload);
            })
            .catch(() => {
                if (!cancelled) setError('Something went wrong.');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [base, enabled, apply]);

    /**
     * `extra` carries the captcha token, handed straight in by the
     * composer rather than staged in state first — there is no setState
     * between minting it and sending it, so it cannot be a request behind.
     */
    const post = async (extra: Record<string, unknown> = {}) => {
        if (draft.trim() === '' || visibility === '') return;

        const ok = await send(base, 'POST', {
            body: draft,
            visibility,
            reply_to: replyTo?.id ?? null,
            guest_name: guestName || null,
            ...extra,
        });

        if (ok) {
            setDraft('');
            setReplyTo(null);
        }
    };

    const startEdit = (comment: Comment) => {
        setEditingId(comment.id);
        setEditDraft(comment.body);
    };

    const cancelEdit = () => {
        setEditingId(null);
        setEditDraft('');
    };

    const saveEdit = async () => {
        if (editingId === null || editDraft.trim() === '') return;

        const ok = await send(`/comments/${editingId}`, 'PATCH', { body: editDraft });

        if (ok) cancelEdit();
    };

    const remove = (id: number) => send(`/comments/${id}`, 'DELETE');

    // Same route the moderation queue posts to — one decision, one place.
    const approve = (id: number) => send(`/comments/${id}/approve`, 'POST');

    return {
        comments,
        visibilities,
        canComment,
        cannotCommentReason,
        isGuest,
        guestModerated,
        captchaRequired,
        captchaResetSignal,
        loading,
        saving,
        error,
        draft,
        setDraft,
        guestName,
        setGuestName,
        visibility,
        setVisibility,
        replyTo,
        setReplyTo,
        editingId,
        editDraft,
        setEditDraft,
        post,
        startEdit,
        cancelEdit,
        saveEdit,
        remove,
        approve,
    };
}

export type FileCommentsController = ReturnType<typeof useFileComments>;
