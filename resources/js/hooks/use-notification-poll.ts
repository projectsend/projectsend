import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const POLL_INTERVAL_MS = 30000;

/**
 * Keeps the unread notification count fresh. `initialCount` (the shared
 * Inertia prop `pending.notifications_unread`) only seeds the very first
 * paint — it's never resynced after that, because Inertia's Back/Forward
 * navigation restores props from its client-side `history.state` cache
 * with no server round-trip (see handlePopstateEvent in
 * @inertiajs/core), so a reactive `useEffect` on that prop would
 * periodically clobber an already-correct local count back to a stale
 * one. Instead, every update comes from an actual fetch of the dedicated
 * plain-JSON endpoint: once on mount, on the interval below as a safety
 * net, and on two Inertia router events that between them cover every
 * kind of visit —
 *   - 'navigate' fires for popstate-restored pages *and* for a visit to
 *     a different URL, but NOT for a visit that lands back on the exact
 *     same URL already showing (Inertia treats that as a "replace", and
 *     the source gates the navigate event on it not being one);
 *   - 'success' fires for every completed visit's response *except*
 *     popstate (which never hits the server at all), including same-URL
 *     ones — e.g. marking a notification read/unread from /notifications
 *     itself, whose POST redirects back to that same /notifications URL.
 * Listening to both closes the gap either alone would miss.
 */
export function useNotificationPoll(initialCount: number) {
    const [count, setCount] = useState(initialCount);

    useEffect(() => {
        const poll = () => {
            fetch(route('notifications.unread-count'), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then((body: { count: number }) => setCount(body.count))
                .catch(() => {
                    /* a missed poll just tries again next tick */
                });
        };

        poll();
        const intervalId = window.setInterval(poll, POLL_INTERVAL_MS);
        const stopOnNavigate = router.on('navigate', poll);
        const stopOnSuccess = router.on('success', poll);

        return () => {
            window.clearInterval(intervalId);
            stopOnNavigate();
            stopOnSuccess();
        };
    }, []);

    return { count, setCount };
}
