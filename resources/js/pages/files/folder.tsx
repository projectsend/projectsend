import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Copy, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton, SavedIndicator } from '@/components/save-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { slugify } from '@/lib/utils';

interface Named {
    id: number;
    name: string;
}

interface FolderPageProps {
    folder: {
        id: number;
        name: string;
        parent_id: number | null;
        public: boolean;
        allow_client_uploads: boolean;
        slug: string;
    };
    public_url: string | null;
    breadcrumb: Named[];
    can_update: boolean;
    can_manage_public: boolean;
    assigned_clients: Named[];
    assigned_groups: Named[];
    available_clients: Named[];
    available_groups: Named[];
}

type Tab = 'general' | 'sharing' | 'public';

export default function FolderPage({
    folder,
    public_url,
    can_update,
    can_manage_public,
    assigned_clients,
    assigned_groups,
    available_clients,
    available_groups,
}: FolderPageProps) {
    const { t } = useTranslation();
    const [tab, setTab] = useState<Tab>('general');
    const [target, setTarget] = useState('');
    const [slugTouched, setSlugTouched] = useState(folder.slug !== '');
    const [copiedLink, setCopiedLink] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Files'), href: '/files' },
        { title: folder.name, href: route('folders.share', folder.id) },
    ];

    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        name: folder.name,
        public: folder.public,
        slug: folder.slug,
        allow_client_uploads: folder.allow_client_uploads,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('folders.update', folder.id), { preserveScroll: true });
    };

    const copyLink = (url: string) => {
        void navigator.clipboard.writeText(url);
        setCopiedLink(true);
        window.setTimeout(() => setCopiedLink(false), 1500);
    };

    const share = () => {
        if (target === '') return;
        const [type, id] = target.split(':');
        router.post(
            route('folders.assignments.store', folder.id),
            { type, id: Number(id) },
            { preserveScroll: true, onSuccess: () => setTarget('') },
        );
    };

    const unshare = (type: 'client' | 'group', named: Named) => {
        router.delete(route('folders.assignments.destroy', folder.id), { data: { type, id: named.id }, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={folder.name} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <Heading title={folder.name} description={t('Folder')} />
                    <Button variant="outline" asChild>
                        <a href={route('files.index', { folder: folder.id })}>{t('Open folder')}</a>
                    </Button>
                </div>

                {can_update && (
                    <nav className="mt-6 flex gap-1 border-b">
                        {(['general', 'sharing', 'public'] as Tab[])
                            .filter((tabKey) => tabKey !== 'public' || can_manage_public)
                            .map((tabKey) => (
                                <button
                                    key={tabKey}
                                    onClick={() => setTab(tabKey)}
                                    className={`border-b-2 px-3 py-2 text-sm ${tab === tabKey ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                                >
                                    {tabKey === 'general' ? t('General') : tabKey === 'sharing' ? t('Sharing') : t('Public')}
                                </button>
                            ))}
                    </nav>
                )}

                <div className="mt-6">
                    {!can_update ? null : tab === 'general' ? (
                        <form onSubmit={submit} className="grid max-w-md gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => {
                                        setData('name', e.target.value);
                                        if (!slugTouched) setData('slug', slugify(e.target.value));
                                    }}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                        </form>
                    ) : tab === 'sharing' ? (
                        <div className="max-w-xl space-y-4">
                            <p className="text-muted-foreground text-sm">{t('Sharing a folder shares everything inside it, now and later')}</p>

                            <div className="flex gap-2">
                                <Select value={target} onValueChange={setTarget}>
                                    <SelectTrigger className="w-72">
                                        <SelectValue placeholder={t('Select a client or group')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {available_clients.map((client) => (
                                            <SelectItem key={`client-${client.id}`} value={`client:${client.id}`}>
                                                {client.name}
                                            </SelectItem>
                                        ))}
                                        {available_groups.map((group) => (
                                            <SelectItem key={`group-${group.id}`} value={`group:${group.id}`}>
                                                {t('Group')}: {group.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button type="button" onClick={share} disabled={target === ''}>
                                    {t('Share')}
                                </Button>
                            </div>

                            <div className="rounded-lg border">
                                {assigned_clients.length === 0 && assigned_groups.length === 0 && (
                                    <p className="text-muted-foreground px-4 py-6 text-center text-sm">{t('Not shared with anyone yet.')}</p>
                                )}
                                {assigned_clients.map((client) => (
                                    <div key={`client-${client.id}`} className="flex items-center justify-between border-b px-4 py-2 last:border-0">
                                        <p className="text-sm font-medium">{client.name}</p>
                                        <Button variant="ghost" size="sm" onClick={() => unshare('client', client)}>
                                            <X className="size-4" />
                                            <span className="sr-only">{t('Remove')}</span>
                                        </Button>
                                    </div>
                                ))}
                                {assigned_groups.map((group) => (
                                    <div key={`group-${group.id}`} className="flex items-center justify-between border-b px-4 py-2 last:border-0">
                                        <p className="text-sm font-medium">
                                            {group.name} <Badge variant="secondary">{t('Group')}</Badge>
                                        </p>
                                        <Button variant="ghost" size="sm" onClick={() => unshare('group', group)}>
                                            <X className="size-4" />
                                            <span className="sr-only">{t('Remove')}</span>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <form onSubmit={submit} className="max-w-xl space-y-4">
                            <div className="grid gap-2">
                                <div className="flex items-start gap-2">
                                    <Checkbox
                                        id="folder-public"
                                        checked={data.public}
                                        onCheckedChange={(checked) => setData('public', checked === true)}
                                    />
                                    <Label htmlFor="folder-public" className="font-normal">
                                        {t('Make this folder public. Every file inside it — now and later, at any depth — becomes public too.')}
                                    </Label>
                                </div>
                                <InputError message={errors.public} />

                                {data.public && (
                                    <div className="mt-2 grid gap-4 pl-6">
                                        <div className="grid gap-2">
                                            <Label htmlFor="folder-slug">{t('URL slug')}</Label>
                                            <Input
                                                id="folder-slug"
                                                value={data.slug}
                                                onChange={(e) => {
                                                    setSlugTouched(true);
                                                    setData('slug', e.target.value);
                                                }}
                                                required
                                            />
                                            <p className="text-muted-foreground text-sm">{t('Used in the public folder URL.')}</p>
                                            <InputError message={errors.slug} />
                                        </div>

                                        <div className="flex items-start gap-2">
                                            <Checkbox
                                                id="folder-allow-client-uploads"
                                                checked={data.allow_client_uploads}
                                                onCheckedChange={(checked) => setData('allow_client_uploads', checked === true)}
                                            />
                                            <Label htmlFor="folder-allow-client-uploads" className="font-normal">
                                                {t('Allow clients to add files to this folder from their portal.')}
                                            </Label>
                                        </div>
                                        <InputError message={errors.allow_client_uploads} />
                                    </div>
                                )}
                            </div>

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    {t('Save')}
                                </Button>

                                {public_url && (
                                    <Button type="button" variant="outline" onClick={() => copyLink(public_url)}>
                                        {copiedLink ? <Check className="size-4" /> : <Copy className="size-4" />}
                                        {t('Copy link')}
                                    </Button>
                                )}

                                <SavedIndicator recentlySuccessful={recentlySuccessful} />
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
