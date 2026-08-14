/**
 * How to name whoever performed a logged action.
 *
 * Four renderers were each carrying their own copy of this, which is how
 * an unauthenticated visitor came to be shown as "System" in all of them:
 * one word covering both the scheduler and a stranger leaving a comment,
 * so the audit trail read as though the installation had commented on its
 * own file.
 *
 * Returns a translation key, not a translated string — the caller holds
 * the `t` from its own component.
 */
export function activityActorLabel(entry: { actor_name: string | null; actor_type: string | null; origin?: string }): {
    kind: 'named' | 'anonymous' | 'system' | 'deleted';
    key: string;
} {
    if (entry.actor_name !== null) return { kind: 'named', key: entry.actor_name };

    // A name is missing for three different reasons, and they are not
    // interchangeable: nobody was signed in, nothing human was involved at
    // all, or the account has since been erased.
    if (entry.actor_type !== null) return { kind: 'deleted', key: 'Deleted account' };

    return entry.origin === 'public' ? { kind: 'anonymous', key: 'Anonymous' } : { kind: 'system', key: 'System' };
}
