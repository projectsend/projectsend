import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { categoryColor } from '@/lib/category-colors';
import { X } from 'lucide-react';
import { ReactNode, useState } from 'react';

export interface BulkEditFolderOption {
    id: number;
    name: string;
}

export interface BulkEditCategory {
    id: number;
    name: string;
    color: string;
}

export interface BulkEditPayload {
    folder_action: 'no_change' | 'move';
    folder_id: number | null;
    description_action: 'no_change' | 'set';
    description: string;
    expiration_action: 'no_change' | 'set' | 'clear';
    expires_at: string;
    download_limit_action: 'no_change' | 'set' | 'clear';
    download_limit: string;
    download_limit_scope: 'total' | 'per_user';
    add_category_ids: number[];
    remove_category_ids: number[];
}

interface BulkEditFilesDialogProps {
    /** The button that opens the dialog. */
    trigger: ReactNode;
    count: number;
    folderOptions: BulkEditFolderOption[];
    categories: BulkEditCategory[];
    canSetCategories: boolean;
    canSetExpiration: boolean;
    canLimitDownloads: boolean;
    onConfirm: (payload: BulkEditPayload) => void;
}

const NO_CHANGE = 'no_change';

/**
 * WordPress-style "Bulk Edit": one shared form applied identically to every
 * selected file. Every field defaults to "no change" so submitting without
 * touching a field never overwrites it — critical for categories, where an
 * empty list must never mean "clear every category."
 */
