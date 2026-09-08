import { Link, router } from '@inertiajs/react';
import { Check, Link2, Pencil, X } from 'lucide-react';
import { useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { type FileRow } from '@/types/portal';

interface FileRowActionsProps {
    file: FileRow;
    size?: 'default' | 'sm' | 'icon';
}

/**
 * The edit and delete controls that sit on a file row.
 *
 * The file twin of FolderRowActions, and gated the same way: on the row's
 * own `can_update`/`can_delete`, which the server decides per file. A
 * client manages what they uploaded and not what was shared with them, and
 * both kinds sit in the same list — so this is never `is_mine`, which
 * answers only half the question. The role's keys are the other half.
 *
 * Deliberately no props for the handlers. Renaming a folder is a one-field
 * dialog and each theme owns its own; a file has eight fields behind five
 * separate permissions, so it gets a page (portal/edit-file.tsx) that every
 * theme shares rather than a form each theme would have to carry.
 *
 * The copy-link control follows the same rule as the other two: it appears
 * when the server put a `share_url` on the row and never otherwise. That is
 * not the same question as `is_mine` — a file shared *with* this client can
 * carry a link that is the sharer's to hand out, not the recipient's — so
 * this reads the field and derives nothing.
 */
export function FileRowActions({ file, size = 'sm' }: FileRowActionsProps) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    const copyLink = (url: string) => {
        void navigator.clipboard?.writeText(url);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1500);
    };

    return (
        <>
            {file.share_url !== null && (
                <Button
                    variant="ghost"
                    size={size}
                    onClick={() => copyLink(file.share_url as string)}
                    title={t('Copy the public link to this file')}
                >
                    {copied ? <Check className="size-4" /> : <Link2 className="size-4" />}
                    <span className="sr-only">{copied ? t('Copied') : t('Copy link')}</span>
                </Button>
            )}
            {file.can_update && (
                <Button variant="ghost" size={size} asChild>
                    <Link href={route('my-files.edit', file.id)}>
                        <Pencil className="size-4" />
                        <span className="sr-only">{t('Edit')}</span>
                    </Link>
                </Button>
            )}
            {file.can_delete && (
                <ConfirmDialog
                    trigger={
                        <Button variant="ghost" size={size} className="text-destructive hover:text-destructive">
                            <X className="size-4" />
                            <span className="sr-only">{t('Delete')}</span>
                        </Button>
                    }
                    title={t('Delete file?')}
                    description={t('":name" will be deleted, along with everyone\'s access to it. This can be undone by an administrator.', {
                        name: file.name,
                    })}
                    confirmLabel={t('Delete file')}
                    onConfirm={() => router.delete(route('my-files.destroy', file.id))}
                />
            )}
        </>
    );
}
