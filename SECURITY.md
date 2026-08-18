# Security Policy

## Reporting a vulnerability

**Use [GitHub's private vulnerability reporting](https://github.com/projectsend/projectsend/security/advisories/new).**
It is the "Report a vulnerability" button on this repository's Security tab. The report stays
private between you and the maintainers, the whole exchange lives in one place, and it is the route
that can end in a published advisory with a CVE and your name on it.

If you would rather not use GitHub, or the report does not fit that form, email
<contact@projectsend.org> instead. Either is fine. What matters is that it does not start in
public.

**Please do not open a public issue for a security report.** An issue is world-readable the moment
it is filed, including by people running the version you just described how to break.

### What helps

Enough to reproduce it, and nothing you would not want to write down:

- The version, from **System → About** (self-hosted) or `config/projectsend.php`.
- How the installation is deployed — the Docker image, a manual install behind nginx, something
  else — and anything unusual in front of it.
- The steps, and what you saw. A short recording or a `curl` command beats a description.
- What an attacker gets out of it, if it is not obvious.

You do not need a proof-of-concept exploit, and you should not run one against an installation that
is not yours.

### What to expect

An acknowledgement within a few days, and a real answer — a fix, a plan, or a reason it is not
what it looked like — once it has been reproduced. If a fix ships, you are credited by name unless
you would rather not be.

This is a small project. If a week goes by in silence, assume the message went astray rather than
that it was ignored, and send it again.

## What is in scope

Anything that lets somebody reach a file, an account, or an installation they should not: the
sharing and permission rules, authentication and two-factor, the public pages and share links, the
API, the upload and download paths, and the setup and update flows.

Some things are worth a report but are not vulnerabilities in ProjectSend:

- **An installation that has not done what the install guide says.** Serving the storage directory
  straight from the web server, or running without the protected-file rules, is a deployment
  problem — see [INSTALL.md](INSTALL.md) and [DOCKER.md](DOCKER.md). Tell us anyway if the
  documentation is what led somebody there.
- **Findings from a scanner, unread.** A header a tool wanted and an exploit are different
  claims. Say which one you have.
- **Anything in a dependency**, unless ProjectSend's use of it is what makes it reachable. Those
  belong upstream, and Dependabot already watches for them here.

## Supported versions

| | |
|---|---|
| **ProjectSend 2.x** | Supported. Fixes land on the current release line; upgrade before reporting that an older 2.x behaves differently. |
| **The companion packages** — [`community-modules`](https://github.com/projectsend/community-modules), [`v1-migration-tool`](https://github.com/projectsend/v1-migration-tool) | Supported, on their own version lines. Report them here or on their own repository; both reach the same people. |
| **ProjectSend Legacy (v1)** | A separate application in a separate repository — see [projectsend/legacy](https://github.com/projectsend/legacy) for how it handles reports. Nothing here applies to it. |

## Hardening your own installation

If you are trying to configure an installation rather than report a bug, the deployment
documentation is what you want: [INSTALL.md](INSTALL.md) for a manual install, including the web
server rules that keep uploaded files private, and [DOCKER.md](DOCKER.md) for the image, where
those rules are already in place.

Two things are worth knowing whichever way you installed:

- **Put TLS in front of it**, and set `TRUSTED_PROXIES` when you do. Without it every visitor
  appears to come from the proxy, which turns the login rate limiter into one bucket for all of
  them and records the wrong address in the download log.
- **`APP_KEY` decrypts what is already stored.** Back it up with the database, and do not rotate it
  on a running installation without knowing what you are doing.
