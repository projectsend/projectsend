import { useTranslation } from '@/hooks/use-translation';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * States what this installation will accept, under a field where somebody
 * is choosing a password.
 *
 * Worth saying up front rather than only in the validation error: the
 * rules are configurable, so a person who knows one ProjectSend
 * installation cannot assume the next one wants the same thing.
 *
 * Renders nothing on the fields that merely *confirm* an existing
 * password (the sign-in form, the confirm-password gate, the delete-account
 * prompt) — there is no policy to meet there, only a password to get right.
 */
export function PasswordRequirements({ className = '' }: { className?: string }) {
    const { t } = useTranslation();
    const { password_policy: policy } = usePage<SharedData>().props;

    if (!policy) {
        return null;
    }

    return (
        <p className={`text-muted-foreground text-sm ${className}`}>
            {t('At least :count characters.', { count: policy.min_length })}
            {policy.reject_breached && ' ' + t('Passwords that appear in known data breaches are not accepted.')}
        </p>
    );
}
