import { useEffect } from 'react';

import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useTranslation } from '@/hooks/use-translation';

// A folder nobody can put anything in isn't useful on its own — enforced
// both for staff (FoldersController::store()) and clients
// (MyFoldersController::store()), so reflected here too rather than
// letting a role be saved in a state where the checkbox does nothing.
const FOLDERS_PERMISSION = 'create_own_folders';
const UPLOAD_PERMISSION = 'upload';

export interface PermissionCatalogCategory {
    key: string;
    label: string;
    permissions: { key: string; label: string }[];
}

interface RoleFormProps {
    name: string;
    onNameChange: (name: string) => void;
    nameLocked: boolean;
    clientScoped: boolean;
    onClientScopedChange: (value: boolean) => void;
    scopeLocked: boolean;
    permissions: string[];
    onPermissionsChange: (permissions: string[]) => void;
    catalog: PermissionCatalogCategory[];
    errors: Partial<Record<string, string>>;
}

export function RoleForm({
    name,
    onNameChange,
    nameLocked,
    clientScoped,
    onClientScopedChange,
    scopeLocked,
    permissions,
    onPermissionsChange,
    catalog,
    errors,
}: RoleFormProps) {
    const { t } = useTranslation();

    const hasUpload = permissions.includes(UPLOAD_PERMISSION);

    // A role saved before this coupling existed could already have
    // create_own_folders without upload — the checkbox below renders
    // disabled in that state, which would otherwise trap the admin with
    // no way to ever uncheck it. Clean it up once, on load.
    useEffect(() => {
        if (!hasUpload && permissions.includes(FOLDERS_PERMISSION)) {
            onPermissionsChange(permissions.filter((permission) => permission !== FOLDERS_PERMISSION));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [hasUpload]);

    const toggle = (key: string, checked: boolean) => {
        let next = checked ? [...permissions, key] : permissions.filter((permission) => permission !== key);

        // create_own_folders requires upload (FoldersController::store()/
        // MyFoldersController::store()) — unchecking upload drops it too,
        // rather than leaving a role saved where the box does nothing.
        if (key === UPLOAD_PERMISSION && !checked) {
            next = next.filter((permission) => permission !== FOLDERS_PERMISSION);
        }

        onPermissionsChange(next);
    };

    const toggleCategory = (category: PermissionCatalogCategory, checked: boolean) => {
        const keys = category.permissions.map((permission) => permission.key);
        const rest = permissions.filter((permission) => !keys.includes(permission));
        onPermissionsChange(checked ? [...rest, ...keys] : rest);
    };

    return (
        <div className="space-y-8">
            <div className="grid max-w-md gap-2">
                <Label htmlFor="name">{t('Role name')}</Label>
                <Input id="name" value={name} onChange={(e) => onNameChange(e.target.value)} disabled={nameLocked} required />
                {nameLocked && <p className="text-muted-foreground text-sm">{t('Built-in role names cannot be changed.')}</p>}
                <InputError message={errors.name} />
            </div>

            <div className="max-w-md">
                <div className="flex items-start gap-2">
                    <Checkbox
                        id="client_scoped"
                        checked={clientScoped}
                        disabled={scopeLocked}
                        onCheckedChange={(checked) => onClientScopedChange(checked === true)}
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="client_scoped" className="font-normal">
                            {t('Limit to assigned clients')}
                        </Label>
                        <p className="text-muted-foreground text-sm">
                            {t('Members of this role only see files and folders belonging to the clients assigned to them, plus their own uploads.')}
                        </p>
                        {scopeLocked && <p className="text-muted-foreground text-sm">{t("This is a built-in role; its scope can't be changed.")}</p>}
                    </div>
                </div>
            </div>

            <div className="space-y-6">
                {catalog.map((category) => {
                    const allChecked = category.permissions.every((permission) => permissions.includes(permission.key));

                    return (
                        <div key={category.key} className="rounded-lg border p-4">
                            <div className="mb-3 flex items-center gap-2">
                                <Checkbox
                                    id={`category-${category.key}`}
                                    checked={allChecked}
                                    onCheckedChange={(checked) => toggleCategory(category, checked === true)}
                                />
                                <Label htmlFor={`category-${category.key}`} className="font-semibold">
                                    {t(category.label)}
                                </Label>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {category.permissions.map((permission) => {
                                    const disabled = permission.key === FOLDERS_PERMISSION && !hasUpload;
                                    const checkbox = (
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id={`permission-${permission.key}`}
                                                checked={permissions.includes(permission.key)}
                                                disabled={disabled}
                                                onCheckedChange={(checked) => toggle(permission.key, checked === true)}
                                            />
                                            <Label htmlFor={`permission-${permission.key}`} className="font-normal">
                                                {t(permission.label)}
                                            </Label>
                                        </div>
                                    );

                                    if (!disabled) {
                                        return <div key={permission.key}>{checkbox}</div>;
                                    }

                                    return (
                                        <TooltipProvider key={permission.key} delayDuration={0}>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <span>{checkbox}</span>
                                                </TooltipTrigger>
                                                <TooltipContent>{t('Requires Upload files')}</TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </div>

            <InputError message={errors.permissions} />
        </div>
    );
}
