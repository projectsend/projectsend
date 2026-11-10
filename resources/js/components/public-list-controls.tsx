import { SelectPageCheckbox } from '@/components/portal/select-page-checkbox';
import { SelectionBar } from '@/components/portal/selection-bar';
import { PublicSortSelect } from '@/components/public-sort-select';
import { ZipDownloadDialog } from '@/components/zip-download-dialog';
import { type usePublicListing } from '@/hooks/use-public-listing';
import { cn } from '@/lib/utils';

/**
 * Everything above a public file list that is the same in every theme: the
 * "select all on this page" box, the Sort by select, the bar that appears
 * once files are ticked, and the dialog a zip is prepared in.
 *
 * Themes with a header row (compact, drive) put the select-all box in it
 * and pass `selectAll={false}`; the rest get it here.
 */
export function PublicListControls({
    listing,
    total,
    rows,
    selectAll = true,
    className,
    barClassName,
    downloadClassName,
}: {
    listing: ReturnType<typeof usePublicListing>;
    /** Every file on every page; the sort control is pointless for one. */
    total: number;
    /** Files on this page, which select-all would tick. */
    rows: number;
    selectAll?: boolean;
    className?: string;
    barClassName?: string;
    downloadClassName?: string;
}) {
    const showSort = total > 1;
    const showSelectAll = listing.canZip && selectAll && rows > 0;

    return (
        <>
            {(showSort || showSelectAll) && (
                <div className={cn('mb-3 flex items-center justify-between gap-3', className)}>
                    {showSelectAll ? <SelectPageCheckbox selection={listing.selection} /> : <span />}
                    {showSort && <PublicSortSelect sortState={listing.sortState} sortBy={listing.sortBy} />}
                </div>
            )}
            {listing.canZip && (
                <SelectionBar
                    count={listing.selection.count}
                    onDownload={listing.downloadSelectionAsZip}
                    onClear={listing.selection.clear}
                    className={cn('mb-3', barClassName)}
                    downloadClassName={downloadClassName}
                />
            )}
            <ZipDownloadDialog status={listing.zip.status} error={listing.zip.error} onClose={listing.zip.close} />
        </>
    );
}
