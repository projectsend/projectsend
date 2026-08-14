import { useDraggable, useDroppable } from '@dnd-kit/core';
import { Folder as FolderIcon, File as FileIcon } from 'lucide-react';
import { ReactNode } from 'react';

import { useTranslation } from '@/hooks/use-translation';

export interface DragData {
    kind: 'file' | 'folder';
    id: number;
    name: string;
}

/**
 * Make an entire table row draggable. Returns a ref + the pointer/keyboard
 * handlers to spread on the `<tr>`, plus `isDragging` so the source row can
 * dim while its chip floats in the DragOverlay.
 */
export function useRowDrag(data: DragData) {
    const { listeners, setNodeRef, isDragging } = useDraggable({
        id: `${data.kind}-${data.id}`,
        data,
    });

    // Only the pointer listeners — not dnd-kit's `attributes`, which would put
    // role="button"/tabIndex on the <tr> and break the table's semantics.
    return { setDragRef: setNodeRef, dragHandleProps: listeners ?? {}, isDragging };
}

/**
 * Make a folder a drop target. `isOver` excludes the folder being dragged
 * onto itself so it never highlights as a valid drop.
 */
export function useFolderDrop(folderId: number) {
    const { setNodeRef, isOver, active } = useDroppable({
        id: `drop-folder-${folderId}`,
        data: { folderId },
    });

    const draggingSelf = active?.data.current?.kind === 'folder' && active.data.current.id === folderId;

    return { setDropRef: setNodeRef, isOver: isOver && !draggingSelf };
}

/**
 * A drop target for a breadcrumb (or the library root). `folderId` null =
 * root. Highlights while a compatible item hovers.
 */
export function DropZone({
    folderId,
    disabled,
    className,
    children,
}: {
    folderId: number | null;
    disabled?: boolean;
    className?: string;
    children: ReactNode;
}) {
    const { setNodeRef, isOver, active } = useDroppable({
        id: folderId === null ? 'root' : `drop-folder-${folderId}`,
        data: { folderId },
        disabled,
    });

    const draggingSelf = active?.data.current?.kind === 'folder' && active.data.current.id === folderId;
    const highlight = isOver && !draggingSelf;

    return (
        <span ref={setNodeRef} className={`${className ?? ''} ${highlight ? 'ring-primary bg-primary/10 rounded-md ring-2' : ''}`}>
            {children}
        </span>
    );
}

/** The floating preview rendered inside <DragOverlay> while dragging. */
export function DragChip({ data }: { data: DragData }) {
    const { t } = useTranslation();
    const Icon = data.kind === 'folder' ? FolderIcon : FileIcon;

    return (
        <div className="bg-background ring-primary/40 pointer-events-none inline-flex max-w-xs items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium shadow-lg ring-1">
            <Icon className="text-primary size-4 shrink-0" />
            <span className="truncate">{data.name}</span>
            <span className="sr-only">{t('Moving')}</span>
        </div>
    );
}
