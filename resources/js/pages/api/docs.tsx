import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface Endpoint {
    method: string;
    path: string;
    summary: string;
    abilities: string[];
}

type Tab = 'guide' | 'zapier' | 'endpoints';

interface Props {
    guide_html: string;
    zapier_html: string;
    endpoints: Endpoint[];
    spec_url: string;
    version: string | null;
}

const METHOD_TONE: Record<string, string> = {
    GET: 'text-emerald-700 dark:text-emerald-400',
    POST: 'text-violet-700 dark:text-violet-400',
    PATCH: 'text-amber-700 dark:text-amber-400',
    PUT: 'text-amber-700 dark:text-amber-400',
    DELETE: 'text-red-700 dark:text-red-400',
};

export default function ApiDocs({ guide_html, zapier_html, endpoints, spec_url, version }: Props) {
    const { t } = useTranslation();
    const [tab, setTab] = useState<Tab>('guide');

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('API'), href: '/api/docs' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('API')} />

            <div className="px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={t('API')} description={t('How external tools can act on this installation on your behalf.')} />
                    <div className="flex items-center gap-2">
                        {version && <Badge variant="secondary">v{version}</Badge>}
                        <Button variant="outline" size="sm" asChild>
                            <a href={spec_url} target="_blank" rel="noreferrer">
                                {t('OpenAPI specification')}
                            </a>
                        </Button>
                        <Button size="sm" asChild>
                            <Link href={route('api-tokens.index')}>{t('API tokens')}</Link>
                        </Button>
                    </div>
                </div>

                {/*
                 * Three views of the same API, one at a time. The endpoint
                 * table is generated from the committed OpenAPI document;
                 * the other two are markdown files that ship with the
                 * application, converted server-side with HTML input
                 * escaped — see ApiDocsController.
                 *
                 * The guide and the Zapier page are separate because they
                 * are for different readers: one is writing code against
                 * the API, the other is filling in a form in Zapier and
                 * will read nothing else.
                 */}
                <div className="mt-6 mb-6 flex gap-1 border-b">
                    {(['guide', 'zapier', 'endpoints'] as const).map((key) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setTab(key)}
                            className={`border-b-2 px-3 py-2 text-sm ${tab === key ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                        >
                            {key === 'guide' ? t('Guide') : key === 'zapier' ? t('Zapier') : t('Endpoints')}
                        </button>
                    ))}
                </div>

                {tab === 'endpoints' && (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr className="text-left">
                                    <th className="px-4 py-2 font-medium">{t('Method')}</th>
                                    <th className="px-4 py-2 font-medium">{t('Path')}</th>
                                    <th className="px-4 py-2 font-medium">{t('Description')}</th>
                                    <th className="px-4 py-2 font-medium">{t('Permissions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {endpoints.map((endpoint) => (
                                    <tr key={`${endpoint.method} ${endpoint.path}`} className="border-t">
                                        <td className={`px-4 py-2 font-mono text-xs font-semibold ${METHOD_TONE[endpoint.method] ?? ''}`}>
                                            {endpoint.method}
                                        </td>
                                        <td className="px-4 py-2 font-mono text-xs whitespace-nowrap">{endpoint.path}</td>
                                        <td className="text-muted-foreground px-4 py-2">{endpoint.summary}</td>
                                        <td className="px-4 py-2">
                                            <span className="text-muted-foreground font-mono text-xs">{endpoint.abilities.join(', ') || '—'}</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {tab !== 'endpoints' && (
                    <section className="api-guide max-w-3xl" dangerouslySetInnerHTML={{ __html: tab === 'guide' ? guide_html : zapier_html }} />
                )}
            </div>
        </AppLayout>
    );
}
