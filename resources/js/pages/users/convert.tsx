import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { FilterField, ListToolbar } from '@/components/list-toolbar';
import { Pagination, PaginationMeta } from '@/components/pagination';
import { TableShell } from '@/components/table-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useListQuery } from '@/hooks/use-list-query';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

type Direction = 'to_client' | 'to_staff';

interface Consequences {
    api_tokens: number;
    assigned_clients: number;
    managed_by: number;
    group_memberships: number;
    files_shared_with_them: number;
    content: { files: number; folders: number };
}

interface AccountRow {
    id: number;
    name: string;
    email: string;
    role: string | null;
    active: boolean;
    account_requested: boolean;
    is_self: boolean;
    is_last_administrator: boolean;
    requires_new_password: boolean;
    auth_source: string;
    consequences: Consequences;
}

interface RoleOption {
    id: number;
    name: string;
    is_system: boolean;
    client_scoped: boolean;
}

interface ConvertProps {
    direction: Direction;
    filters: { search: string | null };
    accounts: AccountRow[];
    pagination: PaginationMeta;
    roles: RoleOption[];
    clients: { id: number; name: string }[];
}

/**
 * Singular/plural chosen here rather than left as ":count items" with a 1
 * in it. Elsewhere the app tolerates that (":count downloads" in a row
 * subtitle), but this screen's numbers are the entire safety story of a
 * destructive action and get read closely.
 */
function plural(t: (key: string, replacements?: Record<string, string | number>) => string, count: number, one: string, many: string) {
    return count === 1 ? t(one) : t(many, { count });
}

