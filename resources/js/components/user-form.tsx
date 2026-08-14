import { X } from 'lucide-react';

import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PasswordRequirements } from '@/components/password-requirements';
import { useTranslation } from '@/hooks/use-translation';

export interface AssignableRole {
    id: number;
    name: string;
    is_system: boolean;
    client_scoped: boolean;
}

export interface ClientOption {
    id: number;
    name: string;
}

interface UserFormProps {
    name: string;
    email: string;
    roleId: string;
    password: string;
    passwordConfirmation: string;
    onChange: (field: 'name' | 'email' | 'role_id' | 'password' | 'password_confirmation', value: string) => void;
    roles: AssignableRole[];
    clients: ClientOption[];
    assignedClients: number[];
    onAssignedClientsChange: (ids: number[]) => void;
    passwordOptional: boolean;
    errors: Partial<Record<string, string>>;
}

export function UserForm({
    name,
    email,
    roleId,
    password,
    passwordConfirmation,
    onChange,
    roles,
    clients,
    assignedClients,
    onAssignedClientsChange,
    passwordOptional,
    errors,
}: UserFormProps) {
    const { t } = useTranslation();

    const selectedRole = roles.find((role) => String(role.id) === roleId);

    return (
        <div className="grid max-w-md gap-6">
            <div className="grid gap-2">
                <Label htmlFor="name">{t('Name')}</Label>
                <Input id="name" value={name} onChange={(e) => onChange('name', e.target.value)} required autoComplete="off" />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">{t('Email address')}</Label>
                <Input id="email" type="email" value={email} onChange={(e) => onChange('email', e.target.value)} required autoComplete="off" />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="role_id">{t('Role')}</Label>
                <Select value={roleId} onValueChange={(value) => onChange('role_id', value)}>
                    <SelectTrigger id="role_id" className="w-full">
                        <SelectValue placeholder={t('Select a role')} />
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

            {selectedRole?.client_scoped && (
                <AssignedClientsField
                    clients={clients}
                    selected={assignedClients}
                    onChange={onAssignedClientsChange}
                    error={errors.assigned_clients}
                />
            )}

            <div className="grid gap-2">
                <Label htmlFor="password">{passwordOptional ? t('New password (leave blank to keep current)') : t('Password')}</Label>
                <Input
                    id="password"
                    type="password"
                    value={password}
                    onChange={(e) => onChange('password', e.target.value)}
                    required={!passwordOptional}
                    autoComplete="new-password"
                />
                <PasswordRequirements />
                <InputError message={errors.password} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password_confirmation">{t('Confirm password')}</Label>
                <Input
                    id="password_confirmation"
                    type="password"
                    value={passwordConfirmation}
                    onChange={(e) => onChange('password_confirmation', e.target.value)}
                    required={!passwordOptional || password !== ''}
                    autoComplete="new-password"
                />
                <InputError message={errors.password_confirmation} />
            </div>
        </div>
    );
}

/**
 * The clients a client-scoped staff member manages: pick from a dropdown,
 * remove via chips. Mirrors the folder-sharing picker.
 */
function AssignedClientsField({
    clients,
    selected,
    onChange,
    error,
}: {
    clients: ClientOption[];
    selected: number[];
    onChange: (ids: number[]) => void;
    error?: string;
}) {
    const { t } = useTranslation();

    const available = clients.filter((client) => !selected.includes(client.id));
    const nameFor = (id: number) => clients.find((client) => client.id === id)?.name ?? String(id);

    return (
        <div className="grid gap-2">
            <Label>{t('Assigned clients')}</Label>
            <p className="text-muted-foreground text-sm">
                {t('This role only sees files and folders belonging to these clients, plus its own uploads.')}
            </p>

            <Select value="" onValueChange={(value) => onChange([...selected, Number(value)])}>
                <SelectTrigger className="w-full">
                    <SelectValue placeholder={t('Add a client')} />
                </SelectTrigger>
                <SelectContent>
                    {available.length === 0 ? (
                        <div className="text-muted-foreground px-2 py-1.5 text-sm">{t('No more clients to add')}</div>
                    ) : (
                        available.map((client) => (
                            <SelectItem key={client.id} value={String(client.id)}>
                                {client.name}
                            </SelectItem>
                        ))
                    )}
                </SelectContent>
            </Select>

            {selected.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {selected.map((id) => (
                        <span key={id} className="bg-muted inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm">
                            {nameFor(id)}
                            <button
                                type="button"
                                className="text-muted-foreground hover:text-foreground"
                                onClick={() => onChange(selected.filter((x) => x !== id))}
                            >
                                <X className="size-3.5" />
                                <span className="sr-only">{t('Remove')}</span>
                            </button>
                        </span>
                    ))}
                </div>
            )}

            <InputError message={error} />
        </div>
    );
}
