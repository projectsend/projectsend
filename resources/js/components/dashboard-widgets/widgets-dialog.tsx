import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { WIDGET_KEYS, WIDGET_LABELS, type WidgetKey, type WidgetLayout } from '@/lib/dashboard-widgets';

export function WidgetsDialog({
    open,
    onOpenChange,
    permittedKeys,
    layout,
    columnsCount,
    onSave,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    permittedKeys: WidgetKey[];
    layout: WidgetLayout;
    columnsCount: number;
    onSave: (layout: WidgetLayout, columnsCount: number) => void;
}) {
    const { t } = useTranslation();
    const [draftEnabled, setDraftEnabled] = useState<Partial<Record<WidgetKey, boolean>>>({});
    const [draftColumns, setDraftColumns] = useState(columnsCount);

    // Re-sync the draft from the current server-known state every time
    // the dialog opens — it may have changed since the last time it was
    // open (e.g. a widget dragged to a new position).
    useEffect(() => {
        if (!open) return;

        setDraftEnabled(Object.fromEntries(permittedKeys.map((key) => [key, layout[key]?.enabled ?? true])));
        setDraftColumns(columnsCount);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const visibleKeys = WIDGET_KEYS.filter((key) => permittedKeys.includes(key));

    const save = () => {
        const nextLayout: WidgetLayout = { ...layout };
        for (const key of visibleKeys) {
            nextLayout[key] = {
                enabled: draftEnabled[key] ?? true,
                column_index: layout[key]?.column_index ?? 0,
                position: layout[key]?.position ?? 0,
            };
        }
        onSave(nextLayout, draftColumns);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Dashboard widgets')}</DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="dashboard-columns">{t('Columns')}</Label>
                        <Select value={String(draftColumns)} onValueChange={(v) => setDraftColumns(Number(v))}>
                            <SelectTrigger id="dashboard-columns" className="w-32">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[1, 2, 3, 4].map((n) => (
                                    <SelectItem key={n} value={String(n)}>
                                        {n}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('Visible widgets')}</Label>
                        <div className="space-y-2">
                            {visibleKeys.map((key) => (
                                <div key={key} className="flex items-center gap-2">
                                    <Checkbox
                                        id={`widget-${key}`}
                                        checked={draftEnabled[key] ?? true}
                                        onCheckedChange={(checked) => setDraftEnabled((current) => ({ ...current, [key]: checked === true }))}
                                    />
                                    <Label htmlFor={`widget-${key}`} className="font-normal">
                                        {t(WIDGET_LABELS[key])}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        {t('Cancel')}
                    </Button>
                    <Button onClick={save}>{t('Save')}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
