# API — module endpoints

Optional modules add endpoints under `/api/v1/modules/{module}/…`. They are **not** in the committed
[`api/openapi.json`](api/openapi.json): `OpenApiContractTest` skips `api/v1/modules/*`, because that
document is served unauthenticated and has to be identical on every installation, while a module's
paths exist only where the module does. This file is the documentation for the modules that ship
with the application; a module living in its own package documents its surface in its own repository.

Everything [`api-guide.md`](api-guide.md) describes — bearer-token authentication, ability checks,
RFC 7807 errors, rate limits — applies unchanged. The core supplies all of it; none of it is
restated per module.

`GET /api/v1/me` lists the modules an installation actually carries, so an integration can check for
`branding` before calling anything below rather than guessing from a 404.

---

## Branding

Mounted at `/api/v1/modules/branding`, behind `capability:branding.customize`. Every edition has
that capability; a hosted plan can have it subtracted from its environment, in which case these
paths answer 403 like any other gated route.

Both endpoints are read-only. Uploading either image is a multipart flow whose content-sniffing
rules only make sense behind a file picker, and settings writes follow the rule that there is never
a generic `PATCH /settings`.

Hiding the attribution line is not here. That switch is Cloud-only, has no API surface, and its
column is written by the `cloud-modules` package.

### `GET /logo` — ability: `edit_settings`

The logo shown in place of the default sidebar icon.

```json
{
  "data": {
    "logo_url": "https://example.test/storage/branding/9f3c….png",
    "updated_at": "2026-08-07T16:02:30+00:00"
  }
}
```

`logo_url` is `null` when no logo has been uploaded, which is the normal state rather than an error.

### `GET /watermark` — ability: `edit_settings`

The mark stamped onto the thumbnails and previews clients and anonymous public visitors see. What
this installation's own staff see goes unmarked, and the stored files — including every download —
are never altered.

```json
{
  "data": {
    "enabled": true,
    "image_url": "https://example.test/storage/branding/a68a….png",
    "position": "bottom-right",
    "size": 35,
    "opacity": 55
  }
}
```

| Field | Meaning |
|---|---|
| `enabled` | Whether client- and public-facing images are *actually* being watermarked. False whenever nothing is drawn — including when the toggle is on but its image has since been removed. It answers "is this installation watermarking?", not "which way is the switch pointing?" What staff see is never marked regardless. |
| `image_url` | The artwork, or `null` if none was ever chosen. |
| `position` | One of `top-left`, `top-center`, `top-right`, `middle-left`, `center`, `middle-right`, `bottom-left`, `bottom-center`, `bottom-right`. |
| `size` | Percentage of the image the mark is scaled to fit inside, keeping its proportions — so a thumbnail and a preview carry the same design at different scales. 5–100. |
| `opacity` | Percentage. 1–100. |

An installation that has never opened the branding screen answers with the defaults it would start
from (`enabled: false`, `bottom-right`, `30`, `60`) rather than a payload of nulls.

---

## Deferred, on purpose

- **Writes for either image.** See above.
- **Rendered thumbnails themselves.** Already deferred by the host ([`api-todo.md`](api-todo.md));
  the watermark endpoint exists so an integration generating its own derivative images can reproduce
  the installation's mark, not as a step toward serving thumbnails over the API.
