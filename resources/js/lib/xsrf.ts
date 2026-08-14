/**
 * This installation's CSRF token, read from its cookie.
 *
 * The cookie is named after the installation rather than after the
 * framework (see App\Http\Middleware\ValidateCsrfToken): cookies are
 * scoped by host and ignore the port, so a second Laravel app on the same
 * hostname would otherwise overwrite a shared `XSRF-TOKEN` and every write
 * here would fail with a 419 that looks exactly like an expired session.
 *
 * The name comes from a meta tag because nothing on the client could
 * derive it. `XSRF-TOKEN` remains the fallback so a page rendered by
 * something that does not emit the tag still works.
 *
 * Read fresh on every call, never captured: the value rotates, and a copy
 * taken when a component mounted is a 419 waiting for a long-open tab.
 */
export function xsrfCookieName(): string {
    return document.querySelector('meta[name="xsrf-cookie"]')?.getAttribute('content') || 'XSRF-TOKEN';
}

export function xsrfToken(): string {
    const name = xsrfCookieName().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : '';
}
