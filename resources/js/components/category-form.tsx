import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { categoryColor } from '@/lib/category-colors';

interface CategoryFormProps {
    name: string;
    color: string;
    colors: string[];
    onChange: (field: 'name' | 'color', value: string) => void;
    errors: Partial<Record<string, string>>;
}

export function CategoryForm({ name, color, colors, onChange, errors }: CategoryFormProps) {
    const { t } = useTranslation();

    return (
        <div className="grid max-w-md gap-6">
            <div className="grid gap-2">
                <Label htmlFor="name">{t('Name')}</Label>
                <Input id="name" value={name} onChange={(e) => onChange('name', e.target.value)} required autoFocus />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label>{t('Color')}</Label>
                <div className="flex flex-wrap gap-2">
                    {colors.map((value) => {
                        const def = categoryColor(value);

                        return (
                            <button
                                key={value}
                                type="button"
                                onClick={() => onChange('color', value)}
                                aria-label={t(def.label)}
                                aria-pressed={color === value}
                                title={t(def.label)}
                                className={`size-6 rounded-full ${def.swatch} ${color === value ? 'ring-ring ring-2 ring-offset-2' : ''}`}
                            />
                        );
                    })}
                </div>
                <InputError message={errors.color} />
            </div>
        </div>
    );
}
