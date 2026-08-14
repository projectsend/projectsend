import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical } from 'lucide-react';
import { ReactNode } from 'react';

import { useTranslation } from '@/hooks/use-translation';

/**
 * The uniform card shell every dashboard widget renders inside. Owns the
 * title row (drag handle + title, plus each widget's own optional extra
 * header content — a range picker, a "View all" link, …) so the drag
 * handle always sits right next to the title rather than as a separate
 * control row. The handle alone carries the drag listeners, not the
 * whole card, so clicking a link or button inside a widget's own
 * content never starts a drag. Dragging is always available — there's
 * no separate "arrange mode" to toggle first.
 */
export function WidgetBox({ id, title, headerExtra, children }: { id: string; title: string; headerExtra?: ReactNode; children: ReactNode }) {
    const { t } = useTranslation();
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={`bg-card rounded-lg border p-4 ${isDragging ? 'z-10 opacity-50' : ''}`}
        >
            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-1.5">
                    <button
                        type="button"
                        className="text-muted-foreground hover:text-foreground -ml-1 cursor-grab touch-none active:cursor-grabbing"
                        {...attributes}
                        {...listeners}
                    >
                        <GripVertical className="size-4" />
                        <span className="sr-only">{t('Drag to reorder')}</span>
                    </button>
                    <h2 className="text-sm font-semibold">{title}</h2>
                </div>
                {headerExtra}
            </div>
            {children}
        </div>
    );
}
