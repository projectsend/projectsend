import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Globe } from 'lucide-react';
import { type FormEventHandler } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import PortalLayout from '@/layouts/portal-layout';
import { categoryColor } from '@/lib/category-colors';
import { type BreadcrumbItem } from '@/types';
import { type CategoryTag } from '@/types/portal';

interface FolderOption {
    id: number;
    name: string;
    /** Files in here are readable by anyone, with or without the public switch. */
    public: boolean;
}

interface PortalEditFileProps {
    theme: string;
    file: {
        id: number;
        name: string;
        description: string | null;
        original_name: string;
        size: number;
        public: boolean;
        commentable: boolean;
        expires_at: string | null;
        download_limit: number | null;
        download_limit_scope: string;
        folder_id: number | null;
        categories: number[];
    };
    can_delete: boolean;
    can_publish: boolean;
    can_set_expiration: boolean;
    can_set_categories: boolean;
    can_limit_downloads: boolean;
    can_set_commentable: boolean;
    categories: CategoryTag[];
    folders: FolderOption[];
    public_listing_slug: string | null;
}

/**
 * The client's editor for a file they uploaded.
 *
 * One page for every theme, like portal/upload.tsx and for the same
 * reason — a form rebuilt per theme is four places for a field to go
 * missing. Only the shell differs, and the `theme` prop picks it.
 *
 * Every field here is behind the same permission the server will check
 * when this posts. Hiding a control the client cannot use is a courtesy,
 * not the enforcement: ApplyFileEdits leaves an ungranted field exactly as
 * it was regardless of what arrives.
 */
