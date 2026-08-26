import { type BreadcrumbItem, type SharedData } from '@/types';
import {
    DndContext,
    DragEndEvent,
    DragOverEvent,
    DragOverlay,
    DragStartEvent,
    KeyboardSensor,
    PointerSensor,
    closestCorners,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { Head, router, usePage } from '@inertiajs/react';
import { GripVertical, LayoutGrid } from 'lucide-react';
import { Fragment, ReactNode, useMemo, useState } from 'react';

import { ApiWidget, type ApiUsageSummary } from '@/components/dashboard-widgets/api-widget';
import { CountersWidget, type Counters } from '@/components/dashboard-widgets/counters-widget';
import { ExpiredFilesWidget, type ExpiredFilesSummary } from '@/components/dashboard-widgets/expired-files-widget';
import { LargestFilesWidget, type LargestFile } from '@/components/dashboard-widgets/largest-files-widget';
import { NewsWidget, type NewsItem } from '@/components/dashboard-widgets/news-widget';
import { RecentActivityViewAll, RecentActivityWidget, type RecentEntry } from '@/components/dashboard-widgets/recent-activity-widget';
import { SystemWidget, type SystemInfo } from '@/components/dashboard-widgets/system-widget';
import { TopClientsWidget, type TopClient } from '@/components/dashboard-widgets/top-clients-widget';
import { TransfersRangeControls, TransfersWidget, type TransferPoint, type TransfersRange } from '@/components/dashboard-widgets/transfers-widget';
import { WidgetBox } from '@/components/dashboard-widgets/widget-box';
import { WidgetsDialog } from '@/components/dashboard-widgets/widgets-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { UpdateReleaseDialog } from '@/components/update-release-dialog';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { WIDGET_KEYS, WIDGET_LABELS, type WidgetKey, type WidgetLayout } from '@/lib/dashboard-widgets';

interface DashboardProps {
    counters: Counters | null;
    transfers: TransferPoint[] | null;
    transfers_range: TransfersRange | null;
    top_clients_by_storage: TopClient[] | null;
    largest_files: LargestFile[] | null;
    recent: RecentEntry[] | null;
    system: SystemInfo | null;
    news: NewsItem[] | null;
    expired_files: ExpiredFilesSummary | null;
    api: ApiUsageSummary | null;
    widget_layout: WidgetLayout;
    dashboard_columns: number;
}

const COLUMN_CLASSES: Record<number, string> = {
    1: 'lg:grid-cols-1',
    2: 'lg:grid-cols-2',
    3: 'lg:grid-cols-3',
    4: 'lg:grid-cols-4',
};

function columnContainerId(index: number): string {
    return `column-${index}`;
}

function columnIndexFromContainerId(id: string): number {
    return Number(id.replace('column-', ''));
}

/** A column is a droppable area (needed so an EMPTY column still accepts a drop) plus a sortable list of the widgets currently in it. */
function Column({ index, widgets, renderWidget }: { index: number; widgets: WidgetKey[]; renderWidget: (key: WidgetKey) => ReactNode }) {
    const { t } = useTranslation();
    const { setNodeRef, isOver } = useDroppable({ id: columnContainerId(index) });
    const isEmpty = widgets.length === 0;

    return (
        <div
            ref={setNodeRef}
            className={
                isEmpty
                    ? `rounded-lg border border-dashed p-3 transition-colors ${isOver ? 'border-primary bg-primary/5' : 'border-muted-foreground/25'}`
                    : 'space-y-6'
            }
        >
            <SortableContext items={widgets} strategy={verticalListSortingStrategy}>
                {isEmpty ? (
                    <div className="text-muted-foreground flex min-h-32 flex-col items-center justify-center gap-2 text-center text-sm">
                        <GripVertical className="size-5" />
                        <p>{t('Drag widgets here')}</p>
                    </div>
                ) : (
                    widgets.map((key) => <Fragment key={key}>{renderWidget(key)}</Fragment>)
                )}
            </SortableContext>
        </div>
    );
}

export default function Dashboard({
    counters,
    transfers,
    transfers_range,
    top_clients_by_storage,
    largest_files,
    recent,
    system,
    news,
    expired_files,
    api,
    widget_layout,
    dashboard_columns,
}: DashboardProps) {
    const { t } = useTranslation();
    const { update_notice } = usePage<SharedData>().props;
    const [releaseDialogOpen, setReleaseDialogOpen] = useState(false);
    const [widgetsDialogOpen, setWidgetsDialogOpen] = useState(false);
    const [layout, setLayout] = useState<WidgetLayout>(widget_layout);
    const [columnsCount, setColumnsCount] = useState(dashboard_columns);
    const [activeKey, setActiveKey] = useState<WidgetKey | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Dashboard'), href: '/dashboard' }];

    const applyTransferRange = (params: Record<string, string>) =>
        router.get(route('dashboard'), params, { preserveState: true, preserveScroll: true, replace: true });

    const hasData = (key: WidgetKey): boolean => {
        switch (key) {
            case 'counters':
                return counters !== null;
            case 'transfers':
                return transfers !== null;
            case 'top_clients_by_storage':
                return top_clients_by_storage !== null && top_clients_by_storage.length > 0;
            case 'largest_files':
                return largest_files !== null && largest_files.length > 0;
            case 'recent':
                return recent !== null;
            case 'system':
                return system !== null;
            case 'news':
                return news !== null;
            case 'expired_files':
                return expired_files !== null;
            case 'api':
                return api !== null;
        }
    };

    const renderWidget = (key: WidgetKey): ReactNode => {
        const title = t(WIDGET_LABELS[key]);

        switch (key) {
            case 'counters':
                return (
                    counters && (
                        <WidgetBox id={key} title={title}>
                            <CountersWidget counters={counters} />
                        </WidgetBox>
                    )
                );
            case 'transfers':
                return (
                    transfers && (
                        <WidgetBox
                            id={key}
                            title={title}
                            headerExtra={<TransfersRangeControls transfersRange={transfers_range} onRangeChange={applyTransferRange} />}
                        >
                            <TransfersWidget transfers={transfers} />
                        </WidgetBox>
                    )
                );
            case 'top_clients_by_storage':
                return (
                    top_clients_by_storage && (
                        <WidgetBox id={key} title={title}>
                            <TopClientsWidget topClients={top_clients_by_storage} />
                        </WidgetBox>
                    )
                );
            case 'largest_files':
                return (
                    largest_files && (
                        <WidgetBox id={key} title={title}>
                            <LargestFilesWidget largestFiles={largest_files} />
                        </WidgetBox>
                    )
                );
            case 'recent':
                return (
                    recent && (
                        <WidgetBox id={key} title={title} headerExtra={<RecentActivityViewAll />}>
                            <RecentActivityWidget recent={recent} />
                        </WidgetBox>
                    )
                );
            case 'system':
                return (
                    system && (
                        <WidgetBox id={key} title={title}>
                            <SystemWidget system={system} onViewReleaseNotes={() => setReleaseDialogOpen(true)} />
                        </WidgetBox>
                    )
                );
            case 'news':
                return (
                    news && (
                        <WidgetBox id={key} title={title}>
                            <NewsWidget news={news} />
                        </WidgetBox>
                    )
                );
            case 'expired_files':
                return (
                    expired_files && (
                        // Retitled rather than relabelled in WIDGET_LABELS:
                        // the same widget means different things to the two
                        // viewers, and only the server knows which one this
                        // is.
                        <WidgetBox id={key} title={expired_files.scoped ? t('Your expired files') : title}>
                            <ExpiredFilesWidget expiredFiles={expired_files} />
                        </WidgetBox>
                    )
                );
            case 'api':
                return (
                    api && (
                        <WidgetBox id={key} title={title}>
                            <ApiWidget summary={api} />
                        </WidgetBox>
                    )
                );
        }
    };

    // Visible, ordered widget keys grouped into columns — derived fresh
    // any time the layout or the underlying data changes (e.g. after a
    // Widgets modal save reloads the page with fresh data for a
    // newly-enabled widget).
    const columns = useMemo((): WidgetKey[][] => {
        const result: WidgetKey[][] = Array.from({ length: columnsCount }, () => []);

        const visible = WIDGET_KEYS.filter((key) => layout[key]?.enabled && hasData(key)).sort(
            (a, b) => (layout[a]?.position ?? 0) - (layout[b]?.position ?? 0),
        );

        for (const key of visible) {
            const columnIndex = Math.min(layout[key]?.column_index ?? 0, columnsCount - 1);
            result[columnIndex].push(key);
        }

        return result;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [layout, columnsCount, counters, transfers, top_clients_by_storage, largest_files, recent, system, news, expired_files]);

    const permittedKeys = Object.keys(layout) as WidgetKey[];
    const isEmpty = columns.every((col) => col.length === 0);

    const sensors = useSensors(useSensor(PointerSensor), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));

    const findColumnOf = (columnsState: WidgetKey[][], id: string): number | null => {
        if (id.startsWith('column-')) return columnIndexFromContainerId(id);
        const index = columnsState.findIndex((col) => col.includes(id as WidgetKey));
        return index === -1 ? null : index;
    };

    const [dragColumns, setDragColumns] = useState<WidgetKey[][] | null>(null);

    const handleDragStart = (event: DragStartEvent) => {
        setActiveKey(event.active.id as WidgetKey);
        setDragColumns(columns.map((col) => [...col]));
    };

    const handleDragOver = (event: DragOverEvent) => {
        const { active, over } = event;
        if (!over || !dragColumns) return;

        const activeColumn = findColumnOf(dragColumns, String(active.id));
        const overColumn = findColumnOf(dragColumns, String(over.id));
        if (activeColumn === null || overColumn === null || activeColumn === overColumn) return;

        setDragColumns((current) => {
            if (!current) return current;
            const next = current.map((col) => [...col]);
            const fromIndex = next[activeColumn].indexOf(active.id as WidgetKey);
            next[activeColumn].splice(fromIndex, 1);
            const overIndex = next[overColumn].indexOf(over.id as WidgetKey);
            next[overColumn].splice(overIndex === -1 ? next[overColumn].length : overIndex, 0, active.id as WidgetKey);
            return next;
        });
    };

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        setActiveKey(null);

        if (!over || !dragColumns) {
            setDragColumns(null);
            return;
        }

        const activeColumn = findColumnOf(dragColumns, String(active.id));
        const overColumn = findColumnOf(dragColumns, String(over.id));

        let finalColumns = dragColumns;
        if (activeColumn !== null && overColumn !== null && activeColumn === overColumn && active.id !== over.id) {
            const col = dragColumns[activeColumn];
            finalColumns = dragColumns.map((c, i) =>
                i === activeColumn ? arrayMove(col, col.indexOf(active.id as WidgetKey), col.indexOf(over.id as WidgetKey)) : c,
            );
        }

        setDragColumns(null);

        const nextLayout: WidgetLayout = { ...layout };
        finalColumns.forEach((col, columnIndex) => {
            col.forEach((key, position) => {
                nextLayout[key] = { enabled: true, column_index: columnIndex, position };
            });
        });
        setLayout(nextLayout);

        router.put(
            route('dashboard.widgets.update'),
            {
                columns: columnsCount,
                widgets: Object.entries(nextLayout).map(([widget_key, entry]) => ({ widget_key, ...entry })),
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const displayColumns = dragColumns ?? columns;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Dashboard')} />

            <div className="space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading title={t('Dashboard')} description={t('An overview of this installation')} />
                    <Button variant="outline" size="sm" onClick={() => setWidgetsDialogOpen(true)}>
                        <LayoutGrid className="size-4" />
                        {t('Widgets')}
                    </Button>
                </div>

                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCorners}
                    onDragStart={handleDragStart}
                    onDragOver={handleDragOver}
                    onDragEnd={handleDragEnd}
                >
                    <div className={`grid gap-6 ${COLUMN_CLASSES[columnsCount] ?? COLUMN_CLASSES[2]}`}>
                        {displayColumns.map((widgets, index) => (
                            <Column key={index} index={index} widgets={widgets} renderWidget={renderWidget} />
                        ))}
                    </div>

                    <DragOverlay>
                        {activeKey && (
                            <div className="bg-card ring-primary/40 flex items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium shadow-lg ring-1">
                                <GripVertical className="text-muted-foreground size-4" />
                                {t(WIDGET_LABELS[activeKey])}
                            </div>
                        )}
                    </DragOverlay>
                </DndContext>

                {isEmpty && (
                    <div className="text-muted-foreground rounded-lg border px-4 py-10 text-center text-sm">
                        <Badge variant="secondary" className="mb-2">
                            {t('Welcome')}
                        </Badge>
                        <p>{t('Use the navigation to get started.')}</p>
                    </div>
                )}
            </div>

            {update_notice && <UpdateReleaseDialog notice={update_notice} open={releaseDialogOpen} onOpenChange={setReleaseDialogOpen} />}

            <WidgetsDialog
                open={widgetsDialogOpen}
                onOpenChange={setWidgetsDialogOpen}
                permittedKeys={permittedKeys}
                layout={layout}
                columnsCount={columnsCount}
                onSave={(newLayout, newColumnsCount) => {
                    setWidgetsDialogOpen(false);
                    router.put(
                        route('dashboard.widgets.update'),
                        {
                            columns: newColumnsCount,
                            widgets: Object.entries(newLayout).map(([widget_key, entry]) => ({ widget_key, ...entry })),
                        },
                        {
                            onSuccess: () => {
                                setLayout(newLayout);
                                setColumnsCount(newColumnsCount);
                            },
                        },
                    );
                }}
            />
        </AppLayout>
    );
}
