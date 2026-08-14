import { Pencil, X } from 'lucide-react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { type FolderRow } from '@/types/portal';

interface FolderRowActionsProps {
    folder: FolderRow;
    onRename: (folder: FolderRow) => void;
    onDelete: (id: number) => void;
    size?: 'default' | 'sm' | 'icon';
}

/**
 * The rename and delete controls that sit on a folder row.
 *
 * Each is gated on the row's own permissions, which the server decides per
 * folder — a client can manage the folders they created but not the ones
 * shared with them, and both kinds appear in the same list.
 */
export function FolderRowActions({ folder, onRename, onDelete, size = 'sm' }: FolderRowActionsProps) {
    const { t } = useTranslation();

    return (
        <>
            {folder.can_update && (
                <Button variant="ghost" size={size} onClick={() => onRename(folder)}>
                    <Pencil className="size-4" />
                    <span className="sr-only">{t('Rename')}</span>
                </Button>
            )}
            {folder.can_delete && (
                <ConfirmDialog
                    trigger={
                        <Button variant="ghost" size={size} className="text-destructive hover:text-destructive">
                            <X className="size-4" />
                            <span className="sr-only">{t('Delete')}</span>
                        </Button>
                    }
                    title={t('Delete folder?')}
                    description={t('The folder ":name" and everything inside it will be deleted. This can be undone by an administrator.', {
                        name: folder.name,
                    })}
                    confirmLabel={t('Delete folder')}
                    onConfirm={() => onDelete(folder.id)}
                />
            )}
        </>
    );
}
