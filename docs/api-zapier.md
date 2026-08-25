# Connecting ProjectSend to Zapier

Zapier connects apps to each other. You pick something that happens in one app, and something that
should happen in another. ProjectSend has no official Zapier app yet, but you do not need one: the
API works with Zapier's built-in **Webhooks by Zapier** actions today, on both the self-hosted and
the hosted edition.

This page shows how to set that up. It assumes you have used Zapier before. If you have not, the
short version is that a "Zap" is one rule made of a **trigger** (what starts it) and one or more
**actions** (what it does).

**One thing to check first:** everything here uses **Webhooks by Zapier**, which Zapier includes
only from its Professional plan upwards. On Zapier's free plan you will not be able to add those
steps. Nothing below works around that — it is Zapier's limit, not ours.

Everything here also works with Make, n8n, Pipedream, Power Automate and anything else that can call
an HTTP endpoint. See the last section.

---

## What you can build

Some things people actually use this for:

- Post a message in Slack when a client uploads a file.
- Create a ProjectSend client account when someone fills in a form.
- Add a row to a spreadsheet every time a file is shared.
- Upload a file to ProjectSend when it lands in a Dropbox folder.
- Send yourself a reminder when a comment is waiting for approval.

---

## Before you start: make a token

Zapier signs in as you, using a token instead of your password.

1. Sign in to ProjectSend and go to **Settings → API tokens**.
2. Click **Create token**. Name it something you will recognise later, like `Zapier`.
3. Tick only the permissions the Zap needs. A Zap that reads files and posts to Slack only needs
   read access to files. It does not need to create clients.
4. Choose an expiry date. Read **Your token will expire** near the end of this page before you
   pick one — that part catches people out.
5. Copy the token. It is shown once and never again.

Keep the token somewhere safe until you have pasted it into Zapier. Anyone who has it can do the
things you ticked.

---

## Set up the connection in Zapier

Zapier calls this an "authentication". You only do it once, and every Zap can reuse it.

In any **Webhooks by Zapier** step, open the request settings and add this header:

| Field | Value |
|---|---|
| Header name | `Authorization` |
| Header value | `Bearer YOUR_TOKEN` |

The word `Bearer`, then a space, then the token. That is the whole thing.

To check it works before you build anything, make a test Zap with a **Webhooks by Zapier → GET**
step pointing at:

```
https://your-install.example.com/api/v1/me
```

You should get back your own name, your email, and the list of things this token is allowed to do.
If you get a `401`, the header is wrong or the token has expired. If you get a `403`, the token is
valid but you did not tick that permission.

---

## Triggers: starting a Zap when something happens

ProjectSend does not push events out yet, so Zapier has to ask. Use **Webhooks by Zapier → Retrieve
Poll**. Zapier calls the URL every few minutes, and runs your Zap for anything it has not seen
before.

This works out of the box. List endpoints return the newest items first, and every item has an `id`,
which is exactly what Zapier needs to tell new things from old ones.

### Useful things to watch

| What you want to know | URL |
|---|---|
| **A file was shared with a client** | `…/api/v1/activity?action[]=file.assigned` |
| **A client downloaded a file** | `…/api/v1/activity?action[]=file.downloaded` |
| **Somebody left a comment** | `…/api/v1/activity?action[]=comment.posted` |
| Anything at all happened | `…/api/v1/activity` |
| A file was added | `…/api/v1/files` |
| A client account was created | `…/api/v1/clients` |
| A comment is waiting for approval | `…/api/v1/comments/pending` |
| A group was created | `…/api/v1/groups` |

The first three are the ones people usually want, and they only work through
`/api/v1/activity`. Sharing a file writes an assignment and leaves the file itself untouched, so
polling the file list will never show you a share; downloads are recorded in the activity log and
nowhere else. Repeat `action[]` to watch for more than one kind of thing at once.

Watching activity needs a token with **view activity log** ticked.

In the Zapier step, set:

- **Method**: GET
- **URL**: one of the above
- **Headers**: the `Authorization` header from earlier
- **Key**: `data` — this tells Zapier where the list of items is inside the response

### What comes back

From `/api/v1/activity`:

```json
{
  "data": [
    {
      "id": 1284,
      "action": "file.assigned",
      "created_at": "2026-08-25T09:14:02+00:00",
      "actor": { "id": 3, "name": "Dana", "type": "staff" },
      "subject": { "type": "file", "id": 128, "name": "October invoice" },
      "context": { "target": "Acme Ltd" }
    }
  ]
}
```

So a Slack message can read *"Dana shared October invoice with Acme Ltd"* using `actor.name`,
`subject.name` and `context.target`.

From `/api/v1/files`:

```json
{
  "data": [
    {
      "id": 128,
      "name": "October invoice",
      "original_name": "invoice-oct.pdf",
      "mime_type": "application/pdf",
      "size": 184320,
      "public": false,
      "expires_at": null,
      "created_at": "2026-08-25T09:14:02+00:00",
      "updated_at": "2026-08-25T09:14:02+00:00"
    }
  ],
  "links": { "next": "https://your-install.example.com/api/v1/files?cursor=..." }
}
```

Every field in there is available in later steps of your Zap. So a Slack message can say
*"October invoice was just uploaded"* by using the `name` field.

### Only want some of them?

Add query parameters to the URL. For example, only the files a particular client uploaded:

```
https://your-install.example.com/api/v1/files?uploaded_by=42
```

`GET /api/v1/files` also accepts `folder_id`, `category_id`, `search`, `public` and `expired`.

The full list of filters for each endpoint is in the **API** page inside ProjectSend, and in the
OpenAPI file at `/api/v1/openapi.json`.

---

## Actions: what a Zap can do

