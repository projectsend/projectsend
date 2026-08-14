<p align="center">
  <img src="public/favicon.svg" alt="" width="84">
</p>

<h1 align="center">ProjectSend</h1>

<p align="center">
  <strong>Share files with your clients, from your own server.</strong>
</p>

<p align="center">
  <a href="LICENSE"><img alt="License: GPL v2 or later" src="https://img.shields.io/badge/license-GPLv2%2B-3b5bdb"></a>
  <img alt="PHP 8.4+" src="https://img.shields.io/badge/PHP-8.4%2B-777bb4">
  <img alt="Self-hosted" src="https://img.shields.io/badge/self--hosted-Docker%20or%20zip-0b7285">
</p>

---

ProjectSend is a self-hosted application for getting files to the people you work with. You upload
what you want to send, choose exactly who can see it, and each client signs in to their own private
page to download it.

No public link passed around by email, no third-party service holding your clients' documents, no
per-seat pricing. It runs on your server, and the files stay there.

## What it does

**For the people you send to**
- A private area per client, showing only what has been shared with them
- Sign in with an email address, with optional two-factor authentication
- Search, filter and sort their files; download one, several as a zip, or a whole folder
- Optional comments on a file, so questions live next to the thing they are about
- Email notifications when something new arrives, in their own language

**For you**
- Resumable uploads that survive a dropped connection, so large files actually arrive
- Organise with folders, categories and client groups
- Share with one client, a whole group, or publicly — and set an expiry date or a download limit
- Thumbnails and previews for images and documents
- Storage quotas per client, and custom fields for the details you need to keep on them
- A full activity log and download history: who got what, and when

**For the installation**
- Roles and permissions for your own team, so an uploader is not an administrator
- Sign-in the way you already work: LDAP, social sign-in, or plain email and password
- Themes for the client-facing pages and for outgoing email
- 16 languages
- A REST API with scoped tokens and generated OpenAPI docs
- Privacy controls, including GDPR-grade account erasure with a grace period
- Local disk or S3-compatible storage

## Screenshots

<p align="center">
  <img src=".github/screenshots/dashboard.png" alt="The dashboard: counters for files, clients and groups, the clients using the most storage against their quotas, a month of uploads and downloads as a line chart, and recent activity" width="900">
</p>
<p align="center"><em>The dashboard — what is in the installation, and what has been happening in it.</em></p>

<p align="center">
  <img src=".github/screenshots/files.png" alt="The file library, showing folders and files with thumbnails, sharing status and download counts" width="900">
</p>
<p align="center"><em>Your library — folders, categories, and who each file is shared with.</em></p>

<p align="center">
  <img src=".github/screenshots/portal.png" alt="A client's own page, listing the files shared with them with download buttons" width="900">
</p>
<p align="center"><em>What your client sees — only their files, nothing else.</em></p>

## Getting started

**With Docker** — the quickest path, and the one we recommend.

```sh
git clone https://github.com/projectsend/projectsend.git
cd projectsend
cp .env.example .env          # set PROJECTSEND_EDITION=community
docker compose up -d
```

The app is at `http://localhost:8090`, and the first thing it shows you is a setup screen that
creates your administrator account.

Before you put real files in it, read **[DOCKER.md](DOCKER.md)** — where your database and uploads
actually live, how to move them onto paths you chose, and how to back them up so an upgrade can't
take them with it.

**Without Docker** — for servers where it isn't an option, install from a release zip.
**[INSTALL.md](INSTALL.md)** covers requirements, `.env`, nginx, the background worker and cron,
updating and troubleshooting. You do not need Composer or npm on the server; the zip ships ready to
run.

**Already running it?** **[UPDATE.md](UPDATE.md)** is how you move to a new version — one command
on Docker, a short sequence on your own server, and what to check afterwards either way.

## Coming from ProjectSend Legacy?

The previous generation of ProjectSend lives on at
[projectsend/legacy](https://github.com/projectsend/legacy). This is a rebuild rather than an
upgrade, so moving across is an import rather than an update — install fresh, then bring your old
site into it with the
[migration tool](https://github.com/projectsend/v1-migration-tool): accounts, clients, groups,
categories, folders, files and history.

It never writes to your old install, and any run can be undone with a single command.
**[MIGRATING-FROM-V1.md](MIGRATING-FROM-V1.md)** explains what comes across, the two routes
(same machine, or a portable export from a server you can't reach), and the one change your clients
will notice: they sign in with their email address now, using the same password.

## Contributing

Bug reports, translations and pull requests are all welcome — see
**[CONTRIBUTING.md](CONTRIBUTING.md)** for how to set up a development copy, what the checks are,
and the contributor agreement.

Found a security issue? Please report it privately through GitHub's security advisories rather than
opening a public issue.

## License

Free software under the **GNU General Public License v2, or (at your option) any later version** —
see [LICENSE](LICENSE). Use it, study it, change it, share it.

Commercial licenses are available for organizations that cannot work under copyleft terms;
[LICENSING.md](LICENSING.md) explains both options. Contributions require signing a CLA, for reasons
set out in [CONTRIBUTING.md](CONTRIBUTING.md).
