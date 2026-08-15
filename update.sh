#!/usr/bin/env bash
#
# Updates a ProjectSend installation that runs from files on a server —
# the release-zip path in INSTALL.md, not the Docker one.
#
# It replaces a sequence of nine commands, of which one (reloading PHP-FPM)
# is silently fatal to skip: with OPcache set the way production guides
# recommend, PHP never re-reads a file it has already compiled, so the
# database ends up updated while every visitor is still served the old
# code — and `php artisan` reports the new version throughout.
#
# What it will not do is decide anything for you. It asks before checking
# GitHub, asks before downloading, verifies the checksum of what it
# downloaded, and asks whether you have a backup before it touches a
# single file. The application itself still never downloads or applies
# anything: this runs when you run it.
#
# Usage:
#   sudo ./update.sh                      # ask what is available, then apply it
#   sudo ./update.sh --zip ~/ps-2.1.0.zip # apply a zip you already have
#   ./update.sh --check                   # only report; changes nothing, needs no root
#
set -euo pipefail

REPO_API="https://api.github.com/repos/projectsend/projectsend/releases/latest"
RELEASES_PAGE="https://github.com/projectsend/projectsend/releases"
DISCORD_URL="https://discord.gg/VT9n6cyvXT"

ZIP=""
CHECK_ONLY=0
ASSUME_YES=0
FORCE=0
BACKUP=0
HAVE_BACKUP=0
NO_RESTART=0
KEEP_DOWNLOAD=0
WEB_USER=""
FPM_SERVICE=""
WORKER_SERVICE=""
BACKUP_DIR="/var/backups/projectsend"
DOWNLOAD_DIR=""

usage() {
    cat <<'USAGE'
Usage: sudo ./update.sh [options]

  With no options it asks whether to check GitHub for a newer release,
  whether to download it, and whether you have a backup — then applies it.

  --zip <path>        Apply this release zip instead of asking about a
                      download. You fetch it; this never downloads a file
                      you did not ask for.
  --check             Report the installed and latest versions and stop.
                      Changes nothing and does not need root.
  --backup            Dump the database before applying anything.
  --backup-dir <dir>  Where dumps go. Default: /var/backups/projectsend
  --i-have-a-backup   Skip the backup question (for unattended runs).
  --force             Apply a zip older than what is installed, or one
                      whose version cannot be read. Migrations do not roll
                      back; this is not a casual flag.
  --user <name>       The user the web server runs as. Default: whoever
                      owns public/index.php.
  --php-fpm <unit>    PHP-FPM service to reload. Default: detected.
  --worker <unit>     Queue worker service to restart. Default:
                      projectsend-worker.service, if it exists.
  --no-restart        Do not touch systemd. You are then responsible for
                      reloading PHP-FPM; until you do, ProjectSend shows
                      your staff a banner saying so.
  --download-dir <d>  Where a downloaded zip goes. Default: a temp dir.
  --keep-download     Keep the downloaded zip after a successful update.
  -y, --yes           Answer yes to every question. Requires --backup or
                      --i-have-a-backup, so an unattended run cannot
                      quietly skip both.
  -h, --help          This.
USAGE
}

say()  { printf '==> %s\n' "$*"; }
note() { printf '    %s\n' "$*"; }
warn() { printf '\033[33m    %s\033[0m\n' "$*" >&2; }
fail() { printf '\033[31m%s\033[0m\n' "$*" >&2; exit 1; }

