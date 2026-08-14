import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type CommentVisibilityOption } from '@/hooks/use-file-comments';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Who the comment being written will reach.
 *
 * The options come from the server, already narrowed to what this author
 * may actually choose on this file — "Everyone" is simply absent when
 * public comments are off or the file is not public, rather than present
 * and rejected on submit.
 *
 * Part of the shared field set every theme composes; never reimplement
 * this inside a theme.
 */
export function CommentVisibilitySelect({
    id = 'comment-visibility',
    value,
    options,
    onChange,
}: {
    id?: string;
    value: string;
    options: CommentVisibilityOption[];
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation();

    // One *choice* means there is nothing to choose. Options that are only
    // there to explain why they are unavailable do not count towards that.
    if (options.filter((option) => option.available).length <= 1) return null;

    const selected = options.find((option) => option.value === value);

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id} className="text-xs font-normal">
                {t('Who can see this')}
            </Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value} disabled={!option.available}>
                            {t(option.label)}
                            {!option.available && option.reason !== null && (
                                <span className="text-muted-foreground ml-2 text-xs">{t(option.reason)}</span>
                            )}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {selected && <p className="text-muted-foreground text-xs">{t(selected.description)}</p>}
        </div>
    );
}
