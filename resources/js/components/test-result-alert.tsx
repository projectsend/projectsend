import { CircleAlert, CircleCheck } from 'lucide-react';
import { type ReactNode } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';

/**
 * The answer to a "test this configuration" button.
 *
 * Shared by the three screens that have one — CAPTCHA, LDAP and email —
 * because they are the same thing and used to look like three different
 * things: one coloured only its failures, one put both outcomes in a grey
 * textarea, and one styled success as a neutral box, which is how it went
 * unnoticed on a page already made of boxes.
 *
 * A result somebody pressed a button for and is waiting on is the most
 * important thing on the screen for the second it exists, so it is filled
 * rather than outlined. It says which answer it is twice — in colour and
 * in a glyph — because colour alone is nothing to an administrator who
 * cannot see it.
 */
export function TestResultAlert({ ok, children, className }: { ok: boolean; children: ReactNode; className?: string }) {
    return (
        <Alert variant={ok ? 'success' : 'destructive'} className={className}>
            {ok ? <CircleCheck className="size-4" /> : <CircleAlert className="size-4" />}
            <AlertDescription className="whitespace-pre-line">{children}</AlertDescription>
        </Alert>
    );
}
