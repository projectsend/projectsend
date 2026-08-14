import { Check, Pencil, Reply, Trash2 } from 'lucide-react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { type FileCommentsController } from '@/hooks/use-file-comments';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The thread itself: who said what, when, and to whom.
 *
 * Part of the shared field set — every theme renders this same component
 * inside its own shell, so a change to how a comment reads happens once.
 * Only the chrome around it is themed.
 */
export function CommentList({ comments: c, dense = false }: { comments: FileCommentsController; dense?: boolean }) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();

    if (c.loading) {
        return <p className="text-muted-foreground py-4 text-sm">{t('Loading…')}</p>;
    }

    if (c.comments.length === 0) {
        // "No comments yet" invites you to write the first one. When you
        // cannot, the composer below states why instead — two sentences
        // where one of them is beside the point is worse than one, so this
        // one steps aside rather than being contradicted underneath.
        if (!c.canComment && c.cannotCommentReason !== null) return null;

        return <p className="text-muted-foreground py-4 text-sm">{t('No comments yet.')}</p>;
    }

    return (
        <ul className={dense ? 'divide-y' : 'space-y-4'}>
            {c.comments.map((comment) => (
                <li
                    key={comment.id}
                    // Held comments read as provisional rather than posted:
                    // the author sees their own here while it waits, and a
                    // moderator sees everyone's, so both need to tell at a
                    // glance which are live.
                    className={`${dense ? 'py-2' : ''} ${comment.pending ? 'opacity-60' : ''}`.trim()}
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-medium">{comment.author_name}</span>

                        {comment.author_type === 'guest' && (
                            <Badge variant="outline" className="text-[10px]">
                                {t('Visitor')}
                            </Badge>
                        )}

                        {/* Staff only, and only when this is one client's
                            conversation rather than every client's. */}
                        {comment.conversation !== null && (
                            <Badge variant="secondary" className="text-[10px]">
                                {t('With :name', { name: comment.conversation })}
                            </Badge>
                        )}

                        <Badge variant="outline" className="text-[10px]">
                            {t(comment.visibility_label)}
                        </Badge>

                        {comment.pending && (
                            <Badge className="bg-amber-500 text-[10px] text-white hover:bg-amber-500">{t('Awaiting approval')}</Badge>
                        )}

                        {comment.created_at && (
                            <span className="text-muted-foreground text-xs">
                                {dateTime(comment.created_at)}
                                {comment.edited_at && ` · ${t('edited')}`}
                            </span>
                        )}
                    </div>

                    {c.editingId === comment.id ? (
                        <div className="mt-2 grid gap-2">
                            <Textarea value={c.editDraft} onChange={(e) => c.setEditDraft(e.target.value)} rows={3} />
                            <div className="flex gap-2">
                                <Button size="sm" onClick={() => void c.saveEdit()} disabled={c.saving}>
                                    {t('Save')}
                                </Button>
                                <Button size="sm" variant="ghost" onClick={c.cancelEdit}>
                                    {t('Cancel')}
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <p className="mt-1 text-sm whitespace-pre-wrap">{comment.body}</p>
                    )}

                    {(comment.can_update || comment.can_delete || comment.can_approve || comment.can_reply) && c.editingId !== comment.id && (
                        <div className="mt-1 flex items-center gap-1">
                            {comment.can_approve && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="h-7 gap-1 px-2 text-xs"
                                    disabled={c.saving}
                                    onClick={() => void c.approve(comment.id)}
                                >
                                    <Check className="size-3.5" />
                                    {t('Approve')}
                                </Button>
                            )}
                            {comment.can_reply && (
                                <Button size="sm" variant="ghost" className="h-7 gap-1 px-2 text-xs" onClick={() => c.setReplyTo(comment)}>
                                    <Reply className="size-3.5" />
                                    {t('Reply')}
                                </Button>
                            )}
                            {comment.can_update && (
                                <Button size="sm" variant="ghost" className="h-7 px-2" onClick={() => c.startEdit(comment)}>
                                    <Pencil className="size-3.5" />
                                    <span className="sr-only">{t('Edit')}</span>
                                </Button>
                            )}
                            {comment.can_delete && (
                                <ConfirmDialog
                                    title={t('Delete this comment?')}
                                    description={t('This cannot be undone.')}
                                    confirmLabel={t('Delete')}
                                    onConfirm={() => void c.remove(comment.id)}
                                    trigger={
                                        <Button size="sm" variant="ghost" className="text-destructive h-7 px-2">
                                            <Trash2 className="size-3.5" />
                                            <span className="sr-only">{t('Delete')}</span>
                                        </Button>
                                    }
                                />
                            )}
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}
