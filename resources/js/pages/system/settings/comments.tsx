import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SaveButton } from '@/components/save-button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

interface Option {
    value: string;
    label: string;
}

interface CommentSettingsProps {
    comments_scope: string;
    comments_authors: string;
    public_comments_enabled: boolean;
    comments_guest_moderation: boolean;
    comments_edit_window_minutes: number;
    scope_options: Option[];
    author_options: Option[];
}

export default function CommentSettings({
    comments_scope,
    comments_authors,
    public_comments_enabled,
    comments_guest_moderation,
    comments_edit_window_minutes,
    scope_options,
    author_options,
}: CommentSettingsProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Settings'), href: '/system/settings' },
        { title: t('Comments'), href: '/system/settings/comments' },
    ];

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        comments_scope,
        comments_authors,
        public_comments_enabled,
        comments_guest_moderation,
        comments_edit_window_minutes,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('system-settings.comments.update'));
    };

    // Every other field is meaningless once commenting is off entirely.
    const off = data.comments_scope === 'none';
    // Anonymous authors can only ever produce a public comment, so the
    // author setting and the public switch are two halves of one decision
    // — say so rather than letting the combination fail silently.
    const anonymousWithoutPublic = data.comments_authors === 'everyone' && !data.public_comments_enabled;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Comment settings')} />

            <div className="px-4 py-6">
                <Heading title={t('Comment settings')} description={t('Who can comment on files, and which files accept comments')} />

                <form onSubmit={submit} className="max-w-xl space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="comments_scope">{t('Files that accept comments')}</Label>

                        <Select value={data.comments_scope} onValueChange={(value) => setData('comments_scope', value)}>
                            <SelectTrigger id="comments_scope" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {scope_options.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {t(option.label)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <p className="text-muted-foreground text-sm">
                            {data.comments_scope === 'selected'
                                ? t('Each file has a "Allow comments" switch on its edit page. Only files with it turned on accept comments.')
                                : t('Comments already written stay readable even if a file stops accepting new ones.')}
                        </p>

                        <InputError className="mt-2" message={errors.comments_scope} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="comments_authors">{t('Who can write a comment')}</Label>

                        <Select value={data.comments_authors} onValueChange={(value) => setData('comments_authors', value)} disabled={off}>
                            <SelectTrigger id="comments_authors" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {author_options.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {t(option.label)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {anonymousWithoutPublic && (
                            <p className="text-sm text-amber-600 dark:text-amber-500">
                                {t(
                                    'Visitors who are not logged in can only write public comments, so this has no effect until you allow public comments below.',
                                )}
                            </p>
                        )}

                        <InputError className="mt-2" message={errors.comments_authors} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="public_comments_enabled"
                                checked={data.public_comments_enabled}
                                disabled={off}
                                onCheckedChange={(checked) => setData('public_comments_enabled', checked === true)}
                            />
                            <Label htmlFor="public_comments_enabled" className="font-normal">
                                {t('Allow public comments')}
                            </Label>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'Adds an "Everyone" option when commenting on a file that is publicly visible. Without this, comments only ever reach people who are logged in.',
                            )}
                        </p>
                        <InputError className="mt-2" message={errors.public_comments_enabled} />
                    </div>

                    {/* Only shown when there can be visitors to moderate. A
                        permanently greyed-out checkbox is a question the
                        reader has to answer before they can ignore it, and
                        the answer is not on screen — the same reason the
                        per-file "Allow comments" switch appears only under
                        the `selected` scope. The value is still submitted,
                        so turning "Anyone" back on restores the choice that
                        was made rather than a default. */}
                    {data.comments_authors === 'everyone' && (
                        <div className="grid gap-2">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="comments_guest_moderation"
                                    checked={data.comments_guest_moderation}
                                    disabled={off}
                                    onCheckedChange={(checked) => setData('comments_guest_moderation', checked === true)}
                                />
                                <Label htmlFor="comments_guest_moderation" className="font-normal">
                                    {t('Hold comments from visitors for approval')}
                                </Label>
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {t('A comment from someone who is not logged in stays hidden until a moderator approves it.')}
                            </p>
                            <InputError className="mt-2" message={errors.comments_guest_moderation} />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="comments_edit_window_minutes">{t('Editing window (minutes)')}</Label>
                        <Input
                            id="comments_edit_window_minutes"
                            type="number"
                            min={0}
                            max={1440}
                            className="max-w-32"
                            disabled={off}
                            value={data.comments_edit_window_minutes}
                            onChange={(e) => setData('comments_edit_window_minutes', Number(e.target.value))}
                        />
                        <p className="text-muted-foreground text-sm">
                            {t('How long after posting someone can still edit or delete their own comment. Set to 0 to never allow it.')}
                        </p>
                        <InputError className="mt-2" message={errors.comments_edit_window_minutes} />
                    </div>

                    <SaveButton processing={processing} recentlySuccessful={recentlySuccessful} />
                </form>
            </div>
        </AppLayout>
    );
}
