import { Link, router } from '@inertiajs/react';
import { Pencil, X } from 'lucide-react';

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
 */
export function FileRowActions({ file, size = 'sm' }: FileRowActionsProps) {
    const { t } = useTranslation();

    return (
        <>
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
