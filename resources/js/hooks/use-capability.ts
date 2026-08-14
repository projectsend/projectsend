import { Capability, SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Whether a capability is enabled for the running edition.
 *
 * UI-side hiding only — the backend gate/middleware is the authoritative
 * check. Use to omit navigation and screens that don't exist in this edition.
 */
export function useCapability(capability: Capability): boolean {
    const { capabilities } = usePage<SharedData>().props;

    return capabilities.includes(capability);
}
