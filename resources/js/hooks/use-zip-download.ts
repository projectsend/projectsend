import { useCallback, useRef, useState } from 'react';

import { xsrfToken } from '@/lib/xsrf';

export type ZipDownloadStatus = 'idle' | 'preparing' | 'ready' | 'failed';

interface ZipSelection {
    file_ids?: number[];
    folder_ids?: number[];
}

const POLL_INTERVAL_MS = 2000;

/**
 * Drives the "build a zip, then download it" flow: POST the selection,
 * poll GET zip-downloads/{id} every ~2s, and hand off to a real browser
 * download once ready. Not an Inertia visit — the store/show endpoints
 * return plain JSON, not an Inertia response — so this talks to them with
 * a manually CSRF-tokened fetch instead of the Inertia router.
 */
export function useZipDownload() {
    const [status, setStatus] = useState<ZipDownloadStatus>('idle');
    const [error, setError] = useState<string | null>(null);
    const pollRef = useRef<number | null>(null);

    const stopPolling = useCallback(() => {
        if (pollRef.current !== null) {
            window.clearInterval(pollRef.current);
            pollRef.current = null;
        }
    }, []);

    const close = useCallback(() => {
        stopPolling();
        setStatus('idle');
        setError(null);
    }, [stopPolling]);

    const start = useCallback(
        (selection: ZipSelection) => {
            setStatus('preparing');
            setError(null);

            fetch(route('zip-downloads.store'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(selection),
            })
                .then(async (response) => {
                    if (!response.ok) {
                        const body = await response.json().catch(() => null);
                        throw new Error(body?.message ?? 'Request failed.');
                    }
                    return response.json() as Promise<{ id: number }>;
                })
                .then(({ id }) => {
                    pollRef.current = window.setInterval(() => {
                        fetch(route('zip-downloads.show', id), {
                            credentials: 'same-origin',
                            headers: { Accept: 'application/json' },
                        })
                            .then((r) => r.json())
                            .then((state: { status: string; error: string | null }) => {
                                if (state.status === 'ready') {
                                    stopPolling();
                                    setStatus('ready');
                                    window.location.href = route('zip-downloads.download', id);
                                } else if (state.status === 'failed') {
                                    stopPolling();
                                    setStatus('failed');
                                    setError(state.error);
                                }
                            });
                    }, POLL_INTERVAL_MS);
                })
                .catch((err: Error) => {
                    setStatus('failed');
                    setError(err.message);
                });
        },
        [stopPolling],
    );

    return { status, error, start, close };
}
