# Moving from ProjectSend Legacy (v1)

This guide takes an existing **ProjectSend Legacy** install — the r2098-era PHP app, the one whose
database tables are named `tbl_users`, `tbl_files` and so on — and brings its accounts, clients,
groups, categories, folders, files and history into a new **ProjectSend** install.

It is done by a separate tool, [`projectsend/v1-migration-tool`](https://github.com/projectsend/v1-migration-tool),
which you install into your new install when you want it and remove when you are done. It is
deliberately not built in: migrating happens once, if ever, and the engine it needs — arbitrary
database connections and direct writes across the whole schema — is not something every install
should carry idle.

**Your old install is never written to.** Not a marker row, not a lock file, not a maintenance
flag. It keeps running exactly as it did while you try the migration, look at the result, undo it,
adjust and try again. Nothing about this is a one-way door until you decide it is.

The whole thing takes about twenty minutes on a small install. Large ones take longer to plan than
to run — see [Large installs](#large-installs).

---

## Read this part first

Three things decide whether this will go smoothly, and all three are easier to deal with now than
halfway through.

### Your clients will sign in with their email address

Legacy signs in with a **username**. ProjectSend signs in with an **email address**. Every client's
login therefore changes.

Their **passwords do not** — the hashes come across as they are, so nobody gets a reset email and
nobody is forced to pick a new password. Only the thing they type in the first box changes.

The exception is an account whose stored hash ProjectSend cannot read: a blank password, or one
left by a Legacy install old enough to predate the hashing it uses now. Those accounts could not
sign into Legacy either, and they cannot be repaired — the password itself is long gone. The tool
names them before the run and counts them in the report; those people use **Forgot password** once,
after which their account behaves like any other.

Legacy also did not require email addresses to be unique, because it never signed in with them. The
tool refuses to start until duplicates, blanks and invalid addresses are fixed in your Legacy
install, and it names the accounts. That refusal is on purpose: picking a winner between two
accounts sharing an address is deciding which of your clients loses access, and that is not a
decision a tool should make quietly.

### The new install has to be empty

Set up, but not used. Create the administrator, then migrate — don't upload files or add clients
first. There are no merge semantics, and "empty" is exactly what makes the undo trustworthy.

### A background worker has to be running

A real import outlives any web request. The screen queues a job and polls; without a worker the run
sits at "pending" forever. In Docker the worker container is already running. On a manual install,
see [the worker section of INSTALL.md](INSTALL.md#the-background-worker).

---

## What comes across

Everything in this list, in this order — it follows the dependency graph, so accounts exist before
the things that point at them:

| | |
|---|---|
| **Settings** | The ~43 Legacy options that have a ProjectSend equivalent, plus your mail/SMTP configuration |
| **CAPTCHA keys** | Every reCAPTCHA and Turnstile key pair you had, and which service was switched on. They arrive encrypted, where Legacy kept them in plain text |
| **Roles and permissions** | Including the per-role permission sets |
| **Accounts** | Staff and clients, with their password hashes, disk quotas and custom fields |
| **Groups** | Members and pending membership requests |
| **Categories** | Flattened — see the note below |
| **Folders** | The tree, and who each folder is assigned to |
| **Files** | The rows *and* the bytes, with their descriptions, expiry dates, public flags and download limits |
| **Assignments** | Which clients and groups each file was shared with, and its categories |
| **History** | Every download, and the activity log |

**Categories are flattened.** Legacy nested them; ProjectSend does not. Every category that had a
parent takes its whole ancestry as its name — `Clients / Acme / Invoices` — and a root category
keeps its bare name. Nothing merges and nothing is dropped, so no file loses a tag.

**Download counts come with the downloads.** If a file had a download limit of 3 in Legacy and had
already been taken twice, it arrives here with one download left, not three.

### What does not, and why

The tool reports each of these before it starts and names every affected row. It never guesses.

| | |
|---|---|
| **Files encrypted at rest** | ProjectSend has no at-rest encryption, and the per-file keys are wrapped by a master key that exists only in Legacy's `sys.config.php` |
| **Files on S3, GCS or Azure** | Legacy configured external storage per file; ProjectSend has one bucket for everything |
| **Hidden assignments** | ProjectSend has no hidden state, and creating the assignment anyway would show people files that were hidden from them |
| **Two-factor secrets** | Encrypted with Legacy's key. Those users re-enrol |
| **Email templates** | The placeholder vocabulary is different, so importing them verbatim produces emails with broken tokens — worse than starting from the defaults |
| **Legacy options with no equivalent** | ProjectSend has ~43 settings where Legacy had ~180 |
| **Thumbnails** | A derived cache. ProjectSend regenerates them on first request |

---

## Step 1 — Install the tool

On the **new** install:

```sh
composer require projectsend/v1-migration-tool
php artisan migrate    # creates the tool's two tables
npm run build          # so its screen enters the frontend bundle
```

In Docker, prefix each with `docker compose exec app` (except `npm run build`, which runs on the
host).

> **If `composer require` answers `Could not find a matching version of package
> projectsend/v1-migration-tool`,** your install predates the entry that tells Composer where the
> tool lives. Add it to the `repositories` array in your `composer.json` — top level, alongside
> `require`, not inside it — and run the command again:
>
> ```json
> "repositories": [
>     { "type": "vcs", "url": "https://github.com/projectsend/v1-migration-tool" }
> ]
> ```
>
> The repository is public, so Composer needs no credentials or token for it.

Then open **`/system/migrate`** on your new install, signed in as a staff user with the *Edit
settings* permission. There is no sidebar link — a one-time tool does not earn a permanent slot in
the navigation of an install that will use it once.

Everything below can also be done entirely from the command line; the equivalent commands are
listed at each step.

---

## Step 2 — Pick your route

| Your situation | Use |
|---|---|
| Legacy and ProjectSend are on the **same machine** | [**Direct**](#step-3a--direct-same-machine) |
| Legacy is on **another server**, or on hosting you cannot reach from the new box | [**Bundle**](#step-3b--bundle-different-machines) |

Direct is faster and simpler, and on a single filesystem it does not copy your files at all — it
hardlinks them, so 400 GB migrates in seconds and both installs point at the same bytes until you
decide otherwise. Use it if you can.

---

## Step 3a — Direct (same machine)

Point the tool at your Legacy install directory. It reads the database credentials out of
`includes/sys.config.php` itself, so there is usually nothing to type but the path:

```sh
php artisan projectsend:migrate:preflight --v1-path=/var/www/projectsend-legacy
```

On the screen, choose **Direct**, enter the same path, and pick how to move file bytes:

| `--files=` | What it does |
|---|---|
| `hardlink` | A second directory entry for the same bytes. Instant, costs no disk, leaves Legacy completely intact. Only works within one filesystem, and falls back to copying across a boundary rather than failing |
| `copy` | **Default.** The only strategy that is always correct, and it checksums what it writes as it writes it |
| `move` | Takes the bytes out of Legacy. Fast and frees disk — and **cannot be undone** |
| `defer` | Writes no bytes at all. For importing the database now and moving half a terabyte overnight |

If ProjectSend runs in Docker, the Legacy directory has to be visible **inside the app container**
— bind-mount it there, and use the container's path, not the host's.

---

## Step 3b — Bundle (different machines)

Run one dependency-free PHP file on the Legacy box; it produces a portable directory you bring
over. Download it from `/system/migrate` (there is a link on the screen) or take it from the
package at `bin/projectsend-v1-export.php`.

On the **Legacy** server:

```sh
php projectsend-v1-export.php --preflight                 # look before you leap
php projectsend-v1-export.php --out=/tmp/ps-export        # write the bundle
```

It searches upwards from itself for `includes/sys.config.php`; pass `--install=/var/www/projectsend`
if you put it somewhere else. It never writes to the Legacy install.

**In the `linuxserver/projectsend` container**, where the app lives at `/app/www/public`, uploads
are a symlink to `/data/projectsend` and the config is a symlink to
`/config/projectsend/sys.config.php`:

```sh
docker exec projectsend php /app/www/public/projectsend-v1-export.php --out=/data/ps-export
```

That lands the bundle on the host's own `/data` bind mount. Both symlinks are followed; nothing
else is needed.

### Bundles and file bytes

By default the exporter records an **inventory** of every file — path and size — without moving a
byte. That is the only sane choice above a few gigabytes, and it means the bundle is small enough
to copy anywhere.

Move the files separately, at your own pace, into a `files/` directory inside the bundle:

```sh
rsync -a legacy-server:/var/www/projectsend/upload/files/ /tmp/ps-export/files/
```

The import finds them there. If you would rather have everything in one object, export with
`--files=copy` instead — convenient, and it doubles the disk you need on the Legacy box.

Then copy the bundle to the new server, choose **Bundle** on the screen and give it the path (again,
the path *inside* the app container if you are using Docker):

```sh
php artisan projectsend:migrate:preflight --bundle=/srv/ps-export
```

---

## Step 4 — Read the preflight

Preflight changes nothing. It reports what it found — how many accounts, files, downloads and
activity rows — and separates its findings into three kinds:

- **Blockers.** Duplicate, blank or invalid email addresses; a schema mismatch. The run will not
  start until these are fixed.
- **Acknowledgements.** Things with no equivalent here, from the list above. You confirm you have
  read them; the run then skips those rows and lists them in its report.
- **Notes.** For information — file rows whose bytes are already gone from the Legacy disk, for
  instance, which is common on old installs.

Fix the blockers **in your Legacy install** (that is the one place a duplicate email can be
resolved by a human who knows which client is which), then run preflight again.

---

## Step 5 — Import

Press the button, or:

```sh
php artisan projectsend:migrate:import --v1-path=/var/www/projectsend-legacy --files=hardlink
php artisan projectsend:migrate:import --bundle=/srv/ps-export
```

Add `--accept-skips` to acknowledge the findings from step 4 non-interactively.

The screen shows progress per phase and keeps working if you close the tab — the run is a database
row, not a browser session. If the worker is restarted mid-import it picks up where it left off;
chunks are checkpointed, and rows already written are never written twice.

History is imported last on purpose. It is the largest part by an order of magnitude, and it is the
one part you could reasonably abandon halfway with everything that matters already in.

---

## Step 6 — Check it

```sh
php artisan projectsend:migrate:verify
```

Every entity is checked as an equation: imported + deliberately skipped = what Legacy had. A number
that does not add up is reported as a bug in the tool, not as a warning about your data. It also
checks that imported files actually have bytes at the path they claim — the check that catches a
transfer failing quietly, which is the difference between a migration and a database full of broken
download links.

Then look at it yourself. Sign in, open the file library, open a client's portal, download
something.

---

## Undoing it

```sh
php artisan projectsend:migrate:reset
```

Every run records exactly what it created, so this puts the install back to how it was — including
anything that existed before the run, which is left alone. Try the migration, look at the result,
adjust, run it again.

The one exception is `--files=move`, which took the bytes out of your Legacy install. Deleting the
rows afterwards leaves them nowhere. The command tells you this before it does anything, not after.

---

## Cutting over

Once you are satisfied:

1. **Tell your clients their login changed** — email address instead of username, same password. The
   run report gives you the list of who and what, including the handful (if any) whose password
   could not be read and who need **Forgot password** once.
2. Keep the Legacy install running read-only for a while if you can. Nothing was taken from it
   (unless you used `--files=move`), so it stays a working reference.
3. Remove the tool:

```sh
php artisan projectsend:migrate:reset --drop   # also drops the tool's own tables
composer remove projectsend/v1-migration-tool
```

`--drop` throws away the Legacy → ProjectSend id map. **Keep it** if you may ever want to redirect
old `download.php?id=…` links, because it is the only thing that can resolve them. Removing the
package without `--drop` leaves the two tables behind harmlessly.

Leave the `repositories` entry in `composer.json` alone. It ships with ProjectSend, it costs
nothing while nothing requires the package, and removing it is what makes a second attempt fail to
find the tool.

---

## Large installs

The awkward cases are not the complicated ones, they are the big ones: 200,000 small files, or
400 GB of one client's raw footage, or five million rows of download history. Three options exist
for exactly that.

| | |
|---|---|
| `--files=hardlink` | The 400 GB case. No bytes are copied; both installs point at the same inodes. Same filesystem only |
| `--files=defer` | Import the database tonight, `rsync` the files over the next two days, attach them afterwards |
| `--history=none` | Skip downloads and the activity log entirely. Everything that matters is still imported; you lose the counts and the log |

`--history=none` has one visible consequence worth knowing: a file that carried a download limit
arrives with a **full** allowance, because the downloads that had already been spent against it are
what the count is derived from.

Two practical notes for big runs:

- Give the queue worker room. One worker is enough; it is one long job, not many.
- On a bundle, the file inventory (`--files=inventory`, the default) is what keeps the export
  itself small enough to move.

---

## When something goes wrong

**"This install already has content."** The tool imports into a fresh ProjectSend only. Either use a
genuinely new install, or `php artisan projectsend:migrate:reset` if the content came from a
previous run of the tool itself.

**The run sits at "pending" and nothing happens.** No queue worker. See
[INSTALL.md](INSTALL.md#the-background-worker).

**"No manifest.json in … — this is not a ProjectSend v1 export bundle."** The path points at the
wrong directory, or at the parent of the bundle rather than the bundle.

**A schema mismatch at preflight.** The tool declares every table and column it writes and checks
them before it starts, so this stops the run rather than failing halfway through 200,000 rows.
Update ProjectSend to a version that has what the tool expects — or update the tool.

**Files import but download as 404.** The bytes did not move. Run
`php artisan projectsend:migrate:verify`, which checks precisely this. A common cause in Docker is
a Legacy directory that is visible on the host but not inside the app container.

**Uploads and database seem to vanish after a Docker restart.** That is not the migration — read
[DOCKER.md](DOCKER.md) before putting real files into any Docker install.
