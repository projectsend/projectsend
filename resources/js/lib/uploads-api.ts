import { xsrfToken } from '@/lib/xsrf';

// Re-exported because this module was where the helper used to live, and
// several callers still reach for it here.
export { xsrfToken };

/** The server answered, and the answer was an error. Carries the status. */
export class UploadRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
    }
}

/**
 * Whether a failed request leaves the outcome unknown: no answer at all
 * (the connection dropped), or a proxy in front of the app giving up on
 * it. The application may still be working on the request.
 */
export function outcomeUnknown(error: unknown): boolean {
    if (!(error instanceof UploadRequestError)) return true;

    return [408, 499, 502, 503, 504].includes(error.status);
}

export async function apiFetch<T>(url: string, method: string, body?: unknown): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as { message?: string } | null;
        throw new UploadRequestError(payload?.message ?? `Upload request failed (${response.status})`, response.status);
    }

    // A cancel answers 204 with no body, which is not JSON.
    if (response.status === 204) return undefined as T;

    return (await response.json()) as T;
}
