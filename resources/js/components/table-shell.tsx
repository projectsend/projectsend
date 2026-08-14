import { type ReactNode } from 'react';

/**
 * A header cell. A plain string is a visible label; `null` is the unlabelled
 * column that holds row actions; the object form is for a label that should
 * be read out but not shown.
 */
export type TableColumn = string | null | { label: string; srOnly: true };

interface TableShellProps {
    columns: TableColumn[];
    /** Shown in place of the rows when there are none. */
    emptyMessage: ReactNode;
    isEmpty: boolean;
    children: ReactNode;
}

/**
 * The bordered, horizontally-scrollable table every list page is built from:
 * the frame, the header row, and the centred "nothing here" message.
 *
 * Rows stay with the page that owns them -- the columns genuinely differ from
 * one list to the next, and that is the part worth reading in context.
 */
export function TableShell({ columns, emptyMessage, isEmpty, children }: TableShellProps) {
    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
                <thead>
                    <tr className="bg-muted/50 border-b text-left">
                        {columns.map((column, index) => {
                            if (column === null) {
                                return <th key={index} className="px-4 py-2.5" />;
                            }

                            if (typeof column === 'object') {
                                return (
                                    <th key={index} className="px-4 py-2.5">
                                        <span className="sr-only">{column.label}</span>
                                    </th>
                                );
                            }

                            return (
                                <th key={index} className="px-4 py-2.5 font-medium">
                                    {column}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {isEmpty && (
                        <tr>
                            <td colSpan={columns.length} className="text-muted-foreground px-4 py-8 text-center">
                                {emptyMessage}
                            </td>
                        </tr>
                    )}
                    {children}
                </tbody>
            </table>
        </div>
    );
}
