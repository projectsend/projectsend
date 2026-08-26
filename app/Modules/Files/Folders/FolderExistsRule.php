<?php

declare(strict_types=1);

namespace App\Modules\Files\Folders;

use App\Modules\Files\Models\Folder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An id naming a folder that is actually there.
 *
 * A rule object rather than `Rule::exists(...)->whereNull('deleted_at')`
 * for one reason: the message. The generic form says "The selected folder
 * id is invalid", which tells somebody nothing when the real answer is
 * that the folder they picked has since been deleted — and that is the
 * usual way to meet this rule, since a live id they chose from a list is
 * how they got here. It matters most on the chunked upload path, which is
 * the one place a request that used to succeed now fails.
 *
 * Carrying the message on the rule keeps the single definition
 * Rules::folderId() exists for: a `messages()` array would have to be
 * repeated at every call site, which is how the plain `exists:folders,id`
 * it replaced came to mean two different things in ten places.
 */
class FolderExistsRule implements ValidationRule
{
    /**
     * @param  Closure(string, string|null=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_numeric($value)) {
            // Reached only when a caller drops `integer`; the message
            // still has to make sense to whoever sees it.
            $fail(__('That folder could not be found.'));

            return;
        }

        // Folder::query() honours the soft delete, which is the whole
        // point — the table-level `exists` rule this replaces does not.
        if (Folder::query()->whereKey((int) $value)->exists()) {
            return;
        }

        $fail(__('That folder no longer exists. Pick another one and try again.'));
    }
}
