import { useCallback, useEffect, useRef, useState } from 'react';

import { xsrfToken } from '@/lib/xsrf';

export type ZipDownloadStatus = 'idle' | 'preparing' | 'ready' | 'failed';

export type ZipSelection = Record<string, unknown>;

/**
 * Where a different zip endpoint lives — a visitor's zip on a public page
 * (PublicZipDownloadsController). Its store answers with the status and
 * download URLs, because they sit under the public base address the
 * frontend does not know. Left out, the signed-in routes are used.
 */
export interface ZipEndpoints {
    storeUrl: string;
}

const POLL_INTERVAL_MS = 2000;

/**
 * Drives the "build a zip, then download it" flow: POST the selection,
 * poll GET zip-downloads/{id} every ~2s, and hand off to a real browser
 * download once ready. Not an Inertia visit — the store/show endpoints
 * return plain JSON, not an Inertia response — so this talks to them with
 * a manually CSRF-tokened fetch instead of the Inertia router.
 */
export function useZipDownload(endpoints?: ZipEndpoints) {
    const [status, setStatus] = useState<ZipDownloadStatus>('idle');
    const [error, setError] = useState<string | null>(null);
    const pollRef = useRef<number | null>(null);
    const unmountedRef = useRef(false);

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

    // The poll must not outlive the page: an Inertia visit unmounts the
    // component mid-build, nothing calls close(), and the interval would
    // keep hitting zip-downloads/{id} every two seconds for as long as
    // the tab lives — the server keeps answering "pending" to nobody.
    // The flag covers the gap the cleanup alone leaves open: an unmount
    // while the store POST is still in flight, whose then() would set a
    // fresh interval after the cleanup already ran.
    useEffect(() => {
        return () => {
            unmountedRef.current = true;
            stopPolling();
        };
    }, [stopPolling]);

    const start = useCallback(
        (selection: ZipSelection) => {
            // A second start while a poll is running would overwrite
            // pollRef and orphan the first interval the same way.
            stopPolling();
            setStatus('preparing');
            setError(null);

            fetch(endpoints?.storeUrl ?? route('zip-downloads.store'), {
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
                    return response.json() as Promise<{ id: number; status_url?: string; download_url?: string }>;
                })
                .then(({ id, status_url, download_url }) => {
                    if (unmountedRef.current) {
                        return;
                    }
                    const statusUrl = status_url ?? route('zip-downloads.show', id);
                    const downloadUrl = download_url ?? route('zip-downloads.download', id);
                    pollRef.current = window.setInterval(() => {
                        fetch(statusUrl, {
                            credentials: 'same-origin',
                            headers: { Accept: 'application/json' },
                        })
                            .then((r) => r.json())
                            .then((state: { status: string; error: string | null }) => {
                                if (state.status === 'ready') {
                                    stopPolling();
                                    setStatus('ready');
                                    window.location.href = downloadUrl;
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
        [stopPolling, endpoints?.storeUrl],
    );

    return { status, error, start, close };
}
