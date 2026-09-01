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
 * The three ways out are given in the order most installations should
 * consider them, and each says what it costs.
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

                <div className="space-y-5 text-sm">
                    <section className="space-y-2">
                        <h3 className="font-medium">{t('What is happening')}</h3>
                        <p className="text-muted-foreground">
                            {t(
                                'Your uploaded files are stored outside the web root, so no one can reach them by guessing a URL. Every download therefore goes through ProjectSend first, which checks that the person asking is allowed to have the file.',
                            )}
                        </p>
                        <p className="text-muted-foreground">
                            {t(
                                'After that check passes, the file still has to be sent. Right now PHP is doing that itself: it opens the file and writes it out to the visitor. The alternative is for PHP to tell your web server "this person may have this file, you send it" and finish immediately.',
                            )}
                        </p>
                    </section>

                    <section className="space-y-2">
                        <h3 className="font-medium">{t('Why that is worth changing')}</h3>
                        <p className="text-muted-foreground">
                            {t(
                                'Your server runs a fixed number of PHP worker processes. While PHP is sending a file, one of those workers is busy for the entire download — three minutes for a large file on a slow connection is three minutes that worker cannot answer any other request.',
                            )}
                        </p>
                        <p className="text-muted-foreground">
                            {t(
                                'A handful of people downloading large files at once can therefore occupy every worker you have, and the whole site stops responding — including for people who are only trying to log in. The processor is not busy, and memory is not full; the workers are simply all waiting on network transfers.',
                            )}
                        </p>
                        <p className="text-muted-foreground">
                            {t(
                                'Web servers send files far better than PHP can. They use the operating system call meant for it, handle resuming an interrupted download and seeking through a video, and one process can serve many transfers at once.',
                            )}
                        </p>
                    </section>

                    <section className="space-y-2">
                        <h3 className="font-medium">{t('Why it is set this way')}</h3>
                        <p className="text-muted-foreground">
                            {t(
                                'Handing a file to the web server needs a response header, and each server reads a different one. ProjectSend only sends that header when it knows the server will understand it, because a server that does not recognise it sends an empty response instead — which arrives as a 0-byte file.',
                            )}
                        </p>
                        <p className="text-muted-foreground">
                            {delivery.detected
                                ? t(
                                      'PHP sending the file is the option that works everywhere, so it is what ProjectSend falls back to when it does not recognise the web server in front of it.',
                                  )
                                : t(
                                      'This installation also has PROJECTSEND_FILE_DELIVERY set to php, so ProjectSend is sending files itself because it was told to, not because it could not tell what the server was.',
                                  )}
                        </p>
                    </section>

                    <section className="space-y-3">
                        <h3 className="font-medium">{t('How to change it')}</h3>

                        <div className="space-y-1">
                            <p className="font-medium">{t('Run ProjectSend behind nginx')}</p>
                            <p className="text-muted-foreground">
                                {t(
                                    'The configuration in INSTALL.md includes an internal location block that serves your storage directory. Nothing else needs setting: ProjectSend detects nginx on its own.',
                                )}
                            </p>
                        </div>

                        <div className="space-y-1">
                            <p className="font-medium">{t('Or enable X-Sendfile on Apache or LiteSpeed')}</p>
                            <p className="text-muted-foreground">
                                {t(
                                    'Apache needs the mod_xsendfile module installed and an XSendFilePath directive allowing your storage directory; LiteSpeed reads the same header without a module. Then set PROJECTSEND_FILE_DELIVERY=xsendfile in your .env file.',
                                )}{' '}
                                {t(
                                    'ProjectSend will not turn this on by itself, because it cannot see whether the directory has been allowed — and guessing wrong would produce empty downloads rather than slow ones.',
                                )}
                            </p>
                        </div>

                        <div className="space-y-1">
                            <p className="font-medium">{t('Or store files in object storage')}</p>
                            <p className="text-muted-foreground">
                                {t(
                                    'With S3-compatible or Google Cloud storage configured, downloads become a temporary link straight to the storage provider and your server is not involved in the transfer at all.',
                                )}
                            </p>
                        </div>
                    </section>

                    <p className="text-muted-foreground border-t pt-3 text-xs">
                        {t(
                            'This notice stays while PHP is sending files, including when that was chosen deliberately — the trade-off is the same either way, and a server that grows busier later will meet it without warning.',
                        )}
                    </p>
                </div>
            </DialogContent>
        </Dialog>
    );
}