export default function ConvertAccounts({ direction, filters, accounts, pagination, roles, clients }: ConvertProps) {
    const { t } = useTranslation();
    const [target, setTarget] = useState<AccountRow | null>(null);

    const { values, set } = useListQuery(
        'users.convert',
        { direction, search: filters.search ?? '' },
        { direction: 'to_client', search: '' },
    );

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Convert accounts'), href: '/users/convert' }];

    const toClient = direction === 'to_client';

    // Mirrors the server's guards, so a row that cannot be converted says
    // why before the request is ever made — the same approach users/index
    // takes with is_last_administrator.
    const blockedReason = (row: AccountRow): string | null => {
        if (row.is_self) return t('You cannot convert your own account.');
        if (toClient && row.is_last_administrator) return t('This is the last active administrator account.');
        if (!toClient && row.account_requested) return t('Approve or deny this account request before converting it.');
        return null;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Convert accounts')} />

            <div className="px-4 py-6">
                <Heading
                    title={t('Convert accounts')}
                    description={t('Move an account between staff and clients. Files they uploaded are always kept.')}
                />

                <nav className="mb-6 flex gap-1 border-b">
                    {(['to_client', 'to_staff'] as Direction[]).map((key) => (
                        <button
                            type="button"
                            key={key}
                            onClick={() => set('direction', key)}
                            className={`border-b-2 px-3 py-2 text-sm ${direction === key ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'}`}
                        >
                            {key === 'to_client' ? t('Staff → client') : t('Client → staff')}
                        </button>
                    ))}
                </nav>

                <ListToolbar showClear={values.search !== ''} onClear={() => set('search', '')}>
                    <FilterField label={t('Search')} htmlFor="convert-search">
                        <Input
                            id="convert-search"
                            type="search"
                            placeholder={t('Name or email address')}
                            className="w-64"
                            value={values.search}
                            onChange={(e) => set('search', e.target.value, true)}
                        />
                    </FilterField>
                </ListToolbar>

                <TableShell
                    columns={[t('Name'), t('Email address'), toClient ? t('Role') : t('Status'), t('Will be revoked'), null]}
                    isEmpty={accounts.length === 0}
                    emptyMessage={<>{t('No accounts match these filters.')}</>}
                >
                    {accounts.map((row) => {
                        const blocked = blockedReason(row);
                        const revoked = toClient
                            ? row.consequences.api_tokens + row.consequences.assigned_clients
                            : row.consequences.managed_by;

                        return (
                            <tr key={row.id} className="border-b last:border-0">
                                <td className="px-4 py-2.5 font-medium">{row.name}</td>
                                <td className="text-muted-foreground px-4 py-2.5">{row.email}</td>
                                <td className="px-4 py-2.5">
                                    {toClient ? (
                                        (row.role ?? '—')
                                    ) : (
                                        <Badge variant={row.active ? 'secondary' : 'destructive'}>
                                            {row.account_requested ? t('Pending approval') : row.active ? t('Active') : t('Inactive')}
                                        </Badge>
                                    )}
                                </td>
                                <td className="text-muted-foreground px-4 py-2.5">
                                    {revoked === 0 ? t('Nothing') : plural(t, revoked, '1 item', ':count items')}
                                </td>
                                <td className="px-4 py-2.5 text-right">
                                    {blocked !== null ? (
                                        <Button variant="ghost" size="sm" className="text-muted-foreground" disabled title={blocked}>
                                            {t('Convert')}
                                        </Button>
                                    ) : (
                                        <Button variant="outline" size="sm" onClick={() => setTarget(row)}>
                                            {t('Convert')}
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </TableShell>

                <Pagination meta={pagination} />
            </div>

            {target !== null && (
                <ConvertDialog
                    account={target}
                    direction={direction}
                    roles={roles}
                    clients={clients}
                    onClose={() => setTarget(null)}
                />
            )}
        </AppLayout>
    );
}

/**
 * The confirmation. Its three lists are the entire safety story of this
 * feature, so they name real numbers rather than warning vaguely — and
 * they say what survives as plainly as what does not, because "converting
 * back restores the account" is only trustworthy if you can see it.
 */
function ConvertDialog({
    account,
    direction,
    roles,
    clients,
    onClose,
}: {
    account: AccountRow;
    direction: Direction;
    roles: RoleOption[];
    clients: { id: number; name: string }[];
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const toClient = direction === 'to_client';
    const c = account.consequences;

    const { data, setData, post, processing, errors } = useForm<{
        direction: Direction;
        role_id: string;
        assigned_clients: number[];
        password: string;
    }>({
        direction,
        role_id: roles[0] ? String(roles[0].id) : '',
        assigned_clients: [],
        password: '',
    });

    const chosenRole = roles.find((role) => String(role.id) === data.role_id);

    const submit = () => {
        post(route('users.convert.store', account.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const revoked = toClient
        ? [
              c.api_tokens > 0 && plural(t, c.api_tokens, '1 API token', ':count API tokens'),
              c.assigned_clients > 0 && plural(t, c.assigned_clients, '1 assigned client', ':count assigned clients'),
          ]
        : [
              c.managed_by > 0 &&
                  plural(t, c.managed_by, 'Managed by 1 staff member', 'Managed by :count staff members'),
          ];

    const kept = [
        (c.content.files > 0 || c.content.folders > 0) &&
            t(':files files and :folders folders they uploaded', {
                files: c.content.files,
                folders: c.content.folders,
            }),
        c.group_memberships > 0 && plural(t, c.group_memberships, '1 group membership', ':count group memberships'),
        c.files_shared_with_them > 0 &&
            plural(t, c.files_shared_with_them, '1 file shared with them', ':count files shared with them'),
    ];

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogTitle>
                    {toClient ? t('Convert :name to a client', { name: account.name }) : t('Convert :name to staff', { name: account.name })}
                </DialogTitle>
                <DialogDescription>
                    {toClient
                        ? t('They lose access to every staff screen immediately.')
                        : t('They gain access to the staff screens their role allows.')}
                </DialogDescription>

                {!toClient && (
                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="convert-role">{t('Role')}</Label>
                            <Select value={data.role_id} onValueChange={(value) => setData('role_id', value)}>
                                <SelectTrigger id="convert-role" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem key={role.id} value={String(role.id)}>
                                            {role.is_system ? t(role.name) : role.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.role_id} />
                        </div>

                        {chosenRole?.client_scoped && (
                            <div className="grid gap-2">
                                <Label>{t('Assigned clients')}</Label>
                                <div className="max-h-40 space-y-1 overflow-y-auto rounded border p-2">
                                    {clients
                                        .filter((client) => client.id !== account.id)
                                        .map((client) => (
                                            <label key={client.id} className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={data.assigned_clients.includes(client.id)}
                                                    onCheckedChange={(checked) =>
                                                        setData(
                                                            'assigned_clients',
                                                            checked === true
                                                                ? [...data.assigned_clients, client.id]
                                                                : data.assigned_clients.filter((id) => id !== client.id),
                                                        )
                                                    }
                                                />
                                                {client.name}
                                            </label>
                                        ))}
                                </div>
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="convert-password">
                                {account.requires_new_password ? t('New password') : t('New password (optional)')}
                            </Label>
                            <Input
                                id="convert-password"
                                type="password"
                                required={account.requires_new_password}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            <p className="text-muted-foreground text-sm">
                                {!account.requires_new_password
                                    ? t('Leave blank to keep their current password.')
                                    : account.auth_source === 'ldap'
                                      ? t('This account signs in through your directory. Staff accounts never do, so it needs a password here or they will not be able to sign in at all.')
                                      : t('This account has never had a password of its own — it signs in through a connected provider. Staff need a credential this application can check on its own, so that a provider outage cannot lock them out.')}
                            </p>
                            <InputError message={errors.password} />
                        </div>
                    </div>
                )}

                <div className="space-y-3 text-sm">
                    <div>
                        <p className="font-medium">{t('Will be revoked and cannot be undone')}</p>
                        <ul className="text-muted-foreground list-inside list-disc">
                            {revoked.filter(Boolean).length === 0 ? (
                                <li>{t('Nothing')}</li>
                            ) : (
                                revoked.filter(Boolean).map((line) => <li key={String(line)}>{line}</li>)
                            )}
                        </ul>
                    </div>

                    <div>
                        <p className="font-medium">{t('Will be kept')}</p>
                        <ul className="text-muted-foreground list-inside list-disc">
                            {kept.filter(Boolean).length === 0 ? (
                                <li>{t('Nothing to keep')}</li>
                            ) : (
                                kept.filter(Boolean).map((line) => <li key={String(line)}>{line}</li>)
                            )}
                        </ul>
                        <p className="text-muted-foreground mt-1">
                            {t('Converting back restores everything under "Will be kept". It does not restore what was revoked.')}
                        </p>
                    </div>
                </div>

                <InputError message={(errors as Partial<Record<string, string>>).user} />

                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={onClose}>
                        {t('Cancel')}
                    </Button>
                    <Button variant="destructive" onClick={submit} disabled={processing}>
                        {toClient ? t('Convert to client') : t('Convert to staff')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