# Prompts read from the terminal rather than stdin: this script may be
# piped or run from a wrapper, and a question nobody can answer would
# otherwise be answered by whatever happened to be on stdin.
ask() {
    local prompt="$1" default="$2" reply

    # --yes means yes to everything, not "take the default" — the two
    # questions whose default is No are precisely the ones an unattended
    # run still has to clear. That is why --yes is only accepted alongside
    # --backup or --i-have-a-backup.
    if [[ "$ASSUME_YES" == "1" ]]; then
        printf '%s y\n' "$prompt"

        return 0
    fi

    if [[ ! -r /dev/tty ]]; then
        fail "No terminal to ask '$prompt'. Pass --yes (and --backup or --i-have-a-backup) for an unattended run."
    fi

    read -r -p "$prompt " reply < /dev/tty || true
    reply="${reply:-$default}"

    [[ "${reply,,}" == "y" || "${reply,,}" == "yes" ]]
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --zip)              ZIP="${2:-}"; shift 2 ;;
            --check)            CHECK_ONLY=1; shift ;;
            --backup)           BACKUP=1; shift ;;
            --backup-dir)       BACKUP_DIR="${2:-}"; shift 2 ;;
            --i-have-a-backup)  HAVE_BACKUP=1; shift ;;
            --force)            FORCE=1; shift ;;
            --user)             WEB_USER="${2:-}"; shift 2 ;;
            --php-fpm)          FPM_SERVICE="${2:-}"; shift 2 ;;
            --worker)           WORKER_SERVICE="${2:-}"; shift 2 ;;
            --no-restart)       NO_RESTART=1; shift ;;
            --download-dir)     DOWNLOAD_DIR="${2:-}"; shift 2 ;;
            --keep-download)    KEEP_DOWNLOAD=1; shift ;;
            -y|--yes)           ASSUME_YES=1; shift ;;
            -h|--help)          usage; exit 0 ;;
            *) usage >&2; echo >&2; echo "Unknown option: $1" >&2; exit 2 ;;
        esac
    done

    if [[ -n "$ZIP" && "$ZIP" =~ ^https?:// ]]; then
        usage >&2
        echo >&2
        echo "--zip takes a file, not a URL. Run without --zip and answer yes to the download question, or fetch it yourself first." >&2
        exit 2
    fi

    if [[ "$ASSUME_YES" == "1" && "$BACKUP" == "0" && "$HAVE_BACKUP" == "0" && "$CHECK_ONLY" == "0" ]]; then
        fail "--yes needs either --backup (take one now) or --i-have-a-backup (you already did). An unattended update must not skip both."
    fi
}

# The version literal, read without booting PHP: config/projectsend.php
# references an application enum, so it cannot simply be required.
#
# `| head -1` is deliberately absent from these pipelines. Under
# `set -o pipefail` head closes the pipe as soon as it has its line, the
# process feeding it dies of SIGPIPE, and the whole command substitution
# then reports failure — intermittently, depending on whether the producer
# finished first. Taking the first line in bash afterwards has no such race.
first_line() {
    printf '%s' "${1%%$'\n'*}"
}

version_in_file() {
    first_line "$(sed -n "s/^ *'version' *=> *'\([^']*\)'.*/\1/p" "$1" || true)"
}

version_in_zip() {
    first_line "$(unzip -p "$1" config/projectsend.php 2>/dev/null | sed -n "s/^ *'version' *=> *'\([^']*\)'.*/\1/p" || true)"
}

# Compares the numeric cores only. GNU sort -V puts 2.1.0-rc.1 *after*
# 2.1.0, which is backwards for SemVer, so a prerelease is never compared
# here — the caller refuses that case instead.
version_newer() {
    local a="${1%%-*}" b="${2%%-*}"
    [[ "$a" != "$b" ]] && [[ "$(printf '%s\n%s\n' "$a" "$b" | sort -V | tail -1)" == "$a" ]]
}

in_container() {
    [[ -f /.dockerenv || -f /run/.containerenv ]]
}

resolve_install_dir() {
    # After the re-exec below this script no longer lives in the install,
    # so the directory is passed through the environment instead.
    if [[ -n "${PROJECTSEND_UPDATE_DIR:-}" ]]; then
        INSTALL_DIR="$PROJECTSEND_UPDATE_DIR"
    else
        INSTALL_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
    fi

    for required in artisan public/index.php config/projectsend.php; do
        [[ -e "$INSTALL_DIR/$required" ]] \
            || fail "$INSTALL_DIR does not look like a ProjectSend installation ($required is missing). Run this from the install directory."
    done
}

