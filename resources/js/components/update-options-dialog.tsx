import { useState } from 'react';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The handful of update.sh options worth knowing about, behind a link
 * rather than printed on the page.
 *
 * The screen that tells somebody how to update has one job: the one
 * command. Listing every flag beside it would bury that command under
 * options most people never need — but two of them are the ones an
 * administrator most wants at exactly that moment ("can it back up for
 * me?", "can I just look?"), and finding them meant opening UPDATE.md on
 * a machine they are probably not sitting at.
 *
 * So: nothing on screen until asked for, and then four lines. The script
 * documents the rest with --help, and UPDATE.md documents the procedure.
 *
 * It opens from inside the update dialog as well as from the dashboard
 * card. A dialog on top of a dialog is unusual, and correct here — the
 * reader asked for a footnote to what they are already reading, and
 * Escape returns them to it.
 */
export function UpdateOptionsDialog() {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const options: { flag: string; description: string }[] = [
        { flag: '--backup', description: t('Dump the database before touching anything, and stop if the dump fails.') },
        { flag: '--check', description: t('Report which version is installed and which is available, and change nothing.') },
        { flag: '--i-have-a-backup', description: t('Skip the question about backups, for a run you are not sitting in front of.') },
        { flag: '--no-restart', description: t('Leave PHP and the worker alone, for when you restart them yourself.') },
    ];

    return (
        <>
            <button type="button" onClick={() => setOpen(true)} className="text-muted-foreground text-xs underline hover:no-underline">
                {t('Other options')}
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('Update options')}</DialogTitle>
                    </DialogHeader>

                    <div className="space-y-3 text-sm">
                        <p className="text-muted-foreground">
                            {t('Run the script with no options and it asks about each of these as it goes. These are for the runs where you already know the answer.')}
                        </p>

                        <dl className="divide-y">
                            {options.map((option) => (
                                <div key={option.flag} className="grid gap-1 py-2 sm:grid-cols-[10rem_1fr] sm:gap-3">
                                    <dt>
                                        <code className="bg-muted rounded px-1.5 py-0.5 text-xs">{option.flag}</code>
                                    </dt>
                                    <dd className="text-muted-foreground">{option.description}</dd>
                                </div>
                            ))}
                        </dl>

                        <p className="text-muted-foreground text-xs">
                            {t('sudo ./update.sh --help lists every option, and UPDATE.md walks through the whole procedure, including doing it by hand.')}
                        </p>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
