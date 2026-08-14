import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, Clock, XCircle } from 'lucide-react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface GroupCard {
    id: number;
    name: string;
    description: string | null;
    requested?: boolean;
    denied?: boolean;
}

interface MyGroupsProps {
    memberships: GroupCard[];
    available: GroupCard[];
}

export default function MyGroups({ memberships, available }: MyGroupsProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('My groups'), href: '/my-groups' }];

    const requestMembership = (groupId: number) => {
        router.post(route('my-groups.store'), { group_id: groupId }, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('My groups')} />

            <div className="space-y-10 px-4 py-6">
                <div>
                    <Heading title={t('My groups')} description={t('The groups you belong to and the ones you can ask to join')} />

                    <div className="max-w-2xl space-y-2">
                        {memberships.length === 0 && (
                            <p className="text-muted-foreground rounded-lg border px-4 py-6 text-center text-sm">
                                {t('You are not a member of any group yet.')}
                            </p>
                        )}
                        {memberships.map((group) => (
                            <div key={group.id} className="flex items-center justify-between rounded-lg border px-4 py-3">
                                <div>
                                    <p className="text-sm font-medium">{group.name}</p>
                                    {group.description && <p className="text-muted-foreground text-xs">{group.description}</p>}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant="secondary">
                                        <Check className="mr-1 size-3" />
                                        {t('Member')}
                                    </Badge>
                                    <ConfirmDialog
                                        trigger={
                                            <Button size="sm" variant="ghost" className="text-destructive hover:text-destructive">
                                                {t('Leave')}
                                            </Button>
                                        }
                                        title={t('Leave this group?')}
                                        description={t('You will no longer receive files shared with the group ":name".', { name: group.name })}
                                        confirmLabel={t('Leave group')}
                                        onConfirm={() => router.delete(route('my-groups.leave', group.id), { preserveScroll: true })}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {available.length > 0 && (
                    <div className="max-w-2xl space-y-4">
                        <HeadingSmall title={t('Available groups')} description={t('Membership requests are reviewed by an administrator')} />

                        <div className="space-y-2">
                            {available.map((group) => (
                                <div key={group.id} className="flex items-center justify-between rounded-lg border px-4 py-3">
                                    <div>
                                        <p className="text-sm font-medium">{group.name}</p>
                                        {group.description && <p className="text-muted-foreground text-xs">{group.description}</p>}
                                    </div>
                                    {group.requested ? (
                                        <Badge variant="outline">
                                            <Clock className="mr-1 size-3" />
                                            {t('Requested')}
                                        </Badge>
                                    ) : group.denied ? (
                                        <Badge variant="outline" className="text-muted-foreground">
                                            <XCircle className="mr-1 size-3" />
                                            {t('Request declined')}
                                        </Badge>
                                    ) : (
                                        <Button size="sm" variant="outline" onClick={() => requestMembership(group.id)}>
                                            {t('Request membership')}
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
