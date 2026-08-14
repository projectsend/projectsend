import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';

export interface CustomFieldDefinition {
    id: number;
    label: string;
    type: string;
    options: string[] | null;
    required: boolean;
    locked?: boolean;
}

interface ClientCustomFieldsSectionProps {
    fields: CustomFieldDefinition[];
    values: Record<string, string>;
    onChange: (fieldId: number, value: string) => void;
    errors: Partial<Record<string, string>>;
}

/**
 * Custom field values are keyed by field id in both `values` and the
 * outgoing `custom_field_values` payload — checkboxes store '1'/'0'
 * rather than a real boolean so every field shares one string shape.
 */
export function ClientCustomFieldsSection({ fields, values, onChange, errors }: ClientCustomFieldsSectionProps) {
    const { t } = useTranslation();

    if (fields.length === 0) return null;

    return (
        <div className="grid gap-6">
            {fields.map((field) => {
                const value = values[field.id] ?? '';
                const error = errors[`custom_field_values.${field.id}`];
                const htmlId = `custom-field-${field.id}`;

                if (field.type === 'checkbox') {
                    return (
                        <div key={field.id} className="grid gap-2">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id={htmlId}
                                    checked={value === '1'}
                                    disabled={field.locked}
                                    onCheckedChange={(checked) => onChange(field.id, checked === true ? '1' : '0')}
                                />
                                <Label htmlFor={htmlId} className="font-normal">
                                    {field.label}
                                </Label>
                            </div>
                            <InputError message={error} />
                        </div>
                    );
                }

                return (
                    <div key={field.id} className="grid gap-2">
                        <Label htmlFor={htmlId}>
                            {field.label}
                            {field.required && ' *'}
                        </Label>
                        {field.type === 'textarea' ? (
                            <Textarea
                                id={htmlId}
                                value={value}
                                disabled={field.locked}
                                onChange={(e) => onChange(field.id, e.target.value)}
                            />
                        ) : field.type === 'select' ? (
                            <Select value={value} disabled={field.locked} onValueChange={(v) => onChange(field.id, v)}>
                                <SelectTrigger id={htmlId} className="w-full">
                                    <SelectValue placeholder={t('Select an option')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {(field.options ?? []).map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : (
                            <Input
                                id={htmlId}
                                value={value}
                                disabled={field.locked}
                                onChange={(e) => onChange(field.id, e.target.value)}
                            />
                        )}
                        <InputError message={error} />
                    </div>
                );
            })}
        </div>
    );
}
