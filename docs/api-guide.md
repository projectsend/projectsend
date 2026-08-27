# ProjectSend API guide

The API lets an external tool act on your installation: list and download files, upload new ones,
share them with clients, and manage client accounts and groups.

Everything here describes **v1**, mounted at `/api/v1`. The machine-readable specification is at
`GET /api/v1/openapi.json` and can be imported straight into Postman, Insomnia or a code generator.

---

## Getting started

1. Sign in and go to **Settings → API tokens**.
2. Create a token, tick only the permissions the tool needs, and give it an expiry.
3. Copy the token immediately — it is shown once and never again. Only a hash is stored.

Then:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Accept: application/json" \
     https://your-install.example.com/api/v1/me
```

`GET /api/v1/me` is the first call worth making. It tells you who the token belongs to, which
abilities it *effectively* has, which edition this installation runs, and which optional modules are
available.

---

## Authentication

Bearer tokens only:

```
Authorization: Bearer 1|psend_xxxxxxxxxxxxxxxxxxxx
```

There is no cookie or session authentication on `/api/*`, and no password-login endpoint. Tokens are
created in the web interface, by a person who has already signed in and confirmed their password.
That is a deliberate limitation: it keeps passwords off the API entirely.

**Staff accounts only.** Client accounts cannot hold a working token in v1.

Three things must hold for a call to succeed, on every request:

| | |
|---|---|
| The token carries the ability | Chosen when the token was created or last edited |
| The owner still holds the permission | Re-checked live — a demoted account's token loses the ability immediately, without anyone revoking it |
| This edition has the feature | Community-only features are unavailable on cloud installs and vice versa |

A token can therefore never do more than the person who created it, and never more than it could on
the day it was minted.

### Expiry and revocation

Every token has an expiry (default 90 days, maximum 365). "Never expires" exists but is off by
default — a token nobody ever rotates is a password nobody ever changes.

Revoke from the settings page, or let a token retire itself:

```bash
curl -X DELETE -H "Authorization: Bearer YOUR_TOKEN" \
     https://your-install.example.com/api/v1/tokens/current
```

That revokes only the calling token. Revoking someone else's is deliberately a web-only action.

---

## Abilities

An ability is one of the installation's permission keys. The token creation screen lists only the
ones you hold *and* that the API can currently act on, so what you see there is the authoritative
list for your account.

| Ability | Unlocks |
|---|---|
| `upload` | list files, upload |
| `edit_files` / `edit_others_files` | read and edit file metadata, share files |
| `delete_files` / `delete_others_files` | delete files |
| `set_file_expiration_date` | set `expires_at` when editing |
| `set_file_categories` | set `categories` when editing |
| `limit_downloads` | set `download_limit` and `download_limit_scope` when editing |
| `upload_public` | set `public` when editing |
| `upload` / `edit_files` / `edit_others_files` | read and write a file's comments |
| `manage_clients` | list clients |
| `create_clients` / `edit_clients` / `delete_clients` | create, read and edit, delete clients; `edit_clients` also removes a client's two-factor authentication |
| `manage_groups` | list groups |

| `moderate_comments` | list what is awaiting approval, and approve it |
| `manage_users` | list staff accounts and the roles you may assign |
| `create_users` / `edit_users` / `delete_users` | create, read and edit, delete staff accounts; `edit_users` also removes an account's two-factor authentication |

There is no ability for *writing* a comment. Who may comment is an installation setting rather than
a per-role permission, so the file abilities are the gate — the same question the web asks, which is
"can you see this file". Removing somebody *else's* comment sits there too, because the same
endpoint also lets an author remove their own within the editing window and that is not moderation;
it additionally requires the token's owner to hold `moderate_comments`, checked live against the
account rather than carried by the token.
| `create_groups` / `edit_groups` / `delete_groups` | create, read and edit (including membership), delete groups |

Where an endpoint accepts several — `edit_files` *or* `edit_others_files` — holding either is enough,
and which one applies to a given file depends on whether you uploaded it.

Every operation in the OpenAPI document names its own requirement.

---

## Responses

Successful responses wrap the payload in `data`:

```json
{ "data": { "id": 12, "name": "Quarterly report" } }
```

List endpoints add cursor pagination:

```json
{ "data": [ … ], "links": { "next": "…?cursor=eyJ…" }, "meta": { … } }
```

### Errors

Errors are [RFC 7807](https://datatracker.ietf.org/doc/html/rfc7807) problem documents, served as
`application/problem+json`:

```json
{
  "type": "validation_failed",
  "title": "The given data was invalid.",
  "status": 422,
  "errors": { "name": ["The name field is required."] }
}
```

`type` is a stable slug you can branch on. `title` and `detail` are prose and may be reworded.

| Status | `type` | Means |
|---|---|---|
| 401 | `unauthenticated` | Missing, malformed, expired or revoked token |
| 403 | `forbidden` | The token, the account, or the edition lacks what this call needs |
| 404 | `not_found` | No such resource, or none you may see |
| 413 | `payload_too_large` | Upload part over the size limit |
| 422 | `validation_failed` | See `errors` |
| 429 | `too_many_requests` | Slow down; honour `Retry-After` |

---

## Rate limits

Limits are per token, so one integration cannot exhaust another's allowance. Every response carries:

```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 118
```

and a `Retry-After` header on 429. Uploads have their own, tighter bucket.

> **Self-hosting behind a proxy?** Set `TRUSTED_PROXIES`. Without it every request appears to come
> from the proxy, so unauthenticated rate limits collapse into one shared bucket and the download IP
> log records the proxy instead of the caller.

---

## Reading a list, and polling for changes

All list endpoints accept `per_page` (capped) and `cursor`. Pass the `links.next` URL back verbatim
to walk forward.

To watch for changes — the usual reason an automation tool calls an API on a timer — pass
`updated_since`:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-install.example.com/api/v1/files?updated_since=2026-08-06T09:00:00Z"
```

With `updated_since` the walk is ordered oldest-first by last-modified time, so new and edited rows
always arrive at the end and paging forward visits every row exactly once. Keep the highest
`updated_at` you have seen and pass it on the next poll.

The boundary is inclusive, so you will occasionally re-see the row exactly on your watermark;
de-duplicate by `id`. That is the safe direction of the trade — excluding it could drop a row that
shares a timestamp with another.

**Polling cannot see deletions.** A deleted row simply stops appearing. If you need to react to
deletions, that is what webhooks will be for; they are not built yet.

---

## Reacting to things that happen

Every list above answers "what is there now". `GET /api/v1/activity` answers "what happened", which
is what an automation tool actually needs — and for two of the most useful events it is the only
place to look.

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-install.example.com/api/v1/activity?action[]=file.assigned"
```

```json
{
  "data": [
    {
      "id": 1284,
      "action": "file.assigned",
      "created_at": "2026-08-25T09:14:02+00:00",
      "actor": { "id": 3, "name": "Dana", "type": "staff" },
      "origin": "ui",
      "subject": { "type": "file", "id": 128, "name": "October invoice" },
      "context": { "target": "Acme Ltd" }
    }
  ]
}
```

**Sharing a file leaves no mark on the file.** It writes an assignment, and the file's own
`updated_at` does not move — so polling `/files?updated_since=` will never show you a share, no
matter how often you ask. The same goes for downloads, which are recorded here and nowhere else.

Repeat `action` for more than one: `?action[]=file.assigned&action[]=file.downloaded`. An action
this installation has never heard of is a `422` rather than being quietly dropped, because ignoring
it would hand back the whole log to a caller who asked for one slice. `subject_type` narrows to one
kind of thing — `file`, `folder`, `user`, `group`, `category`, `role`.

Polling works as it does everywhere else. Entries are never edited, so `updated_since` walks the
moment each was recorded; the two mean the same thing on a log that is only ever appended to.

Needs `view_actions_log`, the same permission the activity screen uses, and the same scoping: a
staff member limited to their assigned clients sees their own library and their own actions, never
the whole installation's.

**No IP addresses.** Some entries record one, and the activity screen shows it. It is left out here
on purpose: handing a client's IP to an automation tool is a privacy question nobody asked to have
answered for them.

**Deletions work here too**, which is the one thing polling a list can never do. A deleted file
stops being returned by `/files` and nothing marks the moment it went; the log records it as an
event like any other, so `?action[]=file.deleted` tells you.

One shape to know for those: a deletion entry has **no subject**. By the time it is written the row
is gone, so what the thing was called is snapshotted into `context.name` instead. Read that rather
than `subject.name` when you are reacting to something being removed.

What this still cannot tell you is anything the log does not record, which is deliberately less than
everything.

---

## Uploading

Two ways, and the right one depends on the file.

### One request

```bash
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
     -F "file=@report.pdf" \
     -F "name=Quarterly report" \
     https://your-install.example.com/api/v1/files
```

Simple, and fine up to this installation's configured maximum upload size. There is no resume: a
dropped connection means starting again.

The stored content type is detected from the bytes, not from what you declare.

### Resumable, in parts

For large files or unreliable connections. Four steps:

1. `POST /uploads` with `filename` and `size` → returns an `uploadId`.
2. For each part, `GET /uploads/{id}/parts/{n}/sign` → returns a short-lived signed URL; `PUT` the
   part's bytes to it. Parts may go in any order, and `GET /uploads/{id}/parts` lists what has
   arrived, so an interrupted upload resumes rather than restarts.
3. `POST /uploads/{id}/complete` → assembles the parts and returns the created file.
4. `DELETE /uploads/{id}` abandons an upload you no longer want.

The size you declare in step 1 is checked again against the assembled bytes at step 3, so it is a
courtesy, not a promise the server trusts.

---

## Sharing a file

Sharing is a file plus a target:

```bash
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"type":"client","id":42}' \
     https://your-install.example.com/api/v1/files/12/assignments
```

`type` is `client` or `group`. This is idempotent — assigning twice changes nothing — so retrying a
request that may already have succeeded is safe.

It also does everything the web interface does: the recipient gets an in-app notification, and the
share email follows on the usual delay.

`DELETE` with the same body revokes the share.

### Files that are new versions of other files

A file can be marked as a new version of an earlier one:

```bash
curl -X PUT -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"previous_file_id":11}' \
     https://your-install.example.com/api/v1/files/12/version
```

**A new version is shared with exactly the people the original is shared with.** It has no
recipients of its own, so assigning it returns `422` and names the file to assign instead — every
version follows that one. Any recipients the file already had are moved onto the original when you
link it, so nobody loses access.

`DELETE /files/12/version` removes the link. The file then keeps a copy of the recipients it was
inheriting, so unlinking does not revoke anything either.

Every file carries `is_revision`, `sharing_root_id`, `previous_version` and `next_version`. The last
two are narrowed to what your token may see: a counterpart outside your reach reads as `null`.

---

## Staff accounts

`/users` manages the people who administer the installation, and the role assigned to each of them.
Clients are a different population with their own `/clients` endpoints and never appear here.

Available on every edition. A managed installation may cap how many staff accounts exist — the
operator supplies the number, and creating one past it is refused with a validation error naming the
limit — but who fills those seats, and which role each of them holds, is the installation's own
decision and always was.

Two abilities are needed for each call: `manage_users` to reach the area at all, then the one for the
action (`create_users`, `edit_users`, `delete_users`). That mirrors the web UI, where the whole
section sits behind `manage_users` and each button behind its own key.

### Two rules that will refuse you

**You cannot hand out authority you do not hold.** `role_id` must name a role you could grant
yourself: a caller who is not an administrator may not create one, nor assign any role carrying a
permission they lack, nor touch an account whose role already outranks them (that one is a `403`).
`GET /roles` lists exactly what is available to you, so the safe move is to read it rather than
guess an id.

**The installation always keeps an active administrator.** Demoting, deactivating or deleting the
last one is a `422`. So is deactivating or deleting yourself, from either surface.

### Changing a role

The assigned role is a field on the account, so `PATCH /users/{user}` with `role_id` is the whole
operation. `assigned_clients` only means anything for a `client_scoped` role; moving to any other
role clears it, whether or not you mention it.

Creating and deleting *roles themselves* is deliberately not on the API: a role is a
security boundary, and changing one is a deliberate act performed in the UI.

## Deleting a client

If the client owns files or folders, you must say what happens to them:

```bash
curl -X DELETE -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"content_action":"reassign","reassign_to_id":3}' \
     https://your-install.example.com/api/v1/clients/42
```

`content_action` is `cascade_delete` or `reassign`. There is no default: one would silently destroy
the files, the other would silently hand them to somebody else. `GET /clients/{id}` reports the
counts so you can decide first.

A client owning nothing deletes with no body at all.

---

## Unlocking an account whose second factor is lost

Two-factor authentication has a failure mode that nothing else in the API does: when the
authenticator app and the recovery codes are both gone, the account cannot be opened by its holder
*or* by anybody else. `DELETE` the second factor to put the account back to password-only sign-in:

```bash
curl -X DELETE -H "Authorization: Bearer YOUR_TOKEN" \
     https://your-install.example.com/api/v1/clients/42/two-factor

curl -X DELETE -H "Authorization: Bearer YOUR_TOKEN" \
     https://your-install.example.com/api/v1/users/7/two-factor
```

`edit_clients` and `edit_users` respectively — this changes how an account signs in, it does not
remove one, so it is not the `delete_*` key. Both answer `204` whether or not a second factor was
actually in force, and the staff route additionally refuses (`403`) an account whose role outranks
the caller's, exactly like `PATCH` does.

Two things always happen, and neither is optional: the account holder is emailed that it happened,
and the call is recorded in the activity log against the token's owner. If the installation enforces
two-factor authentication for that population, the account is asked to enrol again on its next
sign-in — this un-sticks an account, it does not exempt one.

`two_factor_enabled` on `GET /clients`, `GET /clients/{client}`, `GET /users` and
`GET /users/{user}` tells you whether there is anything to remove.

---

## Retries and duplicate requests

Assignments and group membership are idempotent. **Creating a file or a client is not** — a retried
`POST` that actually succeeded the first time creates a second one. Until idempotency keys exist,
check before retrying a create you are unsure about.

---

## Module endpoints

Some installations carry optional modules that add their own endpoints under
`/api/v1/modules/{module}/…`. `GET /api/v1/me` lists which are present, so a tool can adapt rather
than guess.

Everything documented above — authentication, abilities, error format, rate limits — applies to them
unchanged; the core supplies all of it rather than each module restating it.

### For module authors

Register from your package's service provider, listening by string class name so your package stays
buildable without the host application present:

```php
Event::listen('App\Modules\Api\Events\RegisteringApiModules', function ($event): void {
    $event->register(
        slug: 'branding',
        routes: __DIR__.'/api-routes.php',
        capability: 'branding.customize',
    );
});
```

The core supplies the prefix, the route-name namespace, the authentication stack and the capability
gate. Your routes declare paths, controllers, and a `token-can:` middleware naming permissions from
the core vocabulary. A slug must be unique and lowercase; a clash throws at boot rather than
silently shadowing.

Module endpoints are deliberately absent from the document above — `OpenApiContractTest` skips
`api/v1/modules/*` — so each package documents its own surface in its own repository. The
`branding` module's endpoints are in `packages/cloud-modules/docs/api.md`.

---

## Versioning

`/api/v1` is a frozen contract. Additive changes only: new endpoints, new optional parameters, new
response fields. Removing or renaming a field, tightening validation, or changing a status code is
breaking and would arrive as `/api/v2`. Anything scheduled for removal ships `Deprecation` and
`Sunset` headers first.

`docs/api-changelog.md` records every change.

---

## Not in v1

Recorded so they read as decisions rather than gaps:

- **Client-user tokens.** Only staff accounts can hold one.
- **Password login.** Tokens come from the web interface.
- **Webhooks.** Poll instead; see above.
- **Idempotency keys.** See "Retries" above.
- **Share links, notifications, thumbnails, settings.**
- **Creating and deleting roles.** `GET /roles` reads them and `role_id` assigns one; defining a
  role's permission set stays in the UI.

---

## Keeping this document true

Anyone shipping a feature or a fix — API-related or not — should run through
asking whether the change needs an API change at all.

For anyone adding an endpoint:

1. Route in `routes/api.php` inside the auth group, with a `token-can:` naming the same permission
   its `routes/web.php` twin uses.
2. A `JsonResource` with an explicit field allowlist — never `$model->toArray()`.
3. Authorization through the domain's existing policy or scope. The token ability is an *additional*
   gate, never a replacement.
4. A feature test, including the negative case for a client-scoped staff token.
5. Rerun `php artisan scramble:export`, add a row above if it changes the ability table, and add a
   changelog entry.

The token creation screen needs no update — it derives its list by scanning routes for `token-can:`.

Note that **docblocks on controller methods under an `Api` namespace are published as the reference**.
Write them for the reader; keep implementation notes in `//` comments inside the method.
