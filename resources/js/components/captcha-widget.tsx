import { usePage } from '@inertiajs/react';
import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState } from 'react';

import { useTranslation } from '@/hooks/use-translation';
import { loadScript } from '@/lib/external-script';
import { type CaptchaAction, type CaptchaProvider, type SharedData } from '@/types';

/**
 * The three providers behave differently enough that letting the
 * difference reach a form would mean writing it out four times:
 *
 *  - reCAPTCHA v2 draws a checkbox the visitor ticks before submitting;
 *  - Turnstile draws a box that usually solves itself;
 *  - reCAPTCHA v3 draws nothing and mints a token at the moment of submit.
 *
 * So the contract is not "here is a token" but "ask me for one when you
 * are ready to submit". A form awaits `execute()`, gets a string or null,
 * and never learns which provider it is talking to.
 */
export interface CaptchaHandle {
    /** A token for this submit, or null when this form is not protected. */
    execute: () => Promise<string | null>;
    /**
     * Discard the current token and draw a fresh challenge.
     *
     * Every failed submit must call this. Tokens are single-use, so a
     * login with a good token and a wrong password consumes it, and the
     * retry would otherwise be refused with "already used" — a message
     * about something the person did not do.
     */
    reset: () => void;
    /** Whether this form asks for a token at all on this installation. */
    required: boolean;
}

interface CaptchaWidgetProps {
    action: CaptchaAction;
    className?: string;
}

interface RecaptchaApi {
    ready: (callback: () => void) => void;
    render: (container: HTMLElement, parameters: Record<string, unknown>) => number;
    reset: (widgetId?: number) => void;
    execute: (siteKey: string, options: { action: string }) => Promise<string>;
}

interface TurnstileApi {
    render: (container: HTMLElement, parameters: Record<string, unknown>) => string;
    reset: (widgetId?: string) => void;
    remove: (widgetId?: string) => void;
}

declare global {
    interface Window {
        grecaptcha?: RecaptchaApi;
        turnstile?: TurnstileApi;
    }
}

/**
 * Mirrors CaptchaProvider::scriptUrl() on the server. `render=explicit`
 * for the two that draw something: auto-rendering scans the page for a
 * magic class name, which is what made two widgets on one page break in
 * v1 and leaves us nothing to reset.
 */
function scriptUrl(provider: CaptchaProvider, siteKey: string): string {
    switch (provider) {
        case 'turnstile':
            return 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        case 'recaptcha_v2':
            return 'https://www.google.com/recaptcha/api.js?render=explicit';
        case 'recaptcha_v3':
            return `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
    }
}

export const CaptchaWidget = forwardRef<CaptchaHandle, CaptchaWidgetProps>(function CaptchaWidget({ action, className }, ref) {
    const { t } = useTranslation();
    const captcha = usePage<SharedData>().props.captcha;

    const required = captcha !== null && captcha.forms.includes(action);
    const invisible = captcha?.provider === 'recaptcha_v3';

    const container = useRef<HTMLDivElement | null>(null);
    const widgetId = useRef<number | string | null>(null);
    const token = useRef<string | null>(null);
    const [failed, setFailed] = useState(false);

    const clearToken = useCallback(() => {
        token.current = null;
    }, []);

    useEffect(() => {
        if (!required || !captcha) return;

        let cancelled = false;
        setFailed(false);

        loadScript(scriptUrl(captcha.provider, captcha.site_key))
            .then(() => {
                if (cancelled) return;

                // v3 draws nothing; there is nothing to render and the
                // token is minted later, in execute().
                if (captcha.provider === 'recaptcha_v3') return;

                const node = container.current;
                if (!node) return;

                if (captcha.provider === 'turnstile') {
                    widgetId.current =
                        window.turnstile?.render(node, {
                            sitekey: captcha.site_key,
                            // Echoed back by siteverify, which is how the
                            // server refuses a token minted on another form.
                            action,
                            callback: (value: string) => (token.current = value),
                            'expired-callback': clearToken,
                            'error-callback': clearToken,
                        }) ?? null;

                    return;
                }

                window.grecaptcha?.ready(() => {
                    if (cancelled || !container.current) return;

                    widgetId.current =
                        window.grecaptcha?.render(container.current, {
                            sitekey: captcha.site_key,
                            callback: (value: string) => (token.current = value),
                            'expired-callback': clearToken,
                            'error-callback': clearToken,
                        }) ?? null;
                });
            })
            .catch(() => {
                if (!cancelled) setFailed(true);
            });

        return () => {
            cancelled = true;
            clearToken();

            // Inertia keeps the document alive across navigations, so a
            // widget left behind would leak and its id would go stale.
            if (widgetId.current !== null) {
                if (typeof widgetId.current === 'string') {
                    window.turnstile?.remove(widgetId.current);
                } else {
                    window.grecaptcha?.reset(widgetId.current);
                }

                widgetId.current = null;
            }
        };
    }, [required, captcha, action, clearToken]);

    useImperativeHandle(
        ref,
        () => ({
            required,
            reset: () => {
                clearToken();

                if (widgetId.current === null) return;

                if (typeof widgetId.current === 'string') {
                    window.turnstile?.reset(widgetId.current);
                } else {
                    window.grecaptcha?.reset(widgetId.current);
                }
            },
            execute: async () => {
                if (!required || !captcha) return null;

                if (captcha.provider !== 'recaptcha_v3') return token.current;

                // v3 tokens live about two minutes, so minting one at page
                // load — as v1 did — expires it on any form somebody
                // stopped to think about. Mint it here, at submit.
                const api = window.grecaptcha;

                if (!api) return null;

                return Promise.race([
                    new Promise<string | null>((resolve) => {
                        api.ready(() => {
                            api.execute(captcha.site_key, { action })
                                .then(resolve)
                                .catch(() => resolve(null));
                        });
                    }),
                    new Promise<string | null>((resolve) => setTimeout(() => resolve(null), 10000)),
                ]);
            },
        }),
        [required, captcha, action, clearToken],
    );

    if (!required) return null;

    if (failed) {
        return (
            <p className={className ?? 'text-destructive text-sm'}>
                {t('The security check could not be loaded. Check that your browser or network is not blocking it, then reload the page.')}
            </p>
        );
    }

    if (invisible) {
        // Google requires this notice wherever the badge is not shown.
        return (
            <p className={className ?? 'text-muted-foreground text-xs'}>
                {t('This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply.')}
            </p>
        );
    }

    return <div ref={container} className={className} />;
});
