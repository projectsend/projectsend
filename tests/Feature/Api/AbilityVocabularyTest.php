<?php

declare(strict_types=1);

use App\Modules\Identity\Permissions\Permission;

/*
 * Staff token abilities are the Permission enum's own snake_case values.
 * When client tokens arrive they will use a dot-namespaced vocabulary
 * ('files.read', 'groups.read') so the two sets can never be confused for
 * one another — a client scope must never accidentally satisfy a check
 * written against a staff permission, or vice versa.
 *
 * This is the cheap half of that guarantee, asserted now while it costs
 * nothing: reserve the dotted namespace by keeping every staff permission
 * out of it.
 */
test('no staff permission uses the namespace reserved for client scopes', function () {
    $dotted = array_filter(
        Permission::cases(),
        static fn (Permission $permission): bool => str_contains($permission->value, '.'),
    );

    expect($dotted)->toBe([]);
});

test('permission values are unique', function () {
    $values = array_map(static fn (Permission $p): string => $p->value, Permission::cases());

    expect($values)->toHaveCount(count(array_unique($values)));
});
