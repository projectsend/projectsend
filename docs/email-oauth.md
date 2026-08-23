# Sending email through Microsoft 365 or Gmail

ProjectSend can send its transactional email — client welcomes, share notifications, password
resets — directly through **Microsoft Graph** or the **Gmail API**, authorized by a mailbox you
connect once from the settings screen. No SMTP password, no app password, no SMTP AUTH: you sign
into the mailbox the installation should send as, approve one permission, and you're done.

This is the recommended way to send through a Microsoft 365 or Google mailbox. Microsoft is
retiring password-based SMTP submission, and both vendors treat their HTTP APIs as the supported
path forward. The classic SMTP transport (with the SendGrid/Mailgun/Postmark/SES presets) remains
available for everything else.

---

## How it works

Go to **Settings → Email → Sending** and pick **Microsoft 365 (OAuth)** or
**Google / Gmail (OAuth)** as the provider. Instead of host and password, the form asks for an
*app registration* — a client ID and secret you create once with the vendor (steps below) — and
then offers **Connect mailbox**: a normal "sign in with Microsoft/Google" screen where you log
into the sending mailbox and approve the send permission.

A few properties worth knowing before you start:

- **Email sends as the connected mailbox.** The From address is the account you signed in with —
  connect `noreply@your-domain` if that is who should appear as the sender. The From *name* stays
  configurable.
- **The permission is minimal.** ProjectSend asks only for "send mail" (`Mail.Send` on Graph,
  `gmail.send` on Google) plus basic sign-in — it cannot read the mailbox.
- **Secrets and tokens are encrypted at rest** and never leave the server or appear in a browser
  response.
- **The connection looks after itself.** Tokens refresh automatically at send time, and a daily
  scheduled check keeps the connection alive through quiet periods. If the grant ever dies — a
  password reset or a policy change can do that — the settings page shows a red warning and every
  administrator who can edit settings gets an in-app notification, instead of mail silently
  stopping. Reconnecting is one click through the same consent screen.
- **Nothing is lost by switching.** Your SMTP settings survive a switch to an OAuth provider and
  back; both OAuth connections can exist side by side, and the provider dropdown decides which one
  actually sends.

---

## Microsoft 365

### Register the application (once)

You need any account that can create an app registration in *some* Microsoft Entra directory —
a work or school account, typically. (A personal Microsoft account can be used as the sending
mailbox, but cannot open the Entra portal unless it has an Azure account of its own.)

1. Go to [entra.microsoft.com](https://entra.microsoft.com) → **App registrations** → **New
   registration**.
2. Name it (e.g. "ProjectSend Mail") and choose the **Supported account types**:
   - *Accounts in this organizational directory only* — if the sending mailbox lives in your own
     tenant. Enter your **Directory (tenant) ID** in ProjectSend later.
   - *Any organizational directory and personal Microsoft accounts* — the most permissive choice;
     works with the ProjectSend tenant field left empty.
   - *Personal Microsoft accounts only* — works, but then the ProjectSend tenant field must say
     `consumers` (the `/common` endpoint refuses consumer-only apps).
3. Under **Redirect URI**, pick type **Web** and enter:

   ```
   https://your-install.example.com/system/settings/email/oauth/callback
   ```

   (`http://localhost:...` is accepted for local development.)
4. After creating: copy the **Application (client) ID** from the overview page.
5. **Certificates & secrets** → **New client secret** → copy the **Value** immediately — it is
   shown only once. Note the expiry you chose; when the secret expires, sending stops until you
   save a new one and reconnect.

You do *not* need to configure API permissions in the portal — the delegated `Mail.Send`
permission is granted on the consent screen when you connect.

### Connect

1. **Settings → Email → Sending** → provider **Microsoft 365 (OAuth)**.
2. Enter the client ID and secret; fill the **Directory (tenant) ID** according to the account
   type above (empty means "any account", a GUID pins your tenant, `consumers` for personal-only
   apps). **Save.**
3. Click **Connect mailbox**, sign in as the sending mailbox, accept the consent prompt.
4. Send yourself a message from the **Test** tab.

### If something refuses

- **"userAudience" error when connecting** — the app registration is personal-accounts-only but
  the tenant field is empty. Put `consumers` in it (or change the registration's supported
  account types).
- **`AADSTS50020` (user does not exist in tenant)** — the tenant field pins a directory the
  signing-in account is not a member of. Empty the field or sign in with a matching account.
- **`ErrorSendAsDenied` when sending** — the message's From differs from the connected mailbox
  and the mailbox has no SendAs rights over that address. Usually means a stale From setting;
  reconnecting or re-saving the settings page pins From to the connected account.
- **`ErrorQuotaExceeded` when sending** — despite the name, check the simple thing first: the
  mailbox may literally be full. (Consumer accounts also see this when Outlook.com restricts a
  dormant or unverified account — sign into the mailbox on the web once and try again.)

## Google / Gmail

### Register the OAuth client (once)

1. Go to [console.cloud.google.com](https://console.cloud.google.com), create (or select) a
   project.
2. Enable the **Gmail API** for the project (search for it, press *Enable*) — without this every
   send fails with a 403.
3. Configure the **OAuth consent screen** ("Google Auth Platform"): app name, support email,
   audience **External**, contact email. The publishing status starts as **Testing**.
4. While in Testing: add the Google account you will connect as a **test user** — only test
   users can pass the consent screen.
5. Create the client: **Clients** (or *Credentials → Create credentials → OAuth client ID*) →
   type **Web application** → add the authorized redirect URI:

   ```
   https://your-install.example.com/system/settings/email/oauth/callback
   ```

6. Copy the **Client ID** and **Client secret**.

### Connect

1. **Settings → Email → Sending** → provider **Google / Gmail (OAuth)** → enter client ID and
   secret → **Save**.
2. **Connect mailbox** → sign in with the Google account. While the app is unverified, Google
   shows a "Google hasn't verified this app" warning — *Advanced → continue* is expected here.
3. Send yourself a message from the **Test** tab.

### The 7-day rule, and getting rid of it

While the consent screen is in **Testing** status, Google expires the refresh token after **seven
days** — the connection then shows as broken and asks to be reconnected weekly. For real use, do
one of these:

- **Google Workspace:** set the consent screen's audience to **Internal**. No verification, no
  warning screen, no 7-day expiry — the right choice when the sending account belongs to your
  own Workspace domain.
- **Personal Gmail:** publish the app to **In production** (Audience → *Publish app*). The
  unverified-app warning remains (verification is only required for broader scopes/user counts),
  but tokens stop expiring.

---

## Production notes

- The redirect URI registered with the vendor must match your installation's real URL. If you
  tested locally first, add the production callback URL as a second redirect URI — both vendors
  accept several per client.
- The connect and callback endpoints require an administrator who can edit settings; the OAuth
  `state` parameter and the session tie every callback to a connect actually started from your
  settings screen.
- Queued mail picks up a new or changed connection immediately — saving the settings restarts
  the queue workers.
- The daily token refresh runs as the scheduled task `Refresh mail OAuth tokens`, visible with
  every other scheduled command under **Settings → Scheduler**.
