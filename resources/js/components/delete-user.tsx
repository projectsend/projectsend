import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

// Components...
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import HeadingSmall from '@/components/heading-small';
import { useTranslation } from '@/hooks/use-translation';

import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';

interface DeleteUserProps {
    graceDays: number;
    /** Their files stop being served to anybody but staff at once. */
    filesWithdrawn: boolean;
    /** Their files are deleted at once rather than with the account. */
    filesDeletedImmediately: boolean;
}

export default function DeleteUser({ graceDays, filesWithdrawn, filesDeletedImmediately }: DeleteUserProps) {
    const { t } = useTranslation();

    // What happens to the files they uploaded, said before they confirm.
    // Deleted outranks withdrawn: a file that is gone is also not served.
    const filesNotice = filesDeletedImmediately
        ? t('The files you uploaded are deleted as soon as you confirm. They cannot be recovered.')
        : filesWithdrawn
          ? t('The files you uploaded stop being available to everyone you shared them with as soon as you confirm.')
          : null;
    const passwordInput = useRef<HTMLInputElement>(null);
    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({ password: '' });

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        clearErrors();
        reset();
    };

    return (
        <div className="space-y-6">
            <HeadingSmall
                title={t('Delete account')}
                description={t('Deactivate your account now; it is permanently erased after :days days', { days: graceDays })}
            />
            <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                    <p className="font-medium">{t('Warning')}</p>
                    <p className="text-sm">
                        {t(
                            'Your account is deactivated immediately. After :days days it is permanently erased, including your identifying details in the activity log — this cannot be undone.',
                            { days: graceDays },
                        )}
                    </p>
                    {filesNotice !== null && <p className="text-sm font-medium">{filesNotice}</p>}
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button variant="destructive">{t('Delete account')}</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>{t('Are you sure you want to delete your account?')}</DialogTitle>
                        <DialogDescription>
                            {t('Your account will be deactivated now and permanently erased after :days days. Enter your password to confirm.', {
                                days: graceDays,
                            })}
                        </DialogDescription>
                        {filesDeletedImmediately && <p className="text-destructive text-sm font-medium">{filesNotice}</p>}
                        <form className="space-y-6" onSubmit={deleteUser}>
                            <div className="grid gap-2">
                                <Label htmlFor="password" className="sr-only">
                                    {t('Password')}
                                </Label>

                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    ref={passwordInput}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder={t('Password')}
                                    autoComplete="current-password"
                                />

                                <InputError message={errors.password} />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" onClick={closeModal}>
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>

                                <Button variant="destructive" disabled={processing} asChild>
                                    <button type="submit">{t('Delete account')}</button>
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
