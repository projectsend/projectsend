# ProjectSend Licensing

ProjectSend is available under two licenses. Pick whichever fits.

## 1. GNU GPL v2 — free, for everyone

The default, and the license ProjectSend has used since 2011. Free of charge, forever, with the
four freedoms intact: run it, study it, modify it, share it. See [LICENSE](LICENSE) for the full
text. Formally the grant is "GPLv2, or at your option, any later version" — the "or later"
option is there because some bundled dependencies (such as the AWS SDK) are Apache-2.0 licensed,
which combines cleanly with GPLv3 terms but not with GPLv2-only.

**If you're self-hosting ProjectSend to share files with your own clients, this is you, and there
is nothing to think about.** Using the software — even commercially, even inside a large company,
even with paying clients — triggers no obligations. You don't have to publish anything.

The GPL asks for reciprocity in one situation: **if you distribute a modified version**, you ship
your changes' source alongside it, under the GPL. Running it on your own server isn't
distribution.

## 2. Commercial license — for when the GPL doesn't fit

Some organizations can't work under copyleft terms. Common cases:

- You want to **embed** ProjectSend inside a closed-source product you sell.
- You want to **white-label** it as part of a commercial offering and distribute it to customers
  without publishing your modifications.
- Your legal or procurement department has a blanket policy against GPL dependencies in shipped
  products.
- You need **warranties or indemnification**, which no open source license provides.

A commercial license removes the GPL's source-sharing obligations. It's the same software.

Contact <contact@projectsend.org> with a short description of what you're building and how you
plan to distribute it, and we'll come back with terms.

---

## What's in the core, and what's in ProjectSend Cloud

We run [ProjectSend Cloud](https://projectsend.cloud), a hosted version. It has features the
self-hosted core doesn't. Rather than let you find that out one feature announcement at a time,
here's the line we draw and the commitments that go with it.

**The principle:** if a capability makes ProjectSend work for one organization on its own server,
it belongs in the core. If it only exists because we run installations on other people's behalf,
it belongs in Cloud.

| | |
|---|---|
| **Always in the free core** | Client accounts and groups · file assignment and expiration · client uploads · download tracking and activity logs · categories and folders · themes and custom branding · two-factor authentication · role-based permissions · S3-compatible storage · public links · translations · the extension API |
| **Cloud only** | Billing and subscriptions · automated provisioning · managed backups and restore · infrastructure monitoring and uptime SLA · managed email deliverability · cross-organization administration |

**Our commitments:**

1. Nothing that is free today will ever move behind a paid tier.
2. No artificial limits in the self-hosted version — no caps on users, clients, files or storage
   designed to push you toward the hosted plan. Self-hosted ProjectSend is a complete product,
   not a demo.
3. Cloud features are built on a public extension API. The same API is available to you, so
   anything we can build as a first-party extension, you can build too.
4. When we add a paid feature, we'll say so plainly in the release notes rather than letting you
   discover it.

**Where it gets genuinely gray:** enterprise-oriented capabilities like SAML/SSO, advanced audit
exports, and long-horizon retention policies would work fine self-hosted, and we may build some
of them for paying customers. We're not going to pretend otherwise. What we won't do is take
something out of the core to put it there.

---

## Frequently asked questions

**I run ProjectSend on my own server for my clients. Do I owe anything?**
No. Self-hosting for your own use — including commercial use, including with paying clients —
carries no obligation. You don't have to publish anything and you don't need a commercial license.

**I made changes to my installation. Now what?**
If you're not distributing that modified version to anyone else, nothing. Keep your changes to
yourself if you want. If you do hand out copies, they come with the source under the GPL. Either
way, we'd rather you upstream them.

**Can I resell ProjectSend hosting?**
Yes. The GPL doesn't stop anyone from offering ProjectSend as a hosted service, including in
competition with us.

**Why is there a CLA if the license isn't changing?**
Two reasons. It's what lets us offer the commercial license described above — without rights to
every contributor's code, we can't license the whole thing to anyone. And it means we're not
permanently locked out of ever updating the project's license, which today would require
tracking down fifteen years of contributors. We have no license change planned. See
[CONTRIBUTING.md](CONTRIBUTING.md) for the full reasoning and what we commit to in exchange.

**Are you going to switch to AGPL / BSL / SSPL?**
No plans. If that ever changes it'll be proposed publicly and discussed before anything happens,
not announced after the fact. And in any case, every release published under the GPLv2 stays
GPLv2 permanently — that can't be revoked and we wouldn't want to.

**Do I need a commercial license to contribute?**
No. Contributions are welcome under the GPL. See [CONTRIBUTING.md](CONTRIBUTING.md).

---

*Nothing on this page is legal advice. If your situation is complicated, talk to a lawyer — and
if you tell us what you're trying to do, we'll usually be able to tell you quickly whether you
need a commercial license or not.*