Use **Webhooks by Zapier → Custom Request** for these, so you can set the method and the body.

Every one of them needs the `Authorization` header, and a second header:

| Header name | Value |
|---|---|
| `Content-Type` | `application/json` |

### Create a client account

- **Method**: POST
- **URL**: `https://your-install.example.com/api/v1/clients`
- **Data**:

```json
{
  "name": "Acme Ltd",
  "email": "billing@acme.example",
  "password": "a-long-random-password"
}
```

The password has to satisfy whatever rules this installation sets under **Settings → Security**, so
generate a long one. The client can change it later.

### Share a file with a client

- **Method**: POST
- **URL**: `https://your-install.example.com/api/v1/files/128/assignments`
- **Data**:

```json
{ "type": "client", "id": 42 }
```

Use `"type": "group"` to share with a whole group instead.

This does everything the web interface does. The client gets a notification, and the share email
goes out on the usual short delay.

Sharing the same file with the same person twice changes nothing, so it is safe if Zapier retries.

### Upload a file

- **Method**: POST
- **URL**: `https://your-install.example.com/api/v1/files`
- **Content-Type**: leave it as form data, not JSON
- **Data**: a `file` field holding the file from an earlier step, and a `name` field for what it
  should be called

This works for ordinary files. Very large ones need the resumable upload, described under
**Large files** near the end of this page.

### Add a client to a group

- **Method**: POST
- **URL**: `https://your-install.example.com/api/v1/groups/7/members`
- **Data**:

```json
{ "user_id": 42 }
```

Only clients can be group members. Passing a staff account is refused.

---

## Three complete examples

### 1. Tell the team in Slack when a client downloads something

- **Trigger**: Webhooks by Zapier → Retrieve Poll →
  `GET /api/v1/activity?action[]=file.downloaded`
- **Action**: Slack → Send Channel Message

Use the fields from the trigger:

> **{{actor__name}}** downloaded **{{subject__name}}**

This is the one people ask for most, and it is only possible through the activity feed — a download
leaves no trace on the file itself.

### 2. Turn a form submission into a client account

- **Trigger**: your form app (Typeform, Google Forms, whatever you use)
- **Action 1**: Zapier → Formatter → Utilities → Random Value, to generate a password
- **Action 2**: Webhooks by Zapier → Custom Request → `POST /api/v1/clients`

Map the form's name and email fields into the JSON body. Then have ProjectSend email the new client
a password reset link, or send them one yourself in a third step.

### 3. Put a Dropbox file into ProjectSend and share it

- **Trigger**: Dropbox → New File in Folder
- **Action 1**: Webhooks by Zapier → Custom Request → `POST /api/v1/files`, with the Dropbox file in
  the `file` field
- **Action 2**: Webhooks by Zapier → Custom Request → `POST /api/v1/files/{{id}}/assignments`, using
  the `id` that came back from action 1

---

## Things to know before you rely on it

### Decide about expiry before you build

Tokens expire by default — 90 days to start with, up to a year. Zapier cannot renew one on its own,
so when it expires the Zap stops. Zapier shows errors, but nobody gets a phone call, and the usual
first symptom is somebody noticing weeks later that something stopped happening.

There is a **Never expires** option on the token screen, which avoids that entirely. It is off by
default on purpose: a token nobody ever rotates is a password nobody ever changes. For an
integration that has to keep running unattended it is usually the right choice — pick it knowingly,
give the token only the permissions the Zap needs, and revoke it the day the Zap goes away.

If you do set an expiry, put a reminder in your calendar for a week before. When the day comes,
create a new token and paste it into the Zap; you do not need to rebuild anything.

### Reacting to a deletion

"When a file is deleted" works, but only through the activity feed:
`…/api/v1/activity?action[]=file.deleted`.

It cannot work any other way. Zapier finds out about things by asking for a list, and a deleted file
simply stops appearing in one — there is no way to tell that apart from it never having been there.
The activity feed records the moment instead.

One thing to know when you build it: a deletion has no `subject`, because the file is already gone
when the entry is written. The name is in `context.name`.

### There is a limit on how often you can call

Each token can make **60 requests a minute** on a self-hosted installation, or **120** on the hosted
edition. Uploads are lower: **20** and **30** a minute.

Zapier's polling is well within that. You would only run into it with a Zap that loops over hundreds
of items at once. If you do, the response tells you how long to wait, and Zapier retries on its own.

### Large files

The single-request upload works up to whatever this installation allows, and it cannot resume. If
the connection drops halfway, the whole thing starts again.

For very large files there is a resumable upload that sends the file in parts. It takes four steps
and is more than most Zaps need, so it is not covered here — the **API** page inside ProjectSend
describes it.

### Only staff accounts

A token belongs to a staff account, not a client account. Clients cannot create tokens, and a Zap
always acts as the staff member who made the token. Everything it does appears under that person's
name in the activity log.

### Test on something harmless first

A Zap that creates or shares does the real thing immediately. Point your first version at a test
client and check the result before you let it run on real work.

---

## Make, n8n, and other tools

Nothing above is specific to Zapier. Any tool that can send an HTTP request will work the same way:

- **Make** — use the HTTP module, and its "Iterator" to walk the `data` array.
- **n8n** — use the HTTP Request node. Its built-in pagination understands the `links.next` URL.
- **Power Automate** — use the HTTP action.
- **A script of your own** — see the **API** page inside ProjectSend, or import
  `/api/v1/openapi.json` into Postman or Insomnia.

If your tool can keep track of where it got to between runs, there is a better way to poll than
asking for the newest items each time. Pass `updated_since` with the last timestamp you saw, and you
will get everything changed since then, oldest first, with nothing skipped. The **API** page explains
it.
