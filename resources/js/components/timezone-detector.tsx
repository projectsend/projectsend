import { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/**
 * Adopts the browser's timezone the first time somebody signs in, so a
 * client in another country sees their own clock without ever finding a
 * setting.
 *
 * Only fires while `timezone_is_explicit` is false — that is, while the
 * zone on screen is the installation's fallback and this account has
 * never had one of its own. The request writes `users.timezone`, which
 * flips the flag, so this runs exactly once per account and can never
 * overwrite a choice somebody made on purpose. A staff member who
 * deliberately works in the office's zone from a laptop abroad keeps it.
 *
 * Deliberately silent: no flash message, no reload of the page's own
 * data. `only: []` asks for nothing but the shared props, and the guard
 * ref keeps React 18's double-invoked effects from posting twice.
 *
 * Mounted in the two layouts that wrap signed-in screens — the staff
 * shell and the portal — rather than once at the root. `usePage()` reads
 * React context that `<App>` provides, so a copy rendered as a sibling of
 * `<App>` throws "usePage must be used within the Inertia component",
 * which unmounts the tree and serves a blank page. The auth and public
 * layouts are left out on purpose: nobody is signed in on those, so there
 * is nothing to detect.
 */
export function TimezoneDetector() {
    const { auth, timezone_is_explicit } = usePage<SharedData>().props;
    const posted = useRef(false);

    useEffect(() => {
        if (auth?.user == null || timezone_is_explicit || posted.current) {
            return;
        }

        const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;

        // An identifier the server would reject is not worth a round trip.
        // Older engines and locked-down browsers can return an empty string
        // or an abbreviation here.
        if (!detected || !detected.includes('/')) {
            return;
        }

        posted.current = true;

        router.put(route('timezone.update'), { timezone: detected }, { preserveScroll: true, preserveState: true, only: [] });
    }, [auth?.user, timezone_is_explicit]);

    return null;
}
