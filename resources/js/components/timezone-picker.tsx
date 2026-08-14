import { SearchablePicker, type PickerOption } from '@/components/searchable-picker';
import { useTranslation } from '@/hooks/use-translation';

export interface TimezoneOption {
    value: string;
    /** "Europe / Madrid" — the identifier, made readable but still searchable by region. */
    label: string;
    /** That zone's offset today, e.g. "UTC+02:00". */
    offset: string;
}

/**
 * Picks one of the ~420 IANA zones.
 *
 * A plain `<Select>` is not usable at this length — radix only does
 * first-letter typeahead, so finding Buenos Aires means scrolling past
 * every other city in the Americas. SearchablePicker is the search box
 * this needs, and reusing it is why its option id is generic: a zone is
 * keyed by its identifier, not by a row id.
 *
 * The offset sits on the hint line rather than in the label. It changes
 * twice a year in half the world, so it is a "did I pick the right one?"
 * confirmation, not something anybody types.
 */
export function TimezonePicker({
    options,
    value,
    onChange,
    className,
}: {
    options: TimezoneOption[];
    value: string;
    onChange: (timezone: string) => void;
    className?: string;
}) {
    const { t } = useTranslation();

    const pickerOptions: PickerOption<string>[] = options.map((option) => ({
        id: option.value,
        label: option.label,
        hint: option.offset,
    }));

    // Stated above the list as well as highlighted inside it. The list
    // scrolls to the selection, but a scrolled-to row still has to be
    // hunted for among four hundred near-identical ones — and the answer
    // to "what is this set to?" should not require looking.
    const current = options.find((option) => option.value === value);

    return (
        <div className="grid gap-2">
            <p className="text-sm">
                {current === undefined ? (
                    <span className="text-muted-foreground">{t('None selected')}</span>
                ) : (
                    <>
                        <span className="font-medium">{current.label}</span> <span className="text-muted-foreground">{current.offset}</span>
                    </>
                )}
            </p>

            <SearchablePicker
                options={pickerOptions}
                value={value}
                // Clicking the selected row again would otherwise clear it,
                // and there is no such thing as "no timezone" — every date on
                // screen has to be rendered in something.
                onChange={(id) => id !== null && onChange(id)}
                searchPlaceholder={t('Search timezones…')}
                className={className}
                maxHeightClass="max-h-56"
            />
        </div>
    );
}