# Runs before anything can overwrite this file. The release zip contains
# update.sh, step "Replace the files" writes it, and bash reads its own
# script lazily by byte offset — it would carry on executing whatever
# happened to be at that offset in the new file.
reexec_from_copy() {
    [[ "${PROJECTSEND_UPDATE_REEXEC:-}" == "1" ]] && return 0

    local self
    # Template ends in X's with no suffix: busybox mktemp rejects
    # anything after them, and the official image is Alpine.
    self="$(mktemp "${TMPDIR:-/tmp}/projectsend-update-XXXXXX")"
    cp -- "${BASH_SOURCE[0]}" "$self"

    export PROJECTSEND_UPDATE_REEXEC=1 PROJECTSEND_UPDATE_DIR="$INSTALL_DIR" PROJECTSEND_UPDATE_SELF="$self"
    exec bash "$self" "$@"
}

latest_release() {
    command -v curl >/dev/null 2>&1 || { warn "curl is not installed, so this cannot check for a release."; return 1; }

    local body
    body="$(curl -fsS --max-time 10 -H 'User-Agent: ProjectSend' "$REPO_API" 2>/dev/null)" || {
        warn "Could not reach GitHub. Look at $RELEASES_PAGE yourself, then re-run with --zip."
        return 1
    }

    LATEST_VERSION="$(first_line "$(printf '%s' "$body" | sed -n 's/.*"tag_name" *: *"v\{0,1\}\([^"]*\)".*/\1/p' || true)")"
    LATEST_URL="$(first_line "$(printf '%s' "$body" | tr ',' '\n' | sed -n 's/.*"browser_download_url" *: *"\([^"]*projectsend-[0-9][^"]*\.zip\)".*/\1/p' || true)")"

    # v1's tags are still an r-number scheme on that repository, and
    # comparing 2.0.1 with r2098 is meaningless — the same guard
    # CheckForUpdatesCommand applies.
    if [[ ! "$LATEST_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+ ]]; then
        warn "The latest tag ($LATEST_VERSION) is not a version this can compare. See $RELEASES_PAGE."
        return 1
    fi

    return 0
}

download_release() {
    local target="$1"

    say "Downloading $(basename "$LATEST_URL")"
    curl -fL --progress-bar -o "$target" "$LATEST_URL" || fail "The download failed. Nothing has been changed."

    # Published beside the zip by the release process. Its absence is worth
    # saying out loud rather than passing over quietly.
    # -L, like the download above: GitHub redirects release assets to
    # object storage, and without it curl cheerfully saves the redirect
    # body and reports success.
    if curl -fsSL --max-time 30 -o "$target.sha256" "$LATEST_URL.sha256" 2>/dev/null; then
        say "Verifying the checksum"

        # Compared by hand rather than with `sha256sum -c`: GNU spells the
        # quiet flag --status and busybox spells it -s, and the official
        # image is busybox. Comparing two strings needs neither.
        local expected actual
        expected="$(first_line "$(cat "$target.sha256")")"
        expected="${expected%% *}"
        actual="$(sha256sum "$target" | awk '{print $1}')"

        [[ -n "$expected" && "$expected" == "$actual" ]] \
            || fail "Checksum mismatch on $target.
  expected: ${expected:-<none published>}
  got:      $actual
That is not the file this release published. It has been left in place for you to look at."

        note "Checksum matches."
    else
        warn "No .sha256 was published beside this release, so the download could not be verified."
        ask "Continue with an unverified download? [y/N]" "n" || fail "Stopped. Nothing has been changed."
    fi
}

detect_web_user() {
    if [[ -z "$WEB_USER" ]]; then
        WEB_USER="$(stat -c '%U' "$INSTALL_DIR/public/index.php" 2>/dev/null || echo www-data)"
    fi

    id -u "$WEB_USER" >/dev/null 2>&1 || fail "No such user: $WEB_USER. Pass --user with the account your web server runs as."
    WEB_GROUP="$(id -gn "$WEB_USER")"
}

as_web_user() {
    if command -v runuser >/dev/null 2>&1; then
        ( cd "$INSTALL_DIR" && runuser -u "$WEB_USER" -- "$@" )
    else
        ( cd "$INSTALL_DIR" && su -s /bin/sh "$WEB_USER" -c "$(printf '%q ' "$@")" )
    fi
}

detect_services() {
    command -v systemctl >/dev/null 2>&1 || { SYSTEMD=0; return 0; }
    SYSTEMD=1

    if [[ -z "$FPM_SERVICE" ]]; then
        local candidates
        candidates="$(systemctl list-unit-files --no-legend --type=service 'php*fpm*.service' 2>/dev/null | awk '{print $1}')"

        case "$(printf '%s' "$candidates" | grep -c . || true)" in
            0) FPM_SERVICE="" ;;
            1) FPM_SERVICE="$candidates" ;;
            *)
                FPM_SERVICE="$(first_line "$(printf '%s\n' "$candidates" | while read -r unit; do
                    systemctl is-active --quiet "$unit" && echo "$unit"
                done || true)")"
                ;;
        esac

        if [[ -z "$FPM_SERVICE" ]]; then
            warn "Could not work out which PHP-FPM service to reload. Pass --php-fpm <unit>, or reload it yourself afterwards."
        fi
    fi

    if [[ -z "$WORKER_SERVICE" ]]; then
        local worker
        worker="$(systemctl list-unit-files --no-legend 'projectsend-worker.service' 2>/dev/null || true)"

        # An `[[ ... ]] && assignment` here would be the function's last
        # command, so a missing worker unit would return 1 and take the
        # whole script down with it under `set -e`.
        if [[ -n "$worker" ]]; then
            WORKER_SERVICE="projectsend-worker.service"
        fi
    fi

    return 0
}

