import { type InertiaFormProps } from '@inertiajs/react';
import { FolderPlus } from 'lucide-react';
import { type FormEventHandler } from 'react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useTranslation } from '@/hooks/use-translation';

interface NewFolderButtonProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    form: InertiaFormProps<{ name: string; parent_id: number | null }>;
    onSubmit: FormEventHandler;
    /** Whether this client may create folders at all. */
    canCreate: boolean;
    /** Creating a folder needs upload permission too; without it the button explains itself. */
    canUpload: boolean;
    /** Hidden while a search or filter is showing a flat view, where "here" has no meaning. */
    searching: boolean;
    size?: 'default' | 'sm';
}

/**
 * The "New folder" button and the dialog behind it.
 *
 * Renders a disabled button with an explanation rather than nothing when the
 * client may create folders but lacks the upload permission it also needs —
 * silently hiding the control would leave them unable to tell why.
 */
export function NewFolderButton({ open, onOpenChange, form, onSubmit, canCreate, canUpload, searching, size = 'default' }: NewFolderButtonProps) {
    const { t } = useTranslation();

    if (!canCreate || searching) {
        return null;
    }

    if (!canUpload) {
        return (
            <TooltipProvider delayDuration={0}>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span>
                            <Button variant="outline" size={size} disabled>
                                <FolderPlus className="size-4" />
                                {t('New folder')}
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>{t('You need upload permission to create folders')}</TooltipContent>
                </Tooltip>
            </TooltipProvider>
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>
                <Button variant="outline" size={size}>
                    <FolderPlus className="size-4" />
                    {t('New folder')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('New folder')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={onSubmit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="my-folder-name">{t('Name')}</Label>
                        <Input id="my-folder-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus required />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {t('Create folder')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
