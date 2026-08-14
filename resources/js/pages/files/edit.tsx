import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, Download, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { CommentThread } from '@/components/comments/comment-thread';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FilePreviewDialog } from '@/components/file-preview-dialog';
import { FileVersionField, type ChainEntry, type VersionLink } from '@/components/files/file-version-field';
import { InheritedSharingNotice, type SharingRoot } from '@/components/files/inherited-sharing-notice';
import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { SaveButton, SavedIndicator } from '@/components/save-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { categoryColor } from '@/lib/category-colors';
import { formatBytes } from '@/lib/format-bytes';
import { isThumbnailable } from '@/lib/thumbnails';
import { slugify } from '@/lib/utils';

interface ShareLink {
    id: number;
    url: string;
    expires_at: string | null;
    max_downloads: number | null;
    downloads_count: number;
    revoke_url: string;
}

interface Named {
    id: number;
    name: string;
}

interface CategoryTag {
    id: number;
    name: string;
    color: string;
}

type Tab = 'general' | 'sharing' | 'links' | 'versions' | 'comments';

interface FilesEditProps {
    file: {
        id: number;
        name: string;
        description: string | null;
        original_name: string;
        size: number;
        mime_type: string;
        uploader: string | null;
        folder_id: number | null;
        public: boolean;
        commentable: boolean;
        slug: string;
        expires_at: string | null;
        expired: boolean;
        download_limit: number | null;
        download_limit_scope: string | null;
        downloads_used: number;
        public_url: string | null;
        public_via_folder: string | null;
        created_at: string | null;
        is_revision: boolean;
        version: { previous: VersionLink | null; next: VersionLink | null };
    };
    sharing_root: SharingRoot | null;
    can_update_root: boolean;
    can_set_version: boolean;
    version_chain: ChainEntry[];
    folder_options: Named[];
    categories: CategoryTag[];
    assigned_category_ids: number[];
    can_set_categories: boolean;
    can_update: boolean;
    can_delete: boolean;
    can_manage_public: boolean;
    can_set_commentable: boolean;
    comments_enabled: boolean;
    assigned_clients: Named[];
    assigned_groups: Named[];
    available_clients: Named[];
    available_groups: Named[];
    share_links: ShareLink[];
    share_link_store_url: string;
    can_set_expiration: boolean;
    can_limit_downloads: boolean;
}

