import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The date a client account stops working, shared by the create and edit
 * screens. The value is a bare `YYYY-MM-DD`: the server reads it as the end
 * of that day in the editor's timezone (DateInput::instant()).
 */
export function ClientExpiryField({
    value,
    onChange,
    error,
    expired = false,
}: {
    value: string;
    onChange: (value: string) => void;
    error?: string;
    /** The stored date has already passed — edit screen only. */
    expired?: boolean;
}) {
    const { t } = useTranslation();
    const { calendarDate } = useFormatDate();

    return (
        <div className="grid gap-2">
            <Label htmlFor="expires_at">{t('Account expires')}</Label>
            <Input id="expires_at" type="date" className="w-48" value={value} onChange={(e) => onChange(e.target.value)} />
            {expired && value !== '' ? (
                <p className="text-destructive text-xs">
                    {t('This account expired on :date and can no longer sign in. To let them back in, choose a later date or clear it, and make sure the account is active.', {
                        date: calendarDate(value),
                    })}
                </p>
            ) : (
                <p className="text-muted-foreground text-xs">
                    {t('After this day the client can no longer sign in, and the account is deactivated. Their files stay. Leave empty for an account that never expires.')}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}
