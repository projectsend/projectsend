import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';

export interface ReassignCandidate {
    id: number;
    name: string;
    role: string;
}

interface AccountContentDeleteDialogProps {
    /** The button that opens the dialog. */
    trigger: ReactNode;
    name: string;
    content: { files: number; folders: number };
    candidates: ReassignCandidate[];
    onConfirm: (choice: { content_action: 'cascade_delete' | 'reassign'; reassign_to_id?: number }) => void;
}

/**
 * Deleting an account that owns files/folders needs a decision on what
 * happens to that content — ConfirmDialog has no slot for that extra
 * choice, so this is a separate dialog rather than an extension of it.
 */
export function AccountContentDeleteDialog({ trigger, name, content, candidates, onConfirm }: AccountContentDeleteDialogProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [contentAction, setContentAction] = useState<'cascade_delete' | 'reassign'>('cascade_delete');
    const [reassignToId, setReassignToId] = useState<string>('');
    const [candidateSearch, setCandidateSearch] = useState('');

    const canConfirm = contentAction === 'cascade_delete' || reassignToId !== '';

    const filteredCandidates = useMemo(() => {
        const query = candidateSearch.trim().toLowerCase();
        if (query === '') return candidates;

        return candidates.filter(
            (candidate) => candidate.name.toLowerCase().includes(query) || candidate.role.toLowerCase().includes(query),
        );
    }, [candidates, candidateSearch]);

    const reset = () => {
        setContentAction('cascade_delete');
        setReassignToId('');
        setCandidateSearch('');
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
                <DialogTitle>{t('Delete account?')}</DialogTitle>
                <DialogDescription>{t('The account of :name will be permanently deleted. This cannot be undone.', { name })}</DialogDescription>

                <p className="text-sm">
                    {t('This account owns :files file(s) and :folders folder(s). What should happen to them?', {
                        files: content.files,
                        folders: content.folders,
                    })}
                </p>

                <div className="grid gap-3">
                    <Select value={contentAction} onValueChange={(value) => setContentAction(value as 'cascade_delete' | 'reassign')}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="cascade_delete">{t('Delete them permanently')}</SelectItem>
                            <SelectItem value="reassign">{t('Reassign them to another account')}</SelectItem>
                        </SelectContent>
                    </Select>

                    {contentAction === 'reassign' && (
                        <div className="grid gap-2">
                            <Input
                                type="search"
                                placeholder={t('Search accounts…')}
                                value={candidateSearch}
                                onChange={(e) => setCandidateSearch(e.target.value)}
                                autoFocus
                            />
                            <div className="max-h-48 overflow-y-auto rounded-md border">
                                {filteredCandidates.length === 0 && (
                                    <p className="text-muted-foreground p-3 text-sm">{t('No accounts match ":search".', { search: candidateSearch })}</p>
                                )}
                                {filteredCandidates.map((candidate) => {
                                    const value = String(candidate.id);
                                    const selected = reassignToId === value;

                                    return (
                                        <button
                                            key={candidate.id}
                                            type="button"
                                            onClick={() => setReassignToId(value)}
                                            className={cn(
                                                'hover:bg-accent hover:text-accent-foreground flex w-full items-center justify-between px-3 py-2 text-left text-sm',
                                                selected && 'bg-accent text-accent-foreground',
                                            )}
                                        >
                                            <span>
                                                {candidate.name} ({candidate.role})
                                            </span>
                                            {selected && <Check className="size-4" />}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="outline">{t('Cancel')}</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={!canConfirm}
                        onClick={() => {
                            setOpen(false);
                            onConfirm(
                                contentAction === 'reassign'
                                    ? { content_action: 'reassign', reassign_to_id: Number(reassignToId) }
                                    : { content_action: 'cascade_delete' },
                            );
                            reset();
                        }}
                    >
                        {t('Delete account')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