env_value() {
    local raw
    raw="$(first_line "$(sed -n "s/^$1=//p" "$INSTALL_DIR/.env" 2>/dev/null || true)")"
    raw="${raw%\"}"; raw="${raw#\"}"
    raw="${raw%\'}"; raw="${raw#\'}"

    printf '%s' "$raw"
}

take_backup() {
    local connection database host port user password stamp target
    connection="$(env_value DB_CONNECTION)"; connection="${connection:-mysql}"
    database="$(env_value DB_DATABASE)"
    host="$(env_value DB_HOST)"; host="${host:-127.0.0.1}"
    port="$(env_value DB_PORT)"; port="${port:-3306}"
    user="$(env_value DB_USERNAME)"
    password="$(env_value DB_PASSWORD)"
    stamp="$(date -u +%Y%m%d-%H%M%S)"

    [[ "$BACKUP_DIR" == "$INSTALL_DIR/public"* ]] && fail "Refusing to write a database dump under public/ — it would be downloadable."

    mkdir -p "$BACKUP_DIR"
    chmod 700 "$BACKUP_DIR"

    case "$connection" in
        mysql|mariadb)
            target="$BACKUP_DIR/projectsend-$INSTALLED_VERSION-$stamp.sql"
            say "Dumping the database to $target"
            # Password through the environment: an argument would be
            # readable in `ps` by every user on the machine.
            MYSQL_PWD="$password" mysqldump --single-transaction --routines --triggers \
                -h "$host" -P "$port" -u "$user" "$database" > "$target" \
                || fail "The database dump failed. Nothing has been changed — fix this, or re-run without --backup once you have a backup of your own."
            ;;
        pgsql)
            target="$BACKUP_DIR/projectsend-$INSTALLED_VERSION-$stamp.sql"
            say "Dumping the database to $target"
            PGPASSWORD="$password" pg_dump -h "$host" -p "$port" -U "$user" "$database" > "$target" \
                || fail "The database dump failed. Nothing has been changed."
            ;;
        sqlite)
            target="$BACKUP_DIR/projectsend-$INSTALLED_VERSION-$stamp.sqlite"
            say "Copying the database to $target"
            cp -- "$database" "$target" || fail "Could not copy the SQLite database. Nothing has been changed."
            ;;
        *)
            fail "Unknown DB_CONNECTION '$connection' — take a backup yourself and re-run with --i-have-a-backup."
            ;;
    esac

    BACKUP_TAKEN="$target"
    note "This is the database only. Your uploaded files in storage/app/files/ are not in it."
}

