import { router, usePage } from '@inertiajs/react';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { useTranslation } from '@/hooks/use-translation';
import { type SharedData } from '@/types';

type ToastType = 'success' | 'error';

interface Toast {
    id: number;
    type: ToastType;
    message: string;
}

const DURATION = 4500;

/**
 * The flash object most recently turned into toasts, across remounts.
 *
 * Both of the component's sources can hand over the *same* visit's flash:
 * the layout (and the Toaster inside it) remounts whenever a flashed
 * redirect lands on a different page component — create → edit, delete →
 * index — and then the mount-time read and the router `success` event
 * each fire once for one flash, stacking every "Client created." twice.
 * Identity comparison is the dedup that cannot over-trigger: a repeat of
 * the same action produces an identical *message* but never the identical
 * *object*, so deliberate back-to-back toasts still both show.
 */
let shownFlash: SharedData['flash'] | null = null;

/**
 * App-wide flash toasts. Reads the `flash` shared prop and shows a toast
 * after any Inertia visit that carried one (created/updated/deleted…).
 * Uses the router `success` event so two identical messages in a row still
 * each toast. Mounted in the app layout, which remounts it — see the note on
 * `shownFlash` above.
 */
export function Toaster() {
    const { t } = useTranslation();
    const page = usePage<SharedData>();
    const [toasts, setToasts] = useState<Toast[]>([]);
    const nextId = useRef(0);

    const push = (type: ToastType, message: string | null | undefined) => {
        if (!message) return;
        const id = ++nextId.current;
        setToasts((current) => [...current, { id, type, message }]);
        window.setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), DURATION);
    };

    const dismiss = (id: number) => setToasts((current) => current.filter((toast) => toast.id !== id));

    const pushFlash = (flash: SharedData['flash'] | null | undefined) => {
        if (!flash || flash === shownFlash) return;
        shownFlash = flash;
        push('success', flash.success);
        push('error', flash.error);
    };

    useEffect(() => {
        // A flash present on the very first render (e.g. a server redirect on load).
        pushFlash(page.props.flash);

        // And every subsequent successful visit.
        const stop = router.on('success', (event) => {
            pushFlash((event.detail.page.props as unknown as SharedData).flash);
        });

        return () => stop();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (toasts.length === 0) return null;

    return (
        <div className="pointer-events-none fixed inset-x-0 top-0 z-[100] flex flex-col items-center gap-2 p-4 sm:items-end" aria-live="polite">
            {toasts.map((toast) => {
                const Icon = toast.type === 'success' ? CheckCircle2 : XCircle;
                const colors =
                    toast.type === 'success'
                        ? 'bg-emerald-600 text-white dark:bg-emerald-500'
                        : 'bg-destructive text-destructive-foreground';

                return (
                    <div
                        key={toast.id}
                        role="status"
                        className={`animate-in slide-in-from-top-2 fade-in pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg px-4 py-3 shadow-lg ${colors}`}
                    >
                        <Icon className="mt-0.5 size-5 shrink-0" />
                        <p className="flex-1 text-sm">{toast.message}</p>
                        <button
                            type="button"
                            onClick={() => dismiss(toast.id)}
                            className="-mr-1 shrink-0 opacity-80 hover:opacity-100"
                        >
                            <X className="size-4" />
                            <span className="sr-only">{t('Dismiss')}</span>
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
