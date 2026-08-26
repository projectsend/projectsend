<?php

declare(strict_types=1);

namespace App\Support;

use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaForm;
use App\Modules\Files\Folders\FolderExistsRule;
use App\Modules\Platform\Captcha\CaptchaRule;
use App\Modules\Platform\Localization\TimezoneRegistry;
use Illuminate\Validation\Rule;

/**
 * Validation rules shared across modules, where having one definition
 * matters more than having it next to its caller.
 */
class Rules
{
    /**
     * The rule for a user-supplied public URL slug.
     *
     * The pattern is deliberately strict — lowercase alphanumerics in
     * hyphen-separated runs, with no leading, trailing or doubled hyphen —
     * because these land directly in a public URL path segment. Loosening
     * it in one place and not the others is exactly what this being shared
     * is meant to prevent.
     *
     * A slug only matters (and is only shown) once the file, folder or
     * group is public; otherwise one derived from the name stands in, so
     * the field is required only when `public` is true. On an update,
     * omitting it leaves the current slug alone — it must not silently
     * change just because the name did.
     *
     * @param  string  $table  the table whose slugs must stay distinct
     * @param  int|null  $ignoreId  the row being updated, which must not
     *                              collide with the slug it already holds
     * @return array<int, mixed>
     */
    public static function slug(string $table, ?int $ignoreId = null): array
    {
        $unique = Rule::unique($table, 'slug');

        return [
            'required_if:public,true',
            'nullable',
            'string',
            'max:255',
            'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
            $ignoreId === null ? $unique : $unique->ignore($ignoreId),
        ];
    }

    /**
     * The rule for an id naming a library folder.
     *
     * Shared because the plain `exists:folders,id` it replaces is not
     * true: Folder uses SoftDeletes, and the presence check runs against
     * the table, so a folder in the trash passes it. Every caller then
     * reads the rule as "this folder exists" and behaves accordingly —
     * and the ones that resolve the id afterwards resolve it through
     * Folder::query(), which does honour the soft delete, so the guard
     * sees no folder at all while the value that reaches the write is
     * still the id.
     *
     * FilesController::store() and Api\FilesController::store() ended up
     * filing an upload into a deleted folder that way: the guard read
     * null and allowed it as a root upload, and the row was written with
     * the id. Deleting a folder deletes every file in its subtree, so
     * that is a live file inside a folder whose deletion already removed
     * everything in it — reachable by id, in search and over the API,
     * and absent from the listing its uploader would look in.
     *
     * Making the rule mean what its readers already assume fixes those
     * and leaves the paths that resolve through StaffLibraryScope alone;
     * they refuse a trashed id today by a longer route.
     *
     * Presence is the caller's business, as with slug() above: spread it
     * behind `sometimes` where a PATCH may omit the field.
     *
     * A rule object rather than a conditional `exists`, so the refusal can
     * say why — see FolderExistsRule.
     *
     * @return array<int, mixed>
     */
    public static function folderId(): array
    {
        return ['nullable', 'integer', new FolderExistsRule];
    }

    /**
     * The rule for an IANA timezone identifier.
     *
     * Shared because two things write `users.timezone` — the picker on the
     * profile form and the silent browser detection behind PUT /timezone —
     * and a zone the registry would refuse must not be storable through
     * either. The framework's own `timezone` rule is not enough on its own:
     * it accepts abbreviations and offsets that `DateTimeZone` tolerates
     * but that never appear in the picker, so membership is checked too.
     *
     * Presence is the caller's business: both callers currently require it,
     * but the field is always sent by a control that has a value, so
     * whether an omission clears or keeps is a decision for the form, not
     * for the format.
     *
     * @return array<int, mixed>
     */
    public static function timezone(): array
    {
        return ['string', 'timezone', Rule::in(app(TimezoneRegistry::class)->all())];
    }

    /**
     * The rules protecting one form with a CAPTCHA, or none at all.
     *
     * Shared because four unrelated forms — login, client registration,
     * the password-reset request and a visitor's comment — must each
     * enforce this identically, and because "this installation does not
     * protect this form" needs to read as an empty rule set at every one
     * of them rather than as four slightly different conditionals.
     *
     * Spread into the caller's rules:
     *
     *     $request->validate([
     *         'email' => ['required', 'email'],
     *         ...Rules::captcha(CaptchaForm::Login),
     *     ]);
     *
     * `bail` so a missing token is refused before any network call, and
     * `required` even on the forms that let an unreachable provider
     * through: failing open is about *our server* being unable to ask, and
     * treating an absent field as an answer is precisely the bug that left
     * v1's registration form unprotected.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function captcha(CaptchaForm $form): array
    {
        if (! app(Captcha::class)->protects($form)) {
            return [];
        }

        return ['captcha_token' => ['bail', 'required', 'string', 'max:5000', new CaptchaRule($form)]];
    }
}
