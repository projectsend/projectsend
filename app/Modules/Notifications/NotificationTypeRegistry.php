<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

/**
 * A pool of registrable notification types, populated by whichever
 * service providers register into it (core types from FilesServiceProvider,
 * GroupsServiceProvider, etc.; premium types like the future file-comment
 * notifications from the private cloud-modules package) — never a closed
 * enum, since core must not need to know a package's notification type
 * keys at compile time. Mirrors ThemeRegistry's shape/rationale exactly.
 *
 * NotificationsServiceProvider only binds this singleton — it registers
 * zero types itself. Each owning module registers its own types in its
 * own provider's boot(), the same way core themes register into
 * ThemeRegistry from PlatformServiceProvider.
 */
class NotificationTypeRegistry
{
    /**
     * @var array<string, NotificationTypeDefinition>
     */
    private array $types = [];

    public function register(NotificationTypeDefinition $definition): void
    {
        $this->types[$definition->key] = $definition;
    }

    public function get(string $key): ?NotificationTypeDefinition
    {
        return $this->types[$key] ?? null;
    }

    /**
     * @return list<NotificationTypeDefinition>
     */
    public function all(): array
    {
        return array_values($this->types);
    }
}
