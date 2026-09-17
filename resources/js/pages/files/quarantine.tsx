import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useState } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { TableShell } from '@/components/table-shell';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { formatBytes } from '@/lib/format-bytes';

interface QuarantinedFile {
    id: number;
    name: string;
    original_name: string;
    size: number;
    uploader: string | null;
    threat: string | null;
    status: string;
    scanned_at: string | null;
    /** It could be downloaded before it was flagged — so somebody may already have it. */
    was_available: boolean;
    downloads_count: number;
}

interface QuarantineProps {
    files: QuarantinedFile[];
    pagination: PaginationMeta;
}

function ReleaseDialog({ file }: { file: QuarantinedFile }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ reason: '' });

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) reset();
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    {t('Release')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Release ":name"?', { name: file.name })}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'The scanner reported a threat in this file. Releasing it makes it downloadable again for everyone it was shared with. Only do this if you are sure the report is wrong.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="reason">{t('Why are you releasing it?')}</Label>
                    <Textarea id="reason" value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
                    <p className="text-muted-foreground text-xs">{t('This is recorded in the activity log, with your name.')}</p>
                    <InputError message={errors.reason} />
                </div>

                <DialogFooter>
                    <Button variant="ghost" type="button" onClick={() => setOpen(false)}>
                        {t('Cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={processing || data.reason.trim() === ''}
                        onClick={() =>
                            post(route('files.release', file.id), {
                                preserveScroll: true,
                                onSuccess: () => setOpen(false),
                            })
                        }
                    >
                        {t('Release file')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function Quarantine({ files, pagination }: QuarantineProps) {
    const { t } = useTranslation();
    const { dateTime } = useFormatDate();
    const { auth } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('All files'), href: '/files' },
        { title: t('Quarantine'), href: '/files/quarantine' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Quarantine')} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={t('Quarantine')} description={t('Files the virus scanner refused. Nobody can download these.')} />

                    {/* The way back to the screen that decides what gets
                        refused, for whoever may change it. */}
                    {auth.permissions.includes('edit_settings') && (
                        <Button variant="outline" asChild>
                            <Link href={route('system-settings.virus-scanning.edit')}>{t('Virus scanning settings')}</Link>
                        </Button>
                    )}
                </div>

                {files.some((file) => file.was_available) && (
                    <Alert variant="destructive" className="mb-4">
                        <ShieldAlert className="size-4" />
                        <AlertDescription>
                            {t(
                                'Some of these were downloadable before they were checked, because the scanner was unreachable at the time. Their download history shows whether anybody took a copy.',
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <TableShell
                    columns={[t('File'), t('Uploaded by'), t('Found'), t('Detected'), null]}
                    isEmpty={files.length === 0}
                    emptyMessage={<>{t('Nothing is in quarantine.')}</>}
                >
                    {files.map((file) => (
                        <tr key={file.id} className="border-b last:border-0">
                            <td className="px-4 py-2.5">
                                {/* The file itself, for somebody deciding
                                    whether the scanner is right: who it was
                                    shared with, where it came from. It is no
                                    longer listed in the library. */}
                                <Link href={route('files.edit', file.id)} className="font-medium hover:underline">
                                    {file.name}
                                </Link>
                                <div className="text-muted-foreground text-xs">
                                    {file.original_name} · {formatBytes(file.size)}
                                </div>
                            </td>
                            <td className="text-muted-foreground px-4 py-2.5">{file.uploader ?? t('(deleted account)')}</td>
                            <td className="px-4 py-2.5">
                                <Badge variant="destructive">{file.threat ?? t('Unknown')}</Badge>
                                {file.was_available && (
                                    <div className="text-muted-foreground mt-1 text-xs">
                                        {t('Was downloadable before this. Downloads so far: :count', { count: file.downloads_count })}
                                    </div>
                                )}
                            </td>
                            <td className="text-muted-foreground px-4 py-2.5">{dateTime(file.scanned_at)}</td>
                            <td className="px-4 py-2.5 text-right">
                                <ReleaseDialog file={file} />
                            </td>
                        </tr>
                    ))}
                </TableShell>

                <Pagination meta={pagination} />
            </div>
        </AppLayout>
    );
}
