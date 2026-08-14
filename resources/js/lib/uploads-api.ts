import { xsrfToken } from '@/lib/xsrf';

// Re-exported because this module was where the helper used to live, and
// several callers still reach for it here.
export { xsrfToken };

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
        throw new Error(payload?.message ?? `Upload request failed (${response.status})`);
    }

    return (await response.json()) as T;
}
