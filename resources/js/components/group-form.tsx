import { useState } from 'react';

import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { slugify } from '@/lib/utils';

interface GroupFormProps {
    name: string;
    slug: string;
    description: string;
    isPublic: boolean;
    onChange: (field: 'name' | 'slug' | 'description' | 'public', value: string | boolean) => void;
    errors: Partial<Record<string, string>>;
}

export function GroupForm({ name, slug, description, isPublic, onChange, errors }: GroupFormProps) {
    const { t } = useTranslation();
    // Auto-fill the slug from the name until the user edits it directly
    // (an already-populated slug, e.g. when editing, counts as touched
    // so we never clobber a deliberate value).
    const [slugTouched, setSlugTouched] = useState(slug !== '');

    return (
        <div className="grid max-w-md gap-6">
            <div className="grid gap-2">
                <Label htmlFor="name">{t('Name')}</Label>
                <Input
                    id="name"
                    value={name}
                    onChange={(e) => onChange('name', e.target.value)}
                    onBlur={() => {
                        if (!slugTouched) onChange('slug', slugify(name));
                    }}
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">{t('Description')}</Label>
                <Input id="description" value={description} onChange={(e) => onChange('description', e.target.value)} />
                <InputError message={errors.description} />
            </div>

            <div className="grid gap-2">
                <div className="flex items-start gap-2">
                    <Checkbox id="public" checked={isPublic} onCheckedChange={(checked) => onChange('public', checked === true)} />
                    <div className="grid gap-1">
                        <Label htmlFor="public" className="font-normal">
                            {t('Public group')}
                        </Label>
                        <p className="text-muted-foreground text-sm">
                            {t('The files of a public group can be browsed by anyone with the link, without logging in.')}
                        </p>
                    </div>
                </div>
                <InputError message={errors.public} />

                {isPublic && (
                    <div className="mt-2 grid gap-2 pl-6">
                        <Label htmlFor="slug">{t('URL slug')}</Label>
                        <Input
                            id="slug"
                            value={slug}
                            onChange={(e) => {
                                setSlugTouched(true);
                                onChange('slug', e.target.value);
                            }}
                            required
                        />
                        <p className="text-muted-foreground text-sm">{t('Used in the public group URL when this group is public.')}</p>
                        <InputError message={errors.slug} />
                    </div>
                )}
            </div>
        </div>
    );
}
