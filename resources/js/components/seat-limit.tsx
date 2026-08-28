import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';

/**
 * What SeatAllowance::staffState() / clientState() send. Null on a
 * self-hosted install, where there is no limit and nothing to say.
 */
export interface SeatState {
    limit: number;
    used: number;
    full: boolean;
    /** Set only when full, and worded by the server so the screen and the
     *  refusal it prevents describe the limit the same way. */
    message: string | null;
}

/**
 * The "New user" / "New client" button, told how many seats are left.
 *
 * A managed installation is sold a number of accounts, so filling it is an
 * ordinary state rather than an error. Offering a live button for a form
 * that cannot be submitted is what made it feel like a fault: you invent a
 * password, submit, and the plan limit arrives as a validation error under
 * the email field. So the button goes dead at the limit and the reason is
 * on screen next to it, before anything is typed.
 */
export function SeatLimitedAction({
    seats,
    href,
    label,
    usage,
}: {
    seats: SeatState | null;
    href: string;
    label: string;
    /** The count line, worded by the caller: staff seats and client seats
     *  are different things to a reader. */
    usage: (seats: SeatState) => string;
}) {
    const action = seats?.full ? (
        <Button disabled title={seats.message ?? undefined}>
            {label}
        </Button>
    ) : (
        <Button asChild>
            <Link href={href}>{label}</Link>
        </Button>
    );

    if (!seats) {
        return action;
    }

    return (
        <div className="flex flex-col items-end gap-1.5">
            {action}
            <p className="text-muted-foreground max-w-xs text-right text-xs">{seats.full ? seats.message : usage(seats)}</p>
        </div>
    );
}