export default function PortalEditFile({
    theme,
    file,
    can_delete,
    can_publish,
    can_set_expiration,
    can_set_categories,
    can_limit_downloads,
    can_set_commentable,
    categories,
    folders,
    public_listing_slug,
}: PortalEditFileProps) {
    const { t } = useTranslation();

    const form = useForm({
        name: file.name,
        description: file.description ?? '',
        folder_id: file.folder_id === null ? 'root' : String(file.folder_id),
        public: file.public,
        commentable: file.commentable,
        expires_at: file.expires_at ?? '',
        download_limit: file.download_limit === null ? '' : String(file.download_limit),
        download_limit_scope: file.download_limit_scope,
        categories: file.categories,
    });
    const { data, setData, processing, errors, recentlySuccessful } = form;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // 'root' means no folder, and an empty box means no limit — the
        // same transforms the staff editor makes, because the server reads
        // one payload shape from both.
        form.transform((payload) => ({
            ...payload,
            folder_id: payload.folder_id === 'root' ? null : payload.folder_id,
            expires_at: payload.expires_at || null,
            download_limit: payload.download_limit === '' ? null : Number(payload.download_limit),
        }));

        form.patch(route('my-files.update', file.id), { preserveScroll: true });
    };

    const unassigned = categories.filter((category) => !data.categories.includes(category.id));
    const selectedFolder = folders.find((folder) => String(folder.id) === data.folder_id) ?? null;

    const content = (
        <>
            <Head title={t('Edit :name', { name: file.name })} />

            <div className="px-4 py-6">
                <Heading title={t('Edit file')} description={file.original_name} />

                <Button variant="ghost" size="sm" asChild className="mb-4 -ml-2">
                    <Link href={route('my-files.index')}>
                        <ArrowLeft className="size-4" />
                        {t('Back to my files')}
                    </Link>
                </Button>

                <form onSubmit={submit} className="grid max-w-2xl gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Name')}</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">{t('Description')}</Label>
                        <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} rows={3} />
                        <InputError message={errors.description} />
                    </div>

                    {folders.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="folder_id">{t('Folder')}</Label>
                            <Select value={data.folder_id} onValueChange={(value) => setData('folder_id', value)}>
                                <SelectTrigger id="folder_id">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="root">{t('No folder')}</SelectItem>
                                    {folders.map((folder) => (
                                        <SelectItem key={folder.id} value={String(folder.id)}>
                                            <span className="flex items-center gap-1.5">
                                                {folder.name}
                                                {folder.public && <Globe className="text-muted-foreground size-3.5 shrink-0" />}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.folder_id} />
                            {/* Moving into a public folder publishes the file
                                whether or not the public switch below is even
                                offered — so the warning belongs here, next to
                                the choice, not only next to that switch. */}
                            {selectedFolder?.public && (
                                <p className="text-muted-foreground text-xs">
                                    {t('":name" is a public folder — anyone will be able to open this file, without signing in.', {
                                        name: selectedFolder.name,
                                    })}
                                </p>
                            )}
                        </div>
                    )}

                    {can_set_expiration && (
                        <div className="grid gap-2">
                            <Label htmlFor="expires_at">{t('Expires on')}</Label>
                            <Input id="expires_at" type="date" value={data.expires_at} onChange={(e) => setData('expires_at', e.target.value)} />
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
                                {t('Leave empty for a file that can be downloaded any number of times.')}
                            </p>
                        </div>
                    )}

                    {can_set_categories && (
                        <div className="grid gap-2">
                            <Label>{t('Categories')}</Label>

                            {data.categories.length > 0 && (
                                <div className="flex flex-wrap gap-1">
                                    {data.categories.map((id) => {
                                        const category = categories.find((c) => c.id === id);

                                        return (
                                            <button
                                                key={id}
                                                type="button"
                                                className={`inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm ${category ? categoryColor(category.color).badge : 'bg-muted'}`}
                                                onClick={() =>
                                                    setData(
                                                        'categories',
                                                        data.categories.filter((current) => current !== id),
                                                    )
                                                }
                                            >
                                                {category?.name ?? id}
                                                <span aria-hidden>×</span>
                                                <span className="sr-only">{t('Remove')}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}

                            {unassigned.length > 0 && (
                                <Select value="" onValueChange={(value) => setData('categories', [...data.categories, Number(value)])}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Add a category')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {unassigned.map((category) => (
                                            <SelectItem key={category.id} value={String(category.id)}>
                                                <span className="flex items-center gap-2">
                                                    <span className={`size-2 shrink-0 rounded-full ${categoryColor(category.color).swatch}`} />
                                                    {category.name}
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </div>
                    )}

                    {can_set_commentable && (
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="commentable"
                                checked={data.commentable}
                                onCheckedChange={(checked) => setData('commentable', checked === true)}
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="commentable">{t('Allow comments on this file')}</Label>
                            </div>
                        </div>
                    )}

                    {can_publish && (
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="public"
                                checked={data.public}
                                onCheckedChange={(checked) => setData('public', checked === true)}
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="public" className="flex items-center gap-1.5">
                                    <Globe className="size-4" />
                                    {t('Make this file public')}
                                </Label>
                                {/* Said plainly, because it is the one switch
                                    here that reaches past the people this
                                    file was shared with. */}
                                <p className="text-muted-foreground text-xs">
                                    {public_listing_slug
                                        ? t('Anyone with the link will be able to open and download it, without signing in.')
                                        : t('This site has no public page set up yet, so nothing will be visible until an administrator sets one.')}
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {t('Save')}
                        </Button>

                        {recentlySuccessful && <p className="text-muted-foreground text-sm">{t('Saved.')}</p>}

                        {can_delete && (
                            <ConfirmDialog
                                trigger={
                                    <Button type="button" variant="ghost" className="text-destructive hover:text-destructive ml-auto">
                                        {t('Delete')}
                                    </Button>
                                }
                                title={t('Delete file?')}
                                description={t('":name" will be deleted, along with everyone\'s access to it. This can be undone by an administrator.', {
                                    name: file.name,
                                })}
                                confirmLabel={t('Delete file')}
                                onConfirm={() => router.delete(route('my-files.destroy', file.id))}
                            />
                        )}
                    </div>
                </form>
            </div>
        </>
    );

    // Same shell dispatch as portal/upload.tsx: the "default" theme uses
    // the app's own sidebar, every other portal theme uses PortalLayout.
    if (theme === 'default') {
        const breadcrumbs: BreadcrumbItem[] = [
            { title: t('My files'), href: '/my-files' },
            { title: file.name, href: route('my-files.edit', file.id) },
        ];

        return <AppLayout breadcrumbs={breadcrumbs}>{content}</AppLayout>;
    }

    return <PortalLayout>{content}</PortalLayout>;
}
