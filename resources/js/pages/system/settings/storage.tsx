import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Cylinder, HardDrive, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SavedIndicator } from '@/components/save-button';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface StorageLocationRow {
    id: number;
    name: string;
    client_note: string;
    local: boolean;
    provider: string;
    is_default: boolean;
    accepts_uploads: boolean;
    configured: boolean;
    location: string;
    region: string;
    clients_count: number;
    files_count: number;
    use_instance_role: boolean;
    access_key: string;
    has_secret: boolean;
    has_key_file: boolean;
    bucket: string;
    endpoint: string;
    use_path_style: boolean;
    root: string;
}

interface StorageSettingsProps {
    locations: StorageLocationRow[];
    upload_notification_names_location: boolean;
    test_result: string | null;
}

export default function StorageSettings({ locations, upload_notification_names_location, test_result }: StorageSettingsProps) {
    const { t } = useTranslation();
    const [sheet, setSheet] = useState<StorageLocationRow | 'new' | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Storage'), href: '/system/settings/storage' },
    ];

    const general = useForm({
        default_location_id: String(locations.find((location) => location.is_default)?.id ?? ''),
        upload_notification_names_location: upload_notification_names_location,
    });

    const saveGeneral: FormEventHandler = (e) => {
        e.preventDefault();
        general.patch(route('system-settings.storage.update'), { preserveScroll: true });
    };

    const providerLabel = (location: StorageLocationRow) => {
        if (location.local) return t('Local disk');
        const name = location.provider === 'gcs' ? t('Google Cloud Storage') : t('S3-compatible');

        return location.region !== '' ? `${name} · ${location.region}` : name;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Storage settings')} />

            <div className="px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-x-4 [&>:not(:first-child)]:mb-6">
                    <Heading
                        title={t('Storage settings')}
                        description={t(
                            'Where uploaded files are kept. Give a client one or more locations on their account page; the first one is used unless they choose another when uploading.',
                        )}
                    />
                    <Button onClick={() => setSheet('new')}>
                        <Plus className="size-4" />
                        {t('Add location')}
                    </Button>
                </div>

                <form onSubmit={saveGeneral} className="mb-6 max-w-3xl space-y-4 rounded-lg border p-4">
                    <div className="grid gap-x-6 gap-y-2 sm:grid-cols-[minmax(0,1fr)_16rem] sm:items-center">
                        <div>
                            <Label htmlFor="default_location">{t('Default for new uploads')}</Label>
                            <p className="text-muted-foreground text-sm">
                                {t('Used for staff uploads, and for clients who have no locations of their own.')}
                            </p>
                        </div>
                        <Select value={general.data.default_location_id} onValueChange={(value) => general.setData('default_location_id', value)}>
                            <SelectTrigger id="default_location">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {locations
                                    .filter((location) => location.accepts_uploads && location.configured)
                                    .map((location) => (
                                        <SelectItem key={location.id} value={String(location.id)}>
                                            {location.name}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                        <InputError message={general.errors.default_location_id} className="sm:col-span-2" />
                    </div>

                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="upload_notification_names_location"
                            checked={general.data.upload_notification_names_location}
                            onCheckedChange={(checked) => general.setData('upload_notification_names_location', checked === true)}
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="upload_notification_names_location" className="font-normal">
                                {t('Name the storage location in upload notifications')}
                            </Label>
                            <p className="text-muted-foreground text-sm">
                                {t('The email telling staff a client uploaded a file says where it was stored.')}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={general.processing}>
                            {t('Save')}
                        </Button>
                        <SavedIndicator recentlySuccessful={general.recentlySuccessful} />
                    </div>
                </form>

                <TableShell
                    columns={[t('Location'), t('Clients'), t('Files'), t('New uploads'), { label: t('Actions'), srOnly: true }]}
                    emptyMessage={null}
                    isEmpty={false}
                >
                    {locations.map((location) => (
                        <tr key={location.id} className="border-b last:border-0">
                            <td className="px-4 py-3">
                                <div className="flex min-w-56 items-center gap-3">
                                    <span
                                        className={`flex size-9 shrink-0 items-center justify-center rounded-md ${location.local ? 'bg-muted text-muted-foreground' : 'bg-primary/10 text-primary'}`}
                                    >
                                        {location.local ? <HardDrive className="size-4" /> : <Cylinder className="size-4" />}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="flex flex-wrap items-center gap-1.5 font-medium">
                                            {location.name}
                                            {location.is_default && <Badge variant="secondary">{t('Default')}</Badge>}
                                        </p>
                                        <p className="text-muted-foreground text-xs">{providerLabel(location)}</p>
                                        <p className="text-muted-foreground truncate font-mono text-[11px]">{location.location}</p>
                                    </div>
                                </div>
                            </td>
                            <td className="px-4 py-3 text-right tabular-nums">{location.clients_count}</td>
                            <td className="px-4 py-3 text-right tabular-nums">{location.files_count}</td>
                            <td className="px-4 py-3 whitespace-nowrap">
                                {!location.configured ? (
                                    <Badge variant="destructive">{t('Not set up')}</Badge>
                                ) : location.accepts_uploads ? (
                                    <Badge variant="success">{t('Accepting uploads')}</Badge>
                                ) : (
                                    <Badge variant="outline">{t('Existing files only')}</Badge>
                                )}
                            </td>
                            <td className="px-4 py-3 text-right">
                                <div className="flex justify-end gap-1">
                                    <Button variant="outline" size="sm" onClick={() => setSheet(location)}>
                                        {t('Edit')}
                                    </Button>
                                    {!location.local && !location.is_default && location.files_count === 0 && (
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                                                    {t('Delete')}
                                                </Button>
                                            }
                                            title={t('Remove this storage location?')}
                                            description={t('Clients who had it lose it from their list. It holds no files, so nothing else changes.')}
                                            confirmLabel={t('Remove')}
                                            onConfirm={() =>
                                                router.delete(route('system-settings.storage.locations.destroy', location.id), {
                                                    preserveScroll: true,
                                                })
                                            }
                                        />
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))}
                </TableShell>
                <p className="text-muted-foreground mt-2 max-w-3xl text-xs">
                    {t(
                        'A location holding files cannot be removed. Turn off its new uploads instead: its files stay available to everyone they are shared with.',
                    )}
                </p>
            </div>

            <Sheet open={sheet !== null} onOpenChange={(open) => !open && setSheet(null)}>
                <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                    {sheet !== null && (
                        <LocationForm
                            key={sheet === 'new' ? 'new' : sheet.id}
                            location={sheet === 'new' ? null : sheet}
                            testResult={test_result}
                            onDone={() => setSheet(null)}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}

function LocationForm({ location, testResult, onDone }: { location: StorageLocationRow | null; testResult: string | null; onDone: () => void }) {
    const { t } = useTranslation();
    const [testing, setTesting] = useState(false);
    const isLocal = location?.local === true;

    const { data, setData, post, patch, processing, errors } = useForm({
        name: location?.name ?? '',
        client_note: location?.client_note ?? '',
        accepts_uploads: location?.accepts_uploads ?? true,
        provider: location?.provider === 'gcs' ? 'gcs' : 's3',
        use_instance_role: location?.use_instance_role ?? false,
        access_key: location?.access_key ?? '',
        secret: '',
        key_file: '',
        bucket: location?.bucket ?? '',
        region: location?.region ?? '',
        endpoint: location?.endpoint ?? '',
        use_path_style: location?.use_path_style ?? false,
        root: location?.root ?? '',
    });

    const isGcs = data.provider === 'gcs';
    const usesInstanceRole = !isGcs && data.use_instance_role;
    const hasSecret = location?.has_secret === true;
    const hasKeyFile = location?.has_key_file === true;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };

        if (location === null) post(route('system-settings.storage.locations.store'), options);
        else patch(route('system-settings.storage.locations.update', location.id), options);
    };

    const testConnection = () => {
        setTesting(true);
        router.post(
            route('system-settings.storage.test'),
            {
                location_id: location?.id ?? null,
                provider: data.provider,
                use_instance_role: data.use_instance_role,
                access_key: data.access_key,
                secret: data.secret,
                key_file: data.key_file,
                bucket: data.bucket,
                region: data.region,
                endpoint: data.endpoint,
                use_path_style: data.use_path_style,
            },
            { preserveScroll: true, preserveState: true, onFinish: () => setTesting(false) },
        );
    };

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <SheetHeader className="border-b p-6 pr-12">
                <SheetTitle>{location === null ? t('Add location') : t('Edit location')}</SheetTitle>
                <SheetDescription>
                    {location === null
                        ? t('A bucket that uploaded files can be kept in.')
                        : isLocal
                          ? t('This server’s own disk.')
                          : location.location}
                </SheetDescription>
            </SheetHeader>

            <div className="flex-1 space-y-5 overflow-y-auto p-6">
                <div className="grid gap-2">
                    <Label htmlFor="location_name">{t('Name')}</Label>
                    <Input id="location_name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    <p className="text-muted-foreground text-sm">
                        {t('Clients see this name, and the note below, when they choose where to upload. They never see the bucket.')}
                    </p>
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="location_note">{t('Note for clients')}</Label>
                    <Input
                        id="location_note"
                        value={data.client_note}
                        placeholder={t('Optional')}
                        onChange={(e) => setData('client_note', e.target.value)}
                    />
                    <InputError message={errors.client_note} />
                </div>

                <div className="flex items-start gap-2 rounded-md border p-3">
                    <Checkbox
                        id="accepts_uploads"
                        checked={data.accepts_uploads}
                        onCheckedChange={(checked) => setData('accepts_uploads', checked === true)}
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="accepts_uploads" className="font-normal">
                            {t('Accept new uploads')}
                        </Label>
                        <p className="text-muted-foreground text-sm">{t('Off keeps serving the files already here.')}</p>
                        <InputError message={errors.accepts_uploads} />
                    </div>
                </div>

                {!isLocal && (
                    <>
                        <div className="grid gap-2 border-t pt-5">
                            <Label htmlFor="storage_provider">{t('Provider')}</Label>
                            <Select value={data.provider} onValueChange={(value) => setData('provider', value)}>
                                <SelectTrigger id="storage_provider" className="w-64">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="s3">{t('S3-compatible')}</SelectItem>
                                    <SelectItem value="gcs">{t('Google Cloud Storage')}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.provider} />
                        </div>

                        {isGcs ? (
                            <div className="grid gap-2">
                                <Label htmlFor="storage_key_file">{t('Service account key')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t(
                                        'Paste the JSON key file for a service account with object read and write access to the bucket. It is stored encrypted and never shown again.',
                                    )}
                                </p>
                                <Textarea
                                    id="storage_key_file"
                                    rows={6}
                                    className="font-mono text-xs"
                                    placeholder={hasKeyFile ? t('Unchanged') : '{ "type": "service_account", ... }'}
                                    value={data.key_file}
                                    onChange={(e) => setData('key_file', e.target.value)}
                                />
                                <InputError message={errors.key_file} />
                            </div>
                        ) : (
                            <>
                                <div className="flex items-start gap-2">
                                    <Checkbox
                                        id="use_instance_role"
                                        checked={data.use_instance_role}
                                        onCheckedChange={(checked) => setData('use_instance_role', checked === true)}
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor="use_instance_role" className="font-normal">
                                            {t("Authenticate as this server's IAM role")}
                                        </Label>
                                        <p className="text-muted-foreground text-sm">
                                            {t(
                                                'For installations running on AWS with a role already attached — an ECS task role, an EC2 instance profile, EKS/IRSA. ProjectSend asks the AWS SDK for temporary credentials instead of storing an access key. Leave this off for MinIO, Backblaze, Wasabi and anything else that needs a key and secret.',
                                            )}
                                        </p>
                                        {usesInstanceRole && hasSecret && (
                                            <p className="text-muted-foreground text-sm">
                                                {t('Saving will delete the access key and secret currently stored here.')}
                                            </p>
                                        )}
                                        <InputError message={errors.use_instance_role} />
                                    </div>
                                </div>

                                {!usesInstanceRole && (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="storage_access_key">{t('Access key')}</Label>
                                            <Input
                                                id="storage_access_key"
                                                value={data.access_key}
                                                onChange={(e) => setData('access_key', e.target.value)}
                                            />
                                            <InputError message={errors.access_key} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="storage_secret">{t('Secret key')}</Label>
                                            <Input
                                                id="storage_secret"
                                                type="password"
                                                placeholder={hasSecret ? t('Unchanged') : ''}
                                                value={data.secret}
                                                onChange={(e) => setData('secret', e.target.value)}
                                            />
                                            <InputError message={errors.secret} />
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="storage_bucket">{t('Bucket')}</Label>
                                <Input id="storage_bucket" value={data.bucket} onChange={(e) => setData('bucket', e.target.value)} />
                                <InputError message={errors.bucket} />
                            </div>
                            {!isGcs && (
                                <div className="grid gap-2">
                                    <Label htmlFor="storage_region">{t('Region')}</Label>
                                    <Input id="storage_region" value={data.region} onChange={(e) => setData('region', e.target.value)} />
                                    <InputError message={errors.region} />
                                </div>
                            )}
                        </div>

                        {!isGcs && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="storage_endpoint">{t('Custom endpoint')}</Label>
                                    <p className="text-muted-foreground text-sm">
                                        {t('Leave blank for AWS S3. Set this to use an S3-compatible service such as MinIO or Backblaze.')}
                                    </p>
                                    <Input
                                        id="storage_endpoint"
                                        value={data.endpoint}
                                        onChange={(e) => setData('endpoint', e.target.value)}
                                        placeholder="https://s3.example.com"
                                    />
                                    <InputError message={errors.endpoint} />
                                </div>

                                <div className="flex items-start gap-2">
                                    <Checkbox
                                        id="use_path_style"
                                        checked={data.use_path_style}
                                        onCheckedChange={(checked) => setData('use_path_style', checked === true)}
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor="use_path_style" className="font-normal">
                                            {t('Use path-style addressing')}
                                        </Label>
                                        <p className="text-muted-foreground text-sm">
                                            {t('Required by most S3-compatible services (MinIO, etc). Leave off for AWS S3.')}
                                        </p>
                                    </div>
                                </div>
                            </>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="storage_root">{t('Path prefix')}</Label>
                            <p className="text-muted-foreground text-sm">{t('Optional. Confines uploads to a subfolder within the bucket.')}</p>
                            <Input id="storage_root" value={data.root} onChange={(e) => setData('root', e.target.value)} />
                            <InputError message={errors.root} />
                        </div>

                        {testResult && (
                            <div className="grid gap-2">
                                <Label htmlFor="test_result">{t('Test result')}</Label>
                                <Textarea id="test_result" value={testResult} readOnly rows={3} className="bg-muted text-muted-foreground" />
                            </div>
                        )}
                    </>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2 border-t p-4">
                {!isLocal && (
                    <Button type="button" variant="outline" onClick={testConnection} disabled={testing || data.bucket.trim() === ''}>
                        {t('Test connection')}
                    </Button>
                )}
                <span className="flex-1" />
                <Button type="button" variant="ghost" onClick={onDone}>
                    {t('Cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {t('Save')}
                </Button>
            </div>
        </form>
    );
}