cleanup() {
    local status=$?

    if [[ "${SITE_IS_DOWN:-0}" == "1" ]]; then
        as_web_user php artisan up >/dev/null 2>&1 || true
        SITE_IS_DOWN=0
    fi

    [[ -n "${TMP_DIR:-}" && -d "${TMP_DIR:-}" ]] && rm -rf "$TMP_DIR"
    [[ -n "${PROJECTSEND_UPDATE_SELF:-}" && -f "${PROJECTSEND_UPDATE_SELF:-}" ]] && rm -f "$PROJECTSEND_UPDATE_SELF"

    if [[ "$status" != "0" && "${APPLYING:-0}" == "1" ]]; then
        echo >&2
        warn "The update did not finish."
        warn "The site has been taken out of maintenance mode, but its files may be part-way updated."
        [[ -n "${BACKUP_TAKEN:-}" ]] && warn "Your database dump is at $BACKUP_TAKEN."

        # Skipped when the failure already printed instructions of its own,
        # so the operator is never given two different sets at once.
        if [[ "${RECOVERY_PRINTED:-0}" != "1" ]]; then
            warn "To finish by hand:  cd $INSTALL_DIR && sudo -u $WEB_USER php artisan projectsend:update"
            [[ -n "${FPM_SERVICE:-}" ]] && warn "Then reload PHP:    sudo systemctl reload $FPM_SERVICE"
        fi
    fi

    exit $status
}

report_versions() {
    note "Installed: $INSTALLED_VERSION"

    if latest_release; then
        if version_newer "$LATEST_VERSION" "$INSTALLED_VERSION"; then
            note "Latest:    $LATEST_VERSION"
            echo
            note "Read the changelog for $LATEST_VERSION first, and make sure you have a backup."
            if in_container; then
                note "This is a container, so its code comes from its image:"
                note "  docker compose pull && docker compose up -d"
            else
                note "Then:  sudo ./update.sh"
            fi
        else
            note "Latest:    $LATEST_VERSION — you are up to date."
            note "Re-applying the same release with --zip restores files that were modified or lost."
        fi
    fi
}

