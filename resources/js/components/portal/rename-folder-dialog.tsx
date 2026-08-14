import { type InertiaFormProps } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { type FolderRow } from '@/types/portal';

interface RenameFolderDialogProps {
    /** The folder being renamed, or null when the dialog is closed. */
    folder: FolderRow | null;
    onClose: () => void;
    form: InertiaFormProps<{ name: string }>;
    onSubmit: FormEventHandler;
}

/**
 * Renaming a folder. Lives at page level rather than inside a row, since the
 * row it belongs to can scroll away — or be re-rendered — while it is open.
 */
export function RenameFolderDialog({ folder, onClose, form, onSubmit }: RenameFolderDialogProps) {
    const { t } = useTranslation();

    return (
        <Dialog open={folder !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Rename folder')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={onSubmit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="my-folder-rename">{t('Name')}</Label>
                        <Input
                            id="my-folder-rename"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            autoFocus
                            required
                        />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {t('Save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
