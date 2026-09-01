import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

export type DeliveryMethod = 'nginx' | 'xsendfile' | 'php';

export interface FileDelivery {
    method: DeliveryMethod;
    /** True when nobody set PROJECTSEND_FILE_DELIVERY and the server was detected. */
    detected: boolean;
}

/**
 * What "downloads are handled by PHP" actually means, for an administrator
 * who has just been told it and reasonably wants to know whether it
 * matters.
 *
 * Written to be accurate rather than reassuring, and specific rather than
 * simplified. The reader is somebody who installed a PHP application on a
 * web server; they can be told what a worker process is. What they must
 * not be told is a vague "performance may be affected", which gives them
 * nothing to decide with, nor an alarming "downloads are broken", which is
 * false — everything works, it just does not scale.
 *
 * The three ways out are listed in the order most installations should
 * consider them.
 */
export function FileDeliveryDialog({
    delivery,
    open,
    onOpenChange,
}: {
    delivery: FileDelivery;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{t('Downloads are being sent by PHP')}</DialogTitle>
                    <DialogDescription>
                        {t('Everything works. This is about how much load your server can take, not about anything being broken.')}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 text-sm">
                    <section className="space-y-1">
                        <h3 className="font-medium">{t('What is happening')}</h3>
                        <p className="text-muted-foreground">
                            {t(
                                'Your files are stored outside the web root, so every download goes through ProjectSend first to check the person is allowed to have it. After that check, PHP is opening the file and sending it. The alternative is for PHP to tell your web server "send this file" and finish immediately.',
                            )}
                        </p>
                    </section>

                    <section className="space-y-1">
                        <h3 className="font-medium">{t('Why that is worth changing')}</h3>
                        <p className="text-muted-foreground">
                            {t(
                                'While PHP sends a file, one worker process is busy for the whole download. A few large downloads at once can occupy every worker you have, and the site stops responding — including for people only trying to log in, with the processor sitting idle.',
                            )}
                        </p>
                        <p className="text-muted-foreground">
                            {t(
                                'Web servers do this far better: they use the system call meant for it, handle resuming and video seeking, and one process serves many transfers at once.',
                            )}
                        </p>
                    </section>

                    <section className="space-y-1">
                        <h3 className="font-medium">{t('Why it is set this way')}</h3>
                        <p className="text-muted-foreground">
                            {t(
                                'Handing a file over needs a response header, and every web server reads a different one. ProjectSend only sends it when it knows the server will act on it, because a server that ignores it sends an empty response instead — which arrives as a 0-byte file.',
                            )}{' '}
                            {delivery.detected
                                ? t('It did not recognise the server in front of it, so it fell back to the option that works everywhere.')
                                : t('Here it is sending files itself because PROJECTSEND_FILE_DELIVERY is set to php.')}
                        </p>
                    </section>

                    <section className="space-y-1">
                        <h3 className="font-medium">{t('How to change it')}</h3>
                        <ul className="text-muted-foreground list-disc space-y-1 pl-5">
                            <li>{t('Run ProjectSend behind nginx, with the location block from INSTALL.md. Nothing else to set — it is detected.')}</li>
                            <li>
                                {t(
                                    'Or on Apache, install mod_xsendfile and allow your storage directory with XSendFilePath; LiteSpeed needs no module. Then set PROJECTSEND_FILE_DELIVERY=xsendfile.',
                                )}
                            </li>
                            <li>{t('Or store files in S3-compatible or Google Cloud storage, so downloads never touch your server at all.')}</li>
                        </ul>
                    </section>

                    <p className="text-muted-foreground border-t pt-3 text-xs">
                        {t('This notice stays while PHP is sending files, including when that was chosen deliberately.')}
                    </p>
                </div>
            </DialogContent>
        </Dialog>
    );
}
