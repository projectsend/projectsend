import { CommentVisibilitySelect } from '@/components/comments/comment-visibility-select';
import { X } from 'lucide-react';
import { useEffect, useRef } from 'react';

import { CaptchaWidget, type CaptchaHandle } from '@/components/captcha-widget';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { type FileCommentsController } from '@/hooks/use-file-comments';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Writing a comment: the body, who it reaches, and — for staff — whose
 * conversation it joins.
 *
 * Part of the shared field set. A theme decides where this sits and what
 * surrounds it, never what it contains: the visibility choice in
 * particular must look and behave the same everywhere, because getting it
 * wrong is how somebody publishes to the world by accident.
 *
 * The name field and the held-for-review notice appear only for an
 * anonymous author, which the payload states — the public page serves
 * signed-in viewers as themselves, so it cannot be a property of the page.
 *
 * When nobody may write here this renders the reason rather than nothing:
 * an absent composer above an empty thread saying "No comments yet" reads
 * as *nobody has commented*, not as *the box is closed*.
 */
export function CommentComposer({ comments: c }: { comments: FileCommentsController }) {
    const { t } = useTranslation();
    const captcha = useRef<CaptchaHandle>(null);

    // The hook bumps this on every failed post. A token is spent by the
    // attempt that carried it, so without a redraw the second try is
    // refused as "already used".
    useEffect(() => {
        if (c.captchaResetSignal > 0) captcha.current?.reset();
    }, [c.captchaResetSignal]);

    if (!c.canComment) {
        // Nothing to say while the thread is still loading — the reason
        // arrives with the payload, and flashing one before it would be a
        // guess.
        if (c.loading || c.cannotCommentReason === null) return null;

        return <p className="text-muted-foreground text-sm">{t(c.cannotCommentReason)}</p>;
    }

    // Whether the author has an account is the server's answer, not the
    // page's: the public file page serves signed-in viewers too, and
    // asking one of them for their name would be both wrong and a lie
    // about what happens to their comment next.
    const guest = c.isGuest;

    const empty = c.draft.trim() === '' || (guest && c.guestName.trim() === '');

    // The token is minted here and handed straight to post(), with no
    // state in between — the endpoint is a fetch() carrying a JSON body,
    // so there is nothing to stage it in that would be in step anyway.
    const submit = async () => {
        const token = await captcha.current?.execute();

        await c.post(token ? { captcha_token: token } : {});
    };

    return (
        <div className="grid gap-3">
            {c.replyTo !== null && (
                <div className="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-xs">
                    <span className="truncate">{t('Replying to :name', { name: c.replyTo.author_name })}</span>
                    <Button size="sm" variant="ghost" className="h-6 px-2" onClick={() => c.setReplyTo(null)}>
                        <X className="size-3.5" />
                        <span className="sr-only">{t('Cancel')}</span>
                    </Button>
                </div>
            )}

            {guest && (
                <div className="grid gap-1.5">
                    <Label htmlFor="comment-guest-name" className="text-xs font-normal">
                        {t('Your name')}
                    </Label>
                    <Input id="comment-guest-name" value={c.guestName} onChange={(e) => c.setGuestName(e.target.value)} maxLength={80} required />
                </div>
            )}

            <Textarea value={c.draft} onChange={(e) => c.setDraft(e.target.value)} placeholder={t('Write a comment…')} rows={3} maxLength={5000} />

            {/* A reply has no audience to choose: it goes exactly where the
                comment it answers went, which is the only way a comment
                becomes addressed to one client rather than all of them. */}
            {c.replyTo === null ? (
                <CommentVisibilitySelect value={c.visibility} options={c.visibilities} onChange={c.setVisibility} />
            ) : (
                <p className="text-muted-foreground text-xs">
                    {c.replyTo.conversation === null
                        ? t('Your reply goes to the same people as the comment above.')
                        : t('Your reply goes to :name only, and to staff.', { name: c.replyTo.conversation })}
                </p>
            )}

            {guest && c.guestModerated && <p className="text-muted-foreground text-xs">{t('Your comment will be reviewed before it appears.')}</p>}

            {c.captchaRequired && <CaptchaWidget ref={captcha} action="comment" />}

            {c.error && <p className="text-destructive text-xs">{t(c.error)}</p>}

            <div>
                <Button size="sm" onClick={() => void submit()} disabled={c.saving || empty}>
                    {t('Post comment')}
                </Button>
            </div>
        </div>
    );
}
