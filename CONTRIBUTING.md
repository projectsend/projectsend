# Contributing to ProjectSend

ProjectSend has been maintained since 2011 and is used by freelancers, agencies, NGOs, schools
and government offices around the world. Thanks for helping keep it good.

## Ways to contribute

- **Report a bug** — open an [issue](https://github.com/projectsend/projectsend/issues) with how you
  run ProjectSend (Docker or manual install), the version, and steps to reproduce.
- **Report a security vulnerability** — do **not** open a public issue. Report it privately via
  [GitHub security advisories](https://github.com/projectsend/projectsend/security/advisories/new).
- **Suggest a feature** — start a [discussion](https://github.com/projectsend/projectsend/discussions)
  first. It saves everyone time to agree on the shape of a thing before code is written.
- **Translate** — translations live in this repo as JSON files under [lang/](lang/). Fixing or
  completing a locale is a pull request like any other.
- **Write code** — read the rest of this document first.

## Before you write code

For anything beyond a small fix, open an issue or discussion first. Large pull requests that
arrive without prior discussion are hard to review and often need rework.

## Setting up for development

A clone is a development copy, not an installation: `vendor/` and `public/build/` are deliberately
not in git, so nothing runs until Composer and npm have filled them. That is what these steps do,
and it is why the published Docker image — which ships both, already built — is what the README
sends users to instead. From a fresh clone:

```sh
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
npm install && npm run build          # or `npm run dev` for hot reload
```

The app is then at `http://localhost:8090`. Until a staff user exists every request redirects to the
first-run setup screen, which creates the initial administrator. For unattended provisioning you can
instead set `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` before starting (the container
creates the account on boot, idempotently), or run
`docker compose exec app php artisan projectsend:admin` at any time.

A few things worth knowing:

- Self-hosted installs run the community edition — `PROJECTSEND_EDITION=community` in `.env`.
- `APP_PORT` / `ADMINER_PORT` / `DB_PORT_FORWARD` override the ports if they clash with something
  you already run.
- Adminer, a database GUI, is available for development with
  `docker compose --profile dev up -d adminer`.
- On later boots the container migrates automatically. The manual `migrate` above is only needed on
  the first install, before `vendor/` exists.
- Until `composer install` has run, the `worker` and `scheduler` containers have no application to
  run and exit with a message saying so; they pick themselves up once it has. If a page answers
  "ProjectSend is not installed yet" or "not built yet", it is naming the step that is missing.
- Two checkouts of this repository share one Compose project name, so `docker compose up` in the
  second one takes over the first one's containers. Pass `-p some-other-name` when you want them
  side by side.

**Staff and clients are different things.** Staff — "system users" — administer the installation and
upload files. Clients are the people files are shared with. There is no staff registration page:
staff are created by an administrator. Client self-registration is optional and can require
approval.

You don't need anything private to work on ProjectSend. The `community-modules` companion package
is public and Composer fetches it for you; the paid Cloud package is not required by the core.

### Before you open a pull request

Run the same checks CI runs, and make sure they pass:

```sh
docker compose exec app ./vendor/bin/pest        # tests
docker compose exec app ./vendor/bin/phpstan     # static analysis (level 8)
docker compose exec app ./vendor/bin/pint        # code style
npm run types                                    # TypeScript
npm run lint                                     # ESLint
```

If you touched the frontend, `npm run build` must also succeed.

## Pull requests

1. Fork the repo and branch off `main`.
2. One logical change per pull request.
3. Run the checks above and keep the existing code style.
4. Write a clear description: what changes, why, and how you tested it.
5. If your change affects the database schema, include the migration.
6. If your change affects user-facing strings, use the existing translation helpers
   (`t()` / `__()`) — never hardcode English.

## Licensing and the Contributor License Agreement

ProjectSend is free software licensed under the **GNU General Public License v2, or (at your
option) any later version**. See [LICENSE](LICENSE). That isn't changing.

Before your first pull request can be merged, you'll need to sign a Contributor License Agreement.
A bot will comment on your pull request with a link; signing takes one click and is recorded
against your GitHub account. You only do this once, and it covers everything you've contributed
to ProjectSend — past and future.

- Contributing on your own behalf → [Individual CLA](CLA-INDIVIDUAL.md)
- Contributing as part of your job, or on behalf of a company → [Entity CLA](CLA-ENTITY.md)

### Why a CLA?

CLAs are contentious in open source and the concern behind that is legitimate, so we'd rather
explain this properly than bury it.

**You keep the copyright in your contribution.** The CLA is a license, not a transfer of
ownership. You can continue to use, relicense, or sell your own code however you want. Nothing
you grant here takes anything away from you.

**It lets the project fund itself.** The CLA gives the maintainers the right to also distribute
the code under other terms — specifically, a commercial license for organizations whose legal
departments can't accept the GPL, and who want to embed ProjectSend in a product of their own.
That revenue, along with managed hosting at [projectsend.cloud](https://projectsend.cloud), is
what pays for continued development of the free, self-hosted version everyone uses. See
[LICENSING.md](LICENSING.md).

**It keeps our options open.** Without a CLA, changing ProjectSend's license in the future —
even to a newer version of the GPL — would require tracking down every contributor from the past
fifteen years and getting individual permission. We're not planning a license change. But we'd
rather not be permanently unable to make one.

### What we commit to in return

- **ProjectSend's core stays under an OSI-approved open source license.** Not source-available,
  not BSL, not SSPL.
- **Nothing that is free today will ever move behind a paid tier.**
- **The self-hosted version will never be crippleware.** No artificial caps on users, clients,
  files or storage designed to push you toward the hosted plan.
- **Every release ever published stays available under the license it was published under.**

**Being straight with you about what we do sell:** we operate ProjectSend Cloud, and it has
features the self-hosted core doesn't. [LICENSING.md](LICENSING.md) sets out exactly where that
line is and what stays in the core permanently. If you'd rather your work not go into a project
that funds itself this way, that's a fair call, and we'd rather you know before you contribute
than after.

If you're not comfortable signing, that's a reasonable position. Open an issue describing the bug
or the design instead — that's a real contribution too, and it doesn't require any agreement.

### A note on AI-assisted code

If you used an AI coding assistant, that's fine, but you're still making the representations in
the CLA: that the contribution is your original work and that you have the right to submit it.
Review what you submit and understand it well enough to maintain it.

## Third-party code

If your pull request includes code you didn't write, say so explicitly in the description and
name the source and its license. Don't quietly paste in a snippet from Stack Overflow or another
project — it creates real problems for everyone downstream.

## How the code is organised

A modular monolith under `app/Modules/` — Identity, Clients, Groups, Files, Sharing, Storage,
Notifications, Comments, Audit, Platform and friends. Modules talk to each other through public
service classes and events rather than reaching into each other's internals.

Two conventions are worth knowing before your first pull request:

- **File bytes never travel through PHP.** Downloads are served by the web server itself
  (`X-Accel-Redirect`) or straight from object storage, so a large download does not occupy a PHP
  worker.
- **Behaviour that differs between installations flows through one registry**
  (`app/Modules/Platform/Capabilities/`) rather than scattered conditionals. Enforcement derives
  from it in three places: route middleware, the UI (via the `useCapability()` hook) and the API,
  which answers `403 capability_unavailable`.

## Translations

No user-facing string is hardcoded. **English text is the translation key** — `__('Save changes')`
in PHP, `t('Save changes')` in React — so English needs no catalogue and a missing translation
falls back to English rather than breaking. Every other language is one file: `lang/{locale}.json`.
Framework strings live in `lang/{locale}/*.php` and come from [laravel-lang](https://laravel-lang.com).

**Write your feature in English, and leave the translating to a separate pass.** Shipping should
never wait on a language nobody in the room speaks, and the cost of a lagging translation is low
while the cost of a rushed one is not. Translations are brought up to date on a cadence, and always
before a release.

Adding a language is adding a file: a locale is installed exactly when `lang/{locale}.json` exists.
Corrections from native speakers are especially welcome — most of the current catalogues have not
yet been read by one.

## Questions

- [Discussions](https://github.com/projectsend/projectsend/discussions)
- <contact@projectsend.org>
