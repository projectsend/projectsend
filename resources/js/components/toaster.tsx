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
 * App-wide flash toasts. Reads the `flash` shared prop and shows a toast
 * after any Inertia visit that carried one (created/updated/deleted…).
 * Uses the router `success` event so two identical messages in a row still
 * each toast. Mounted once in the app layout.
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

    useEffect(() => {
        // A flash present on the very first render (e.g. a server redirect on load).
        push('success', page.props.flash?.success);
        push('error', page.props.flash?.error);

        // And every subsequent successful visit.
        const stop = router.on('success', (event) => {
            const flash = (event.detail.page.props as unknown as SharedData).flash;
            push('success', flash?.success);
            push('error', flash?.error);
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
