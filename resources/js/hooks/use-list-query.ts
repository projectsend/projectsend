import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

/**
 * Sentinel for a "no filter" <Select> value. Radix selects can't use an empty
 * string as an item value, so an explicit "all" stands in and is stripped from
 * the query string before navigating.
 */
export const ALL = 'all';

type Values = Record<string, string>;

const isSet = (value: string) => value !== '' && value !== ALL;

/**
 * Drives a server-paginated list from the query string. Text inputs call
 * `set(key, value, true)` to debounce; selects call `set(key, value)` for an
 * instant apply. Navigation replaces history (so filtering doesn't stack
 * back-button entries) and preserves scroll and component state.
 */
export function useListQuery(routeName: string, initial: Values, empty?: Values, routeParams?: Record<string, unknown>) {
    const [values, setValues] = useState<Values>(initial);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const push = (next: Values) => {
        const params: Values = {};
        for (const [key, value] of Object.entries(next)) {
            if (isSet(value)) params[key] = value;
        }
        router.get(route(routeName, routeParams), params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const set = (key: string, value: string, debounce = false) => {
        setMany({ [key]: value }, debounce);
    };

    // For a single control that maps to more than one query-string key (e.g.
    // a combined "sort by" select that's really `sort` + `direction`) — two
    // separate `set()` calls back to back would each push from the same
    // stale `values` closure and race each other.
    const setMany = (updates: Values, debounce = false) => {
        const next = { ...values, ...updates };
        setValues(next);

        if (timer.current) clearTimeout(timer.current);
        if (debounce) {
            timer.current = setTimeout(() => push(next), 300);
        } else {
            push(next);
        }
    };

    const reset = () => {
        const cleared = empty ?? Object.fromEntries(Object.keys(values).map((key) => [key, '']));
        if (timer.current) clearTimeout(timer.current);
        setValues(cleared);
        push(cleared);
    };

    const hasFilters = Object.values(values).some(isSet);

    return { values, set, setMany, reset, hasFilters };
}
