import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

interface Refused {
    // Typed as pending, but what the start event hands over is the request
    // as sent: the visit plus its callbacks (useForm's among them), which
    // is what lets a replay finish the form's own submission.
    visit: PendingVisit;
    hasPassword: boolean;
}

const visitKey = (method: string, url: string) => `${method.toUpperCase()} ${url}`;

/**
 * The browser half of RequirePasswordConfirmation.
 *
 * A write that needs the password re-proved comes back as a 423 instead of
 * a redirect. Inertia calls that an "invalid" response; this catches it,
 * asks for the password over the page the user is on, and then sends the
 * refused request again, exactly as it was first sent -- same data, same
 * callbacks -- so the form that sent it carries on as if nothing had
 * happened. Nothing navigates, so nothing typed into the form is lost.
 *
 * Mounted once, around every page, in app.tsx.
 */
export function PasswordConfirmationDialog() {
    const { t } = useTranslation();

    // Writes in flight, by method and URL. The invalid event carries only
    // the response, so this is how a refusal finds the visit that caused it.
    const inFlight = useRef(new Map<string, PendingVisit>());

    const [refused, setRefused] = useState<Refused | null>(null);
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            const visit = event.detail.visit;
            if (visit.method !== 'get') {
                inFlight.current.set(visitKey(visit.method, visit.url.href), visit);
            }
        });

        // After `invalid` for the same request, so the visit is still here
        // when a refusal needs it.
        const removeFinish = router.on('finish', (event) => {
            const visit = event.detail.visit;
            inFlight.current.delete(visitKey(visit.method, visit.url.href));
        });

        const removeInvalid = router.on('invalid', (event) => {
            const response = event.detail.response;
            if (response.status !== 423 || response.headers['x-password-confirmation'] !== 'required') {
                return;
            }

            const visit = inFlight.current.get(visitKey(response.config.method ?? '', response.config.url ?? ''));
            if (!visit) {
                return;
            }

            // Stops Inertia's own error modal, which would show the raw JSON.
            event.preventDefault();

            setPassword('');
            setError(undefined);
            setRefused({ visit, hasPassword: response.data?.has_password !== false });
        });

        return () => {
            removeStart();
            removeFinish();
            removeInvalid();
        };
    }, []);

    // Cancelling drops the refused request, and the password with it.
    const close = () => {
        setPassword('');
        setRefused(null);
    };

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();
        if (!refused) {
            return;
        }

        setProcessing(true);
        setError(undefined);

        try {
            await axios.post(route('password.confirm.store'), { password });
        } catch (failure) {
            setPassword('');
            if (axios.isAxiosError(failure) && failure.response?.status === 422) {
                setError(failure.response.data?.errors?.password?.[0] ?? t('Something went wrong. Please try again.'));
            } else if (axios.isAxiosError(failure) && failure.response?.status === 429) {
                setError(t('Too many attempts. Wait a minute and try again.'));
            } else {
                setError(t('Something went wrong. Please try again.'));
            }
            setProcessing(false);

            return;
        }

        setProcessing(false);
        setPassword('');
        setRefused(null);

        // Sent again as it was. The three state flags describe the first
        // attempt, which finished; carried over, they would mark the new
        // one finished before it started.
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        const { url, completed, cancelled, interrupted, ...options } = refused.visit;
        router.visit(url, options);
    };

    return (
        <Dialog open={refused !== null} onOpenChange={(open) => !open && close()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Confirm your password')}</DialogTitle>
                    <DialogDescription>
                        {t('This is a secure area of the application. Please confirm your password before continuing.')}
                    </DialogDescription>
                </DialogHeader>

                {refused && !refused.hasPassword ? (
                    <div className="space-y-4">
                        <p className="text-muted-foreground text-sm">
                            {t('You sign in through a connected account, so there is no password here to confirm. Set one to continue.')}
                        </p>
                        <DialogFooter>
                            <Button variant="ghost" type="button" onClick={close}>
                                {t('Cancel')}
                            </Button>
                            <Button asChild>
                                <a href={route('password.edit')}>{t('Set a password')}</a>
                            </Button>
                        </DialogFooter>
                    </div>
                ) : (
                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password-confirmation-dialog-password">{t('Password')}</Label>
                            <Input
                                id="password-confirmation-dialog-password"
                                type="password"
                                name="password"
                                placeholder={t('Password')}
                                autoComplete="current-password"
                                value={password}
                                autoFocus
                                onChange={(e) => setPassword(e.target.value)}
                            />
                            <InputError message={error} />
                        </div>

                        <DialogFooter>
                            <Button variant="ghost" type="button" onClick={close}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" disabled={processing || password === ''}>
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                {t('Confirm password')}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
