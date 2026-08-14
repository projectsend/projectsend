import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { type SetDataByKeyValuePair } from '@inertiajs/react';

export interface ClientCustomFieldFormData {
    // No index signature: Inertia's setData key type is string-based, and
    // `keyof` over a string index signature also yields `number`, which
    // does not fit it. Dropping it fixes that and makes the key union
    // real — with it, any string type-checked as a valid field name.
    label: string;
    type: string;
    options: string[];
    required: boolean;
    client_editability: string;
    client_contexts: string[];
}

interface ClientCustomFieldFormProps {
    data: ClientCustomFieldFormData;
    types: string[];
    // Inertia's own setData signature rather than a hand-rolled generic:
    // its key type is a dotted-path union (FormDataKeys), not plain keyof,
    // so a bespoke `<K extends keyof T>(k: K, v: T[K])` cannot be proven
    // compatible and the page has to pass setData through unchanged.
    onChange: SetDataByKeyValuePair<ClientCustomFieldFormData>;
    errors: Partial<Record<string, string>>;
}

const typeLabels: Record<string, string> = {
    text: 'Text',
    textarea: 'Multi-line text',
    select: 'Dropdown',
    checkbox: 'Checkbox',
};

const editabilityLabels: Record<string, string> = {
    hidden: 'Hidden from client',
    editable: 'Client can edit',
    editable_once: 'Client can set once',
};

const contextLabels: Record<string, string> = {
    registration: 'Registration form',
    account_edit: 'Account page',
};

export function ClientCustomFieldForm({ data, types, onChange, errors }: ClientCustomFieldFormProps) {
    const { t } = useTranslation();

    return (
        <div className="grid max-w-md gap-6">
            <div className="grid gap-2">
                <Label htmlFor="label">{t('Label')}</Label>
                <Input id="label" value={data.label} onChange={(e) => onChange('label', e.target.value)} required autoFocus />
                <InputError message={errors.label} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="type">{t('Type')}</Label>
                <Select value={data.type} onValueChange={(value) => onChange('type', value)}>
                    <SelectTrigger id="type" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {types.map((type) => (
                            <SelectItem key={type} value={type}>
                                {t(typeLabels[type] ?? type)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.type} />
            </div>

            {data.type === 'select' && (
                <div className="grid gap-2">
                    <Label htmlFor="options">{t('Options')}</Label>
                    <Textarea
                        id="options"
                        rows={4}
                        placeholder={t('One option per line')}
                        value={data.options.join('\n')}
                        onChange={(e) =>
                            onChange(
                                'options',
                                e.target.value.split('\n').map((line) => line.trim()),
                            )
                        }
                    />
                    <InputError message={errors.options} />
                </div>
            )}

            <div className="flex items-center gap-2">
                <Checkbox id="required" checked={data.required} onCheckedChange={(checked) => onChange('required', checked === true)} />
                <Label htmlFor="required" className="font-normal">
                    {t('Required')}
                </Label>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="client_editability">{t('Client access')}</Label>
                <Select value={data.client_editability} onValueChange={(value) => onChange('client_editability', value)}>
                    <SelectTrigger id="client_editability" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.keys(editabilityLabels).map((value) => (
                            <SelectItem key={value} value={value}>
                                {t(editabilityLabels[value])}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.client_editability} />
            </div>

            {data.client_editability !== 'hidden' && (
                <div className="grid gap-2">
                    <Label>{t('Where')}</Label>
                    {Object.keys(contextLabels).map((context) => (
                        <div key={context} className="flex items-center gap-2">
                            <Checkbox
                                id={`context-${context}`}
                                checked={data.client_contexts.includes(context)}
                                onCheckedChange={(checked) =>
                                    onChange(
                                        'client_contexts',
                                        checked === true ? [...data.client_contexts, context] : data.client_contexts.filter((c) => c !== context),
                                    )
                                }
                            />
                            <Label htmlFor={`context-${context}`} className="font-normal">
                                {t(contextLabels[context])}
                            </Label>
                        </div>
                    ))}
                    <InputError message={errors.client_contexts} />
                </div>
            )}
        </div>
    );
}