export function BulkEditFilesDialog({
    trigger,
    count,
    folderOptions,
    categories,
    canSetCategories,
    canSetExpiration,
    canLimitDownloads,
    onConfirm,
}: BulkEditFilesDialogProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const [folderValue, setFolderValue] = useState<string>(NO_CHANGE);
    const [descriptionAction, setDescriptionAction] = useState<'no_change' | 'set'>(NO_CHANGE);
    const [description, setDescription] = useState('');
    const [expirationAction, setExpirationAction] = useState<'no_change' | 'set' | 'clear'>(NO_CHANGE);
    const [expiresAt, setExpiresAt] = useState('');
    const [downloadLimitAction, setDownloadLimitAction] = useState<'no_change' | 'set' | 'clear'>(NO_CHANGE);
    const [downloadLimit, setDownloadLimit] = useState('');
    const [downloadLimitScope, setDownloadLimitScope] = useState<'total' | 'per_user'>('total');
    const [addCategoryIds, setAddCategoryIds] = useState<number[]>([]);
    const [removeCategoryIds, setRemoveCategoryIds] = useState<number[]>([]);

    const reset = () => {
        setFolderValue(NO_CHANGE);
        setDescriptionAction(NO_CHANGE);
        setDescription('');
        setExpirationAction(NO_CHANGE);
        setExpiresAt('');
        setDownloadLimitAction(NO_CHANGE);
        setDownloadLimit('');
        setDownloadLimitScope('total');
        setAddCategoryIds([]);
        setRemoveCategoryIds([]);
    };

    const addCategory = (id: number) => {
        setAddCategoryIds((current) => [...current, id]);
        setRemoveCategoryIds((current) => current.filter((x) => x !== id));
    };
    const removeCategoryToAdd = (id: number) => setAddCategoryIds((current) => current.filter((x) => x !== id));

    const markForRemoval = (id: number) => {
        setRemoveCategoryIds((current) => [...current, id]);
        setAddCategoryIds((current) => current.filter((x) => x !== id));
    };
    const unmarkForRemoval = (id: number) => setRemoveCategoryIds((current) => current.filter((x) => x !== id));

    const touchesNothing =
        folderValue === NO_CHANGE &&
        descriptionAction === NO_CHANGE &&
        expirationAction === NO_CHANGE &&
        downloadLimitAction === NO_CHANGE &&
        addCategoryIds.length === 0 &&
        removeCategoryIds.length === 0;

    const categoryChip = (id: number, onRemove: () => void) => {
        const category = categories.find((c) => c.id === id);
        return (
            <span
                key={id}
                className={`inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm ${category ? categoryColor(category.color).badge : 'bg-muted'}`}
            >
                {category?.name ?? id}
                <button type="button" className="opacity-70 hover:opacity-100" onClick={onRemove}>
                    <X className="size-3.5" />
                    <span className="sr-only">{t('Remove')}</span>
                </button>
            </span>
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) reset();
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{t('Bulk edit :count files', { count })}</DialogTitle>
                <DialogDescription>{t('Only the fields you change here are applied — everything else is left as-is.')}</DialogDescription>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label>{t('Folder')}</Label>
                        <Select value={folderValue} onValueChange={setFolderValue}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_CHANGE}>{t('— No change —')}</SelectItem>
                                <SelectItem value="root">{t('Root')}</SelectItem>
                                {folderOptions.map((folder) => (
                                    <SelectItem key={folder.id} value={String(folder.id)}>
                                        {folder.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('Description')}</Label>
                        <Select value={descriptionAction} onValueChange={(value) => setDescriptionAction(value as 'no_change' | 'set')}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_CHANGE}>{t('— No change —')}</SelectItem>
                                <SelectItem value="set">{t('Set to…')}</SelectItem>
                            </SelectContent>
                        </Select>
                        {descriptionAction === 'set' && <Input value={description} onChange={(e) => setDescription(e.target.value)} autoFocus />}
                    </div>

                    {canSetExpiration && (
                        <div className="grid gap-2">
                            <Label>{t('Expiration')}</Label>
                            <Select value={expirationAction} onValueChange={(value) => setExpirationAction(value as 'no_change' | 'set' | 'clear')}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_CHANGE}>{t('— No change —')}</SelectItem>
                                    <SelectItem value="set">{t('Set to…')}</SelectItem>
                                    <SelectItem value="clear">{t('Clear')}</SelectItem>
                                </SelectContent>
                            </Select>
                            {expirationAction === 'set' && <Input type="date" value={expiresAt} onChange={(e) => setExpiresAt(e.target.value)} />}
                        </div>
                    )}

                    {canLimitDownloads && (
                        <div className="grid gap-2">
                            <Label>{t('Download limit')}</Label>
                            <Select
                                value={downloadLimitAction}
                                onValueChange={(value) => setDownloadLimitAction(value as 'no_change' | 'set' | 'clear')}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_CHANGE}>{t('— No change —')}</SelectItem>
                                    <SelectItem value="set">{t('Set to…')}</SelectItem>
                                    <SelectItem value="clear">{t('Remove the limit')}</SelectItem>
                                </SelectContent>
                            </Select>
                            {downloadLimitAction === 'set' && (
                                <>
                                    <Input type="number" min={1} value={downloadLimit} onChange={(e) => setDownloadLimit(e.target.value)} />
                                    <Select
                                        value={downloadLimitScope}
                                        onValueChange={(value) => setDownloadLimitScope(value as 'total' | 'per_user')}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="total">{t('In total, across everyone')}</SelectItem>
                                            <SelectItem value="per_user">{t('Each person separately')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </>
                            )}
                        </div>
                    )}

                    {canSetCategories && (
                        <>
                            <div className="grid gap-2">
                                <Label>{t('Add categories')}</Label>
                                <Select value="" onValueChange={(value) => addCategory(Number(value))}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Add a category')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.filter((c) => !addCategoryIds.includes(c.id)).length === 0 ? (
                                            <div className="text-muted-foreground px-2 py-1.5 text-sm">{t('No more categories')}</div>
                                        ) : (
                                            categories
                                                .filter((c) => !addCategoryIds.includes(c.id))
                                                .map((category) => (
                                                    <SelectItem key={category.id} value={String(category.id)}>
                                                        <span className="flex items-center gap-2">
                                                            <span
                                                                className={`size-2 shrink-0 rounded-full ${categoryColor(category.color).swatch}`}
                                                            />
                                                            {category.name}
                                                        </span>
                                                    </SelectItem>
                                                ))
                                        )}
                                    </SelectContent>
                                </Select>
                                {addCategoryIds.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {addCategoryIds.map((id) => categoryChip(id, () => removeCategoryToAdd(id)))}
                                    </div>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('Remove categories')}</Label>
                                <Select value="" onValueChange={(value) => markForRemoval(Number(value))}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Remove a category')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.filter((c) => !removeCategoryIds.includes(c.id)).length === 0 ? (
                                            <div className="text-muted-foreground px-2 py-1.5 text-sm">{t('No more categories')}</div>
                                        ) : (
                                            categories
                                                .filter((c) => !removeCategoryIds.includes(c.id))
                                                .map((category) => (
                                                    <SelectItem key={category.id} value={String(category.id)}>
                                                        <span className="flex items-center gap-2">
                                                            <span
                                                                className={`size-2 shrink-0 rounded-full ${categoryColor(category.color).swatch}`}
                                                            />
                                                            {category.name}
                                                        </span>
                                                    </SelectItem>
                                                ))
                                        )}
                                    </SelectContent>
                                </Select>
                                {removeCategoryIds.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {removeCategoryIds.map((id) => categoryChip(id, () => unmarkForRemoval(id)))}
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="outline">{t('Cancel')}</Button>
                    </DialogClose>
                    <Button
                        disabled={touchesNothing}
                        onClick={() => {
                            setOpen(false);
                            onConfirm({
                                folder_action: folderValue === NO_CHANGE ? 'no_change' : 'move',
                                folder_id: folderValue === NO_CHANGE || folderValue === 'root' ? null : Number(folderValue),
                                description_action: descriptionAction,
                                description,
                                expiration_action: canSetExpiration ? expirationAction : NO_CHANGE,
                                expires_at: expiresAt,
                                download_limit_action: canLimitDownloads ? downloadLimitAction : NO_CHANGE,
                                download_limit: downloadLimit,
                                download_limit_scope: downloadLimitScope,
                                add_category_ids: canSetCategories ? addCategoryIds : [],
                                remove_category_ids: canSetCategories ? removeCategoryIds : [],
                            });
                            reset();
                        }}
                    >
                        {t('Update :count files', { count })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
