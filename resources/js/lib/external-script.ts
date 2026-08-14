/**
 * Loads a third-party script, once.
 *
 * This is the only place in the application that fetches code from
 * another origin, which is deliberate: it means the set of external
 * origins we talk to is one grep away, and a Content-Security-Policy — of
 * which there is none today — would have exactly one file to agree with.
 *
 * The memo is what keeps it honest under Inertia. Navigating between two
 * pages that both mount a CAPTCHA does not re-fetch the vendor bundle, and
 * two widgets on one page share a single load rather than racing each
 * other. v1 sidestepped all of this by putting the provider's script in
 * the footer of *every* page, including the admin dashboard, on any
 * installation that had a CAPTCHA configured at all.
 */
const loads = new Map<string, Promise<void>>();

export function loadScript(src: string): Promise<void> {
    const existing = loads.get(src);

    if (existing) return existing;

    const load = new Promise<void>((resolve, reject) => {
        // Server-side rendering, or any environment without a document:
        // nothing to load into, and nothing that will ever resolve.
        if (typeof document === 'undefined') {
            reject(new Error('No document to load a script into.'));

            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => {
            // Drop the memo so a later mount can retry — a blocked script
            // is often a transient network or extension problem, and
            // caching the failure forever would make it permanent.
            loads.delete(src);
            reject(new Error(`Failed to load ${src}`));
        };

        document.head.appendChild(script);
    });

    loads.set(src, load);

    return load;
}