export default function FilesEdit({
    file,
    sharing_root,
    can_update_root,
    can_set_version,
    version_chain,
    folder_options,
    categories,
    assigned_category_ids,
    can_set_categories,
    can_update,
    can_delete,
    can_manage_public,
    can_set_commentable,
    comments_enabled,
    assigned_clients,
    assigned_groups,
    available_clients,
    available_groups,
    share_links,
    share_link_store_url,
    can_set_expiration,
    can_limit_downloads,
}: FilesEditProps) {
    const { t } = useTranslation();
    const { date } = useFormatDate();
    const pageErrors = usePage().props.errors as Record<string, string>;
    // A comment notification and the activity log both land here, so honour
    // ?tab=comments. Somebody who may read the file but not edit it gets the
    // conversation and nothing else, which is the only tab they have.
    const [tab, setTab] = useState<Tab>(() => {
        const requested = new URLSearchParams(window.location.search).get('tab');

        if (requested === 'comments' && comments_enabled) return 'comments';

        return can_update ? 'general' : 'comments';
    });
    const [target, setTarget] = useState('');
    const [shareExpiresAt, setShareExpiresAt] = useState('');
    const [shareMaxDownloads, setShareMaxDownloads] = useState('');
    const [useCustomToken, setUseCustomToken] = useState(false);
    const [customToken, setCustomToken] = useState('');
    const [linkDialogOpen, setLinkDialogOpen] = useState(false);
    const [copiedLinkId, setCopiedLinkId] = useState<number | null>(null);
    const [copiedFileLink, setCopiedFileLink] = useState(false);
    // Auto-fill the slug from the name until the user edits it directly
    // (an already-populated slug counts as touched so we never clobber a
    // deliberate value).
    const [slugTouched, setSlugTouched] = useState(file.slug !== '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('All files'), href: '/files' },
        { title: file.name, href: `/files/${file.id}` },
    ];

    const deleteForm = useForm({});

    const form = useForm({
        name: file.name,
        description: file.description ?? '',
        folder_id: file.folder_id === null ? 'root' : String(file.folder_id),
        public: file.public,
        commentable: file.commentable,
        slug: file.slug,
        expires_at: file.expires_at ?? '',
        download_limit: file.download_limit === null ? '' : String(file.download_limit),
        download_limit_scope: file.download_limit_scope ?? 'total',
        categories: assigned_category_ids,
    });
    const { data, setData, processing, errors, recentlySuccessful } = form;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // 'root' means no folder — send null. Same for an empty expiry.
        form.transform((payload) => ({
            ...payload,
            folder_id: payload.folder_id === 'root' ? null : payload.folder_id,
            expires_at: payload.expires_at || null,
            // An empty box means no limit, the same way an empty date
            // means no expiry.
            download_limit: payload.download_limit === '' ? null : Number(payload.download_limit),
        }));
        form.patch(route('files.update', file.id));
    };

    const assign = () => {
        if (target === '') return;
        const [type, id] = target.split(':');
        router.post(route('files.assignments.store', file.id), { type, id: Number(id) }, { preserveScroll: true, onSuccess: () => setTarget('') });
    };

    const removeAssignment = (type: 'client' | 'group', named: Named) => {
        router.delete(route('files.assignments.destroy', file.id), {
            data: { type, id: named.id },
            preserveScroll: true,
        });
    };

    const createShareLink = () => {
        router.post(
            share_link_store_url,
            {
                expires_at: shareExpiresAt || null,
                max_downloads: shareMaxDownloads === '' ? null : Number(shareMaxDownloads),
                token: useCustomToken ? customToken : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShareExpiresAt('');
                    setShareMaxDownloads('');
                    setUseCustomToken(false);
                    setCustomToken('');
                    setLinkDialogOpen(false);
                },
            },
        );
    };

    const revokeShareLink = (revokeUrl: string) => {
        router.delete(revokeUrl, { preserveScroll: true });
    };

    const copyShareLink = (id: number, url: string) => {
        void navigator.clipboard.writeText(url);
        setCopiedLinkId(id);
        window.setTimeout(() => setCopiedLinkId((current) => (current === id ? null : current)), 1500);
    };

    const copyFileLink = (url: string) => {
        void navigator.clipboard.writeText(url);
        setCopiedFileLink(true);
        window.setTimeout(() => setCopiedFileLink(false), 1500);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={file.name} />

            <div className="px-4 py-6">
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-4">
                        {isThumbnailable(file.mime_type) && (
                            <FilePreviewDialog fileId={file.id} fileName={file.original_name} className="shrink-0">
                                <img src={route('files.thumbnail', file.id)} alt="" className="size-16 rounded border object-cover" />
                            </FilePreviewDialog>
                        )}
                        <Heading
                            title={file.name}
                            description={`${file.original_name} · ${formatBytes(file.size)} · ${file.mime_type}`}
                            badge={
                                file.expired && (
                                    <Badge variant="destructive" className="text-[11px] font-normal">
                                        {t('Expired')}
                                    </Badge>
                                )
                            }
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a href={route('files.download', file.id)}>
                                <Download className="size-4" />
                                {t('Download')}
                            </a>
                        </Button>
                        {can_delete && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive" disabled={deleteForm.processing}>
                                        {t('Delete file')}
                                    </Button>
                                }
                                title={t('Delete file?')}
                                description={t('The file ":name" will no longer be available to anyone it was shared with.', { name: file.name })}
                                confirmLabel={t('Delete file')}
                                onConfirm={() => deleteForm.delete(route('files.destroy', file.id))}
                            />
                        )}
                    </div>
                </div>

                {(can_update || comments_enabled) && (
                    <nav className="mt-6 flex gap-1 border-b">
                        {[
                            ...(can_update ? (['general', 'sharing', 'links'] as Tab[]) : []),
                            ...(can_set_version ? (['versions'] as Tab[]) : []),
                            ...(comments_enabled ? (['comments'] as Tab[]) : []),
                        ]
                            .filter((tabKey) => tabKey !== 'links' || can_manage_public)
                            .map((tabKey) => (
                                <button
                                    key={tabKey}
                                    onClick={() => setTab(tabKey)}
                                    className={`border-b-2 px-3 py-2 text-sm ${tab === tabKey ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                                >
                                    {tabKey === 'general'
                                        ? t('General')
                                        : tabKey === 'sharing'
                                          ? t('Sharing')
                                          : tabKey === 'links'
                                            ? t('Public')
                                            : tabKey === 'versions'
                                              ? t('Versions')
                                              : t('Comments')}
                                </button>
                            ))}
                    </nav>
                )}

                <div className="mt-6">
                    {tab === 'comments' ? (
                        // The same thread the library's slide-over renders —
                        // one component, so the two can never disagree about
                        // what a comment looks like or who may write one.
                        <div className="max-w-2xl">
                            <CommentThread fileId={file.id} />
                        </div>
                    ) : !can_update ? (
                        <p className="text-muted-foreground text-sm">{file.description}</p>
                    ) : tab === 'general' ? (
                        <form onSubmit={submit} className="grid max-w-md gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Title')}</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    onBlur={() => {
                                        if (!slugTouched) setData('slug', slugify(data.name));
                                    }}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">{t('Description')}</Label>
                                <Input id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="folder_id">{t('Folder')}</Label>
                                <Select value={data.folder_id} onValueChange={(value) => setData('folder_id', value)}>
                                    <SelectTrigger id="folder_id" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="root">{t('No folder')}</SelectItem>
                                        {folder_options.map((folder) => (
                                            <SelectItem key={folder.id} value={String(folder.id)}>
                                                {folder.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.folder_id} />
                            </div>

                            {can_set_commentable && (
                                <div className="grid gap-2">
                                    <div className="flex items-start gap-2">
                                        <Checkbox
                                            id="commentable"
                                            checked={data.commentable}
                                            onCheckedChange={(checked) => setData('commentable', checked === true)}
                                        />
                                        <Label htmlFor="commentable" className="font-normal">
                                            {t('Allow comments on this file')}
                                        </Label>
                                    </div>
                                    <InputError message={errors.commentable} />
                                </div>
                            )}

                            {can_set_expiration && (
                                <div className="grid gap-2">
                                    <Label htmlFor="expires_at">{t('Expires')}</Label>
                                    <Input
                                        id="expires_at"
                                        type="date"
                                        value={data.expires_at ?? ''}
                                        onChange={(e) => setData('expires_at', e.target.value)}
                                    />
                                    <InputError message={errors.expires_at} />
                                    <p className="text-muted-foreground text-xs">{t('Leave empty for a file that never expires.')}</p>
                                </div>
                            )}

                            {can_limit_downloads && (
                                <div className="grid gap-2">
                                    <Label htmlFor="download_limit">{t('Download limit')}</Label>
                                    <Input
                                        id="download_limit"
                                        type="number"
                                        min={1}
                                        value={data.download_limit}
                                        onChange={(e) => setData('download_limit', e.target.value)}
                                    />
                                    <InputError message={errors.download_limit} />

                                    {data.download_limit !== '' && (
                                        <Select value={data.download_limit_scope} onValueChange={(value) => setData('download_limit_scope', value)}>
                                            <SelectTrigger id="download_limit_scope">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="total">{t('In total, across everyone')}</SelectItem>
                                                <SelectItem value="per_user">{t('Each person separately')}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    )}

                                    <p className="text-muted-foreground text-xs">
                                        {data.download_limit === ''
                                            ? t('Leave empty for a file that can be downloaded any number of times.')
                                            : t('Downloaded :count time(s) so far. Your own downloads never count.', {
                                                  count: file.downloads_used,
                                              })}
                                    </p>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label>{t('Categories')}</Label>
                                {can_set_categories ? (
                                    <>
                                        <Select value="" onValueChange={(value) => setData('categories', [...data.categories, Number(value)])}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder={t('Add a category')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {categories.filter((c) => !data.categories.includes(c.id)).length === 0 ? (
                                                    <div className="text-muted-foreground px-2 py-1.5 text-sm">{t('No more categories')}</div>
                                                ) : (
                                                    categories
                                                        .filter((c) => !data.categories.includes(c.id))
                                                        .map((category) => (
                                                            <SelectItem key={category.id} value={String(category.id)}>
                                                                <span className="flex items-center gap-2">
                                                                    <span
                                                                        className={`size-2 shrink-0 rounded-full ${categoryColor(category.color).swatch}`}
                                                                    />
                                                                    {category.name}
                                                                </span>
                                                            </SelectItem>
                                                        ))
                                                )}
                                            </SelectContent>
                                        </Select>
                                        {data.categories.length > 0 && (
                                            <div className="flex flex-wrap gap-2">
                                                {data.categories.map((id) => {
                                                    const category = categories.find((c) => c.id === id);
                                                    return (
                                                        <span
                                                            key={id}
                                                            className={`inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm ${category ? categoryColor(category.color).badge : 'bg-muted'}`}
                                                        >
                                                            {category?.name ?? id}
                                                            <button
                                                                type="button"
                                                                className="opacity-70 hover:opacity-100"
                                                                onClick={() =>
                                                                    setData(
                                                                        'categories',
                                                                        data.categories.filter((x) => x !== id),
                                                                    )
                                                                }
                                                            >
                                                                <X className="size-3.5" />
                                                                <span className="sr-only">{t('Remove')}</span>
                                                            </button>
                                                        </span>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </>
                                ) : data.categories.length > 0 ? (
                                    <div className="flex flex-wrap gap-2">
                                        {data.categories.map((id) => {
                                            const category = categories.find((c) => c.id === id);
                                            return (
                                                <Badge key={id} variant="outline" className={category ? categoryColor(category.color).badge : ''}>
                                                    {category?.name ?? id}
                                                </Badge>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground text-sm">{t('None')}</p>
                                )}
                            </div>

                            <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                        </form>
                    ) : tab === 'versions' ? (
                        <FileVersionField
                            version={file.version}
                            chain={version_chain}
                            sharingRoot={sharing_root}
                            canUpdateRoot={can_update_root}
                            candidatesUrl={route('files.version.candidates', file.id)}
                            previewUrl={route('files.version.preview', file.id)}
                            storeUrl={route('files.version.store', file.id)}
                            destroyUrl={route('files.version.destroy', file.id)}
                            error={pageErrors.previous_file_id}
                        />
                    ) : tab === 'sharing' ? (
                        <div className="max-w-xl space-y-4">
                            <HeadingSmall title={t('Shared with')} description={t('Clients and groups that can download this file')} />

                            {/* A revision holds no recipients of its own — it is
                                shared with exactly the people the original is.
                                The assigned lists below stay, read-only: hiding
                                who has the file would make this tab useless
                                rather than simpler. */}
                            {sharing_root && <InheritedSharingNotice root={sharing_root} canUpdateRoot={can_update_root} />}

                            {!file.is_revision && (
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
                                    <Button type="button" onClick={assign} disabled={target === ''}>
                                        {t('Assign')}
                                    </Button>
                                </div>
                            )}

                            <div className="rounded-lg border">
                                {assigned_clients.length === 0 && assigned_groups.length === 0 && (
                                    <p className="text-muted-foreground px-4 py-6 text-center text-sm">{t('Not shared with anyone yet.')}</p>
                                )}
                                {assigned_clients.map((client) => (
                                    <div key={`client-${client.id}`} className="flex items-center justify-between border-b px-4 py-2 last:border-0">
                                        <p className="text-sm font-medium">{client.name}</p>
                                        {!file.is_revision && (
                                            <Button variant="ghost" size="sm" onClick={() => removeAssignment('client', client)}>
                                                <X className="size-4" />
                                                <span className="sr-only">{t('Remove')}</span>
                                            </Button>
                                        )}
                                    </div>
                                ))}
                                {assigned_groups.map((group) => (
                                    <div key={`group-${group.id}`} className="flex items-center justify-between border-b px-4 py-2 last:border-0">
                                        <p className="text-sm font-medium">
                                            {group.name} <Badge variant="secondary">{t('Group')}</Badge>
                                        </p>
                                        {!file.is_revision && (
                                            <Button variant="ghost" size="sm" onClick={() => removeAssignment('group', group)}>
                                                <X className="size-4" />
                                                <span className="sr-only">{t('Remove')}</span>
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="max-w-xl space-y-8">
                            <form onSubmit={submit} className="grid gap-4">
                                <HeadingSmall title={t('Public group listing')} />

                                <div className="grid gap-2">
                                    <div className="flex items-start gap-2">
                                        <Checkbox
                                            id="public"
                                            checked={file.public_via_folder !== null || data.public}
                                            disabled={file.public_via_folder !== null}
                                            onCheckedChange={(checked) => setData('public', checked === true)}
                                        />
                                        <Label
                                            htmlFor="public"
                                            className={file.public_via_folder !== null ? 'text-muted-foreground font-normal' : 'font-normal'}
                                        >
                                            {t("Include this file when a group it's shared with is public.")}
                                        </Label>
                                    </div>
                                    <InputError message={errors.public} />

                                    {file.public_via_folder !== null && (
                                        <p className="text-sm font-medium text-amber-600 dark:text-amber-500">
                                            {t('This file is public because it\'s inside the public folder ":name".', {
                                                name: file.public_via_folder,
                                            })}
                                        </p>
                                    )}

                                    {(file.public_via_folder !== null || data.public) && (
                                        <div className="mt-2 grid gap-2 pl-6">
                                            <Label htmlFor="slug">{t('URL slug')}</Label>
                                            <Input
                                                id="slug"
                                                value={data.slug}
                                                onChange={(e) => {
                                                    setSlugTouched(true);
                                                    setData('slug', e.target.value);
                                                }}
                                                required
                                            />
                                            <p className="text-muted-foreground text-sm">
                                                {t('Used in the public file URL when this file is public.')}
                                            </p>
                                            <InputError message={errors.slug} />
                                        </div>
                                    )}
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button type="submit" disabled={processing}>
                                        {t('Save')}
                                    </Button>

                                    {file.public_url && (
                                        <Button type="button" variant="outline" onClick={() => copyFileLink(file.public_url!)}>
                                            {copiedFileLink ? <Check className="size-4" /> : <Copy className="size-4" />}
                                            {t('Copy link')}
                                        </Button>
                                    )}

                                    <SavedIndicator recentlySuccessful={recentlySuccessful} />
                                </div>
                            </form>

                            <div className="flex items-start justify-between gap-4">
                                <HeadingSmall
                                    title={t('Special public links')}
                                    description={t('Custom links with an optional expiration date or download limit, no account needed.')}
                                />

                                <Dialog open={linkDialogOpen} onOpenChange={setLinkDialogOpen}>
                                    <DialogTrigger asChild>
                                        <Button type="button" className="shrink-0">
                                            {t('Create link')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>{t('Create a public link')}</DialogTitle>
                                        </DialogHeader>

                                        <div className="grid gap-4">
                                            {can_set_expiration && (
                                                <div className="grid gap-1">
                                                    <Label htmlFor="share-expires">{t('Expires')}</Label>
                                                    <Input
                                                        id="share-expires"
                                                        type="date"
                                                        value={shareExpiresAt}
                                                        onChange={(e) => setShareExpiresAt(e.target.value)}
                                                    />
                                                    <p className="text-muted-foreground text-xs">{t('Leave empty for a link that never expires.')}</p>
                                                </div>
                                            )}
                                            {can_limit_downloads && (
                                                <div className="grid gap-1">
                                                    <Label htmlFor="share-max-downloads">{t('Max downloads')}</Label>
                                                    <Input
                                                        id="share-max-downloads"
                                                        type="number"
                                                        min={1}
                                                        placeholder={t('Unlimited')}
                                                        value={shareMaxDownloads}
                                                        onChange={(e) => setShareMaxDownloads(e.target.value)}
                                                    />
                                                    <p className="text-muted-foreground text-xs">{t('Leave empty for unlimited downloads.')}</p>
                                                </div>
                                            )}

                                            <div className="grid gap-2">
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="use-custom-token"
                                                        checked={useCustomToken}
                                                        onCheckedChange={(checked) => setUseCustomToken(checked === true)}
                                                    />
                                                    <Label htmlFor="use-custom-token" className="font-normal">
                                                        {t('Use a custom link instead of a random one')}
                                                    </Label>
                                                </div>
                                                {useCustomToken && (
                                                    <div className="grid gap-1">
                                                        <Input
                                                            id="share-token"
                                                            value={customToken}
                                                            onChange={(e) => setCustomToken(e.target.value)}
                                                        />
                                                        <InputError message={pageErrors.token} />
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        <DialogFooter>
                                            <Button type="button" onClick={createShareLink}>
                                                {t('Create link')}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>

                            <div className="rounded-lg border">
                                {share_links.length === 0 && (
                                    <p className="text-muted-foreground px-4 py-6 text-center text-sm">{t('No public links yet.')}</p>
                                )}
                                {share_links.map((link) => (
                                    <div key={link.id} className="flex items-center justify-between gap-2 border-b px-4 py-2 last:border-0">
                                        <div className="min-w-0">
                                            <p className="truncate font-mono text-xs">{link.url}</p>
                                            <p className="text-muted-foreground mt-0.5 text-xs">
                                                {link.expires_at ? t('Expires :date', { date: date(link.expires_at) }) : t('Never expires')}
                                                {' · '}
                                                {link.max_downloads !== null
                                                    ? t(':used / :limit downloads', { used: link.downloads_count, limit: link.max_downloads })
                                                    : t(':count downloads', { count: link.downloads_count })}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 gap-1">
                                            <Button variant="ghost" size="icon" className="size-8" onClick={() => copyShareLink(link.id, link.url)}>
                                                {copiedLinkId === link.id ? <Check className="size-4" /> : <Copy className="size-4" />}
                                                <span className="sr-only">{t('Copy link')}</span>
                                            </Button>
                                            <ConfirmDialog
                                                trigger={
                                                    <Button variant="ghost" size="sm">
                                                        {t('Revoke')}
                                                    </Button>
                                                }
                                                title={t('Revoke this public link?')}
                                                description={t('Anyone using it will no longer be able to download the file.')}
                                                confirmLabel={t('Revoke link')}
                                                onConfirm={() => revokeShareLink(link.revoke_url)}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
