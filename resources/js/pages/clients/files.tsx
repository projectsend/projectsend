import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Download, File as FileIcon } from 'lucide-react';

import Heading from '@/components/heading';
import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ALL, useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { categoryColor } from '@/lib/category-colors';
import { formatBytes } from '@/lib/format-bytes';

interface CategoryTag {
    id: number;
    name: string;
    color: string;
}

interface FileRow {
    id: number;
    name: string;
    original_name: string;
    mime_type: string;
    size: number;
    created_at: string | null;
    uploaded_by_client: boolean;
    uploader: string | null;
    downloads_count: number;
    can_download: boolean;
    categories: CategoryTag[];
}

interface ClientFilesProps {
    client: { id: number; name: string; email: string };
    files: FileRow[];
    pagination: PaginationMeta;
    search: string;
    owner: string | null;
}

export default function ClientFiles({ client, files, pagination, search, owner }: ClientFilesProps) {
    const { t } = useTranslation();

    const { values, set, reset, hasFilters } = useListQuery(
        'clients.files',
        { search, owner: owner ?? ALL },
        { search: '', owner: ALL },
        { client: client.id },
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Clients'), href: route('clients.index') },
        { title: client.name, href: route('clients.edit', client.id) },
        { title: t('Files'), href: route('clients.files', client.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t(':name — files', { name: client.name })} />

            <div className="px-4 py-6">
                <Heading
                    title={t('Files accessible to :name', { name: client.name })}
                    description={t('Uploaded by the client, or assigned to them directly, via a group, or via a shared folder.')}
                />

                <ListToolbar showClear={hasFilters} onClear={reset}>
                    <FilterField label={t('Search')} htmlFor="client-files-search">
                        <Input
                            id="client-files-search"
                            type="search"
                            placeholder={t('File name')}
                            className="w-64"
                            value={values.search}
                            onChange={(e) => set('search', e.target.value, true)}
                        />
                    </FilterField>
                    <FilterField label={t('Access')} htmlFor="client-files-owner">
                        <Select value={values.owner} onValueChange={(v) => set('owner', v)}>
                            <SelectTrigger id="client-files-owner" className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('Uploaded and shared')}</SelectItem>
                                <SelectItem value="uploaded">{t('Uploaded by client')}</SelectItem>
                                <SelectItem value="shared">{t('Shared with client')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FilterField>
                </ListToolbar>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <tbody>
                            {files.length === 0 && (
                                <tr>
                                    <td className="text-muted-foreground px-4 py-10 text-center">
                                        {hasFilters ? t('No files match these filters.') : t('This client has no accessible files yet.')}
                                    </td>
                                </tr>
                            )}
                            {files.map((file) => (
                                <tr key={file.id} className="border-b last:border-0">
                                    <td className="px-4 py-2.5">
                                        <div className="flex items-start gap-2">
                                            <FileIcon className="text-muted-foreground mt-0.5 size-8 shrink-0" strokeWidth={1.25} />
                                            <div className="min-w-0">
                                                <p className="font-medium">{file.name}</p>
                                                <p className="text-muted-foreground text-xs">{file.original_name}</p>
                                                {file.categories.length > 0 && (
                                                    <div className="mt-1 flex flex-wrap gap-1">
                                                        {file.categories.map((category) => (
                                                            <Badge
                                                                key={category.id}
                                                                variant="outline"
                                                                className={`text-[11px] font-normal ${categoryColor(category.color).badge}`}
                                                            >
                                                                {category.name}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <Badge variant={file.uploaded_by_client ? 'secondary' : 'outline'}>
                                            {file.uploaded_by_client ? t('Uploaded by client') : t('Shared with client')}
                                        </Badge>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-2.5 text-sm whitespace-nowrap">{formatBytes(file.size)}</td>
                                    <td className="text-muted-foreground px-4 py-2.5 text-sm whitespace-nowrap">
                                        {t(':count downloads', { count: file.downloads_count })}
                                    </td>
                                    <td className="px-4 py-2.5 text-right">
                                        {file.can_download && (
                                            <Button variant="ghost" size="sm" asChild>
                                                <a href={route('files.download', file.id)}>
                                                    <Download className="size-4" />
                                                    <span className="sr-only">{t('Download')}</span>
                                                </a>
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination meta={pagination} />
            </div>
        </AppLayout>
    );
}