main() {
    parse_args "$@"
    resolve_install_dir

    INSTALLED_VERSION="$(version_in_file "$INSTALL_DIR/config/projectsend.php")"
    [[ -n "$INSTALLED_VERSION" ]] || INSTALLED_VERSION="unknown"

    if [[ "$CHECK_ONLY" == "1" ]]; then
        say "ProjectSend at $INSTALL_DIR"
        report_versions
        exit 0
    fi

    if in_container; then
        note "Installed: $INSTALLED_VERSION"
        echo
        fail "This is a container: its code comes from its image, and replacing files inside it would be undone by the next recreate.
Update it with:  docker compose pull && docker compose up -d"
    fi

    [[ "$EUID" -eq 0 ]] || fail "This needs root, to reload PHP-FPM and to write files owned by the web server user. Run: sudo ./update.sh"

    reexec_from_copy "$@"

    trap cleanup EXIT INT TERM

    say "ProjectSend at $INSTALL_DIR"

    # ---- what to apply -------------------------------------------------
    if [[ -z "$ZIP" ]]; then
        note "Installed: $INSTALLED_VERSION"

        if ask "Check GitHub for a newer release? [Y/n]" "y" && latest_release; then
            note "Latest:    $LATEST_VERSION"

            if ! version_newer "$LATEST_VERSION" "$INSTALLED_VERSION"; then
                note "You are already on the latest release."
                ask "Download and re-apply $LATEST_VERSION anyway, to restore modified files? [y/N]" "n" \
                    || { note "Nothing to do."; exit 0; }
            fi

            if ask "Download $(basename "$LATEST_URL") and verify its checksum? [Y/n]" "y"; then
                DOWNLOAD_DIR="${DOWNLOAD_DIR:-$(mktemp -d "${TMPDIR:-/tmp}/projectsend-download.XXXXXX")}"
                mkdir -p "$DOWNLOAD_DIR"
                ZIP="$DOWNLOAD_DIR/$(basename "$LATEST_URL")"
                download_release "$ZIP"
            else
                note "Download it yourself, then run:"
                note "  sudo ./update.sh --zip $(basename "$LATEST_URL")"
                note "  $LATEST_URL"
                exit 0
            fi
        fi
    fi

    [[ -n "$ZIP" ]] || fail "Nothing to apply. Pass --zip <file>, or re-run and let it fetch the release."
    [[ -f "$ZIP" ]] || fail "No such file: $ZIP"

    # ---- is it sane, and is it forwards? -------------------------------
    NEW_VERSION="$(version_in_zip "$ZIP")"

    if [[ -z "$NEW_VERSION" ]]; then
        [[ "$FORCE" == "1" ]] || fail "Could not read a version out of $ZIP. If you are sure it is a ProjectSend release, re-run with --force."
        NEW_VERSION="unknown"
    fi

    # `unzip -p` rather than parsing a listing: it answers "is this exact
    # entry there" on both Info-ZIP and busybox, and it involves no pipe —
    # `unzip -l | grep -q` looks equivalent but is not, because grep -q
    # closes the pipe, unzip takes SIGPIPE, and pipefail then reports a
    # perfectly good zip as broken.
    for entry in artisan public/index.php vendor/autoload.php; do
        unzip -p "$ZIP" "$entry" >/dev/null 2>&1 \
            || fail "$ZIP has no $entry at its root — that is not a ProjectSend release zip.
If it unpacks into a folder of its own, this is not the file we publish; use the one from $RELEASES_PAGE."
    done

    if unzip -p "$ZIP" '.env' >/dev/null 2>&1; then
        fail "$ZIP contains a .env. A release zip never does; refusing to overwrite your configuration."
    fi

    if [[ "$NEW_VERSION" != "unknown" && "$NEW_VERSION" != "$INSTALLED_VERSION" ]] && ! version_newer "$NEW_VERSION" "$INSTALLED_VERSION"; then
        [[ "$FORCE" == "1" ]] || fail "$ZIP is version $NEW_VERSION and this installation is on $INSTALLED_VERSION.
Migrations only move forwards — there is no command that undoes them. To go back, restore the database dump you took before updating.
If you know what you are doing, re-run with --force."
    fi

    detect_web_user
    detect_services

    # ---- the backup question -------------------------------------------
    if [[ "$HAVE_BACKUP" == "0" && "$BACKUP" == "0" ]]; then
        if ! ask "Have you backed up the database and your files? [y/N]" "n"; then
            warn "An update that goes wrong can lose data, and a migration cannot be undone."
            ask "Continue anyway? [y/N]" "n" || fail "Stopped. Nothing has been changed."

            if ask "Take a database dump now? [Y/n]" "y"; then
                BACKUP=1
            else
                warn "Continuing without a backup, at your own risk."
            fi
        fi
    fi

    # ---- confirm --------------------------------------------------------
    echo
    say "About to update this installation"
    note "Directory:  $INSTALL_DIR"
    note "Version:    $INSTALLED_VERSION -> $NEW_VERSION"
    note "Zip:        $ZIP"
    note "As user:    $WEB_USER"
    [[ "$BACKUP" == "1" ]] && note "Backup:     yes, to $BACKUP_DIR"
    if [[ "$NO_RESTART" == "1" || "${SYSTEMD:-0}" == "0" ]]; then
        note "Restart:    nothing (you must reload PHP-FPM yourself)"
    else
        note "Restart:    ${FPM_SERVICE:-<none found>}${WORKER_SERVICE:+, $WORKER_SERVICE}"
    fi
    echo

    ask "Proceed? [y/N]" "n" || fail "Stopped. Nothing has been changed."

    APPLYING=1

    [[ "$BACKUP" == "1" ]] && take_backup

    # ---- apply ----------------------------------------------------------
    say "Putting the site into maintenance mode"
    as_web_user php artisan down --retry=60 >/dev/null
    SITE_IS_DOWN=1

    say "Unpacking $ZIP"
    TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/projectsend-unpack.XXXXXX")"
    unzip -q "$ZIP" -d "$TMP_DIR"

    # Belt and braces. The zip has no storage/ content and no .env, but
    # this is the copy that could destroy somebody's library, so it is
    # made structurally impossible rather than merely unlikely.
    rm -rf "$TMP_DIR/storage" "$TMP_DIR/.env"

    say "Replacing the application files"
    # vendor/ and public/build/ belong wholly to the release: merged, they
    # keep orphaned classes and orphaned hashed assets from the old one.
    rm -rf "$INSTALL_DIR/vendor" "$INSTALL_DIR/public/build"
    rm -f "$INSTALL_DIR"/bootstrap/cache/*.php
    cp -a "$TMP_DIR"/. "$INSTALL_DIR"/

    # Symlinks are skipped on purpose. A symlink's own ownership decides
    # nothing — access is governed by whatever it points at — and some
    # filesystems refuse to change it at all: a bind mount from a host
    # (public/storage on a developer's box is where this turned up), an NFS
    # export with root_squash. Aborting an update over a cosmetic detail on
    # a link is not a trade worth making, so they are left alone rather than
    # tolerated by ignoring errors that might have mattered.
    find "$INSTALL_DIR" \! -type l -exec chown "$WEB_USER:$WEB_GROUP" {} + \
        || warn "Some files could not be given to $WEB_USER. Check ownership under $INSTALL_DIR before trusting this install."

    chmod -R u+rwX "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"

    say "Bringing the installation in line with the new code"

    # The command comes from the code that was just unpacked, so a release
    # older than this script does not have it. Only reachable by re-applying
    # or forcing an old release — but the error Symfony prints for an unknown
    # command is a list of thirteen unrelated ones, which is no help at all
    # to somebody whose site is in maintenance mode.
    local commands
    commands="$(as_web_user php artisan list --raw --no-ansi 2>/dev/null || true)"

    if [[ "$commands" != *"projectsend:update"* ]]; then
        # The generic recovery block in cleanup() would otherwise name the
        # very command this release does not have.
        RECOVERY_PRINTED=1
        fail "The release you unpacked ($NEW_VERSION) predates this update script: it has no projectsend:update command.
Finish it by hand, as $WEB_USER:
  php artisan migrate --force
  php artisan projectsend:ensure-roles
  php artisan optimize:clear
  php artisan queue:restart
Then reload PHP-FPM, and bring the site back with: php artisan up"
    fi

    as_web_user php artisan projectsend:update

    # ---- restart --------------------------------------------------------
    if [[ "$NO_RESTART" == "1" ]]; then
        warn "Not touching systemd, as asked."
        warn "PHP is still running the old code until you reload it — ProjectSend will keep telling your staff so."
    elif [[ "${SYSTEMD:-0}" == "1" && -n "${FPM_SERVICE:-}" ]]; then
        say "Reloading $FPM_SERVICE"
        systemctl reload "$FPM_SERVICE" 2>/dev/null || systemctl restart "$FPM_SERVICE" \
            || warn "Could not reload $FPM_SERVICE. Do it yourself, or PHP keeps serving the old code."

        if [[ -n "${WORKER_SERVICE:-}" ]]; then
            say "Restarting $WORKER_SERVICE"
            systemctl restart "$WORKER_SERVICE" || warn "Could not restart $WORKER_SERVICE."
        fi
    else
        warn "No PHP-FPM service was found to reload. Reload PHP yourself now, or it keeps serving the old code."
    fi

    say "Bringing the site back up"
    as_web_user php artisan up >/dev/null
    SITE_IS_DOWN=0

    if [[ "$KEEP_DOWNLOAD" == "0" && -n "${DOWNLOAD_DIR:-}" && "$ZIP" == "$DOWNLOAD_DIR"/* ]]; then
        rm -rf "$DOWNLOAD_DIR"
    fi

    APPLYING=0

    echo
    say "ProjectSend $NEW_VERSION is installed and running."
    [[ -n "${BACKUP_TAKEN:-}" ]] && note "Database dump: $BACKUP_TAKEN"
    note "Check the dashboard's System card, then System -> Settings -> Scheduler, then download a file."

    invitation
}

# The last thing on the screen, and the only moment in this script where
# nothing is at stake: an update that just worked is a better time to
# mention the community than an install, where somebody is mid-task.
#
# Printed, not asked. The answer to "would you like to join?" is a browser,
# and this runs over SSH on a server that has none — so a y/n whose only
# outcome is printing the URL anyway would be a keystroke charged for
# nothing. It would also be answered "yes" by --yes, on behalf of a cron
# job that cannot join anything.
invitation() {
    echo
    say "Come and say hello"
    note "ProjectSend has a Discord: release news, help when something is not"
    note "behaving, and other people running the same software. We are in it too."
    note "$DISCORD_URL"
}

main "$@"
