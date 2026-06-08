## Overview

This reference is auto-generated from every REST route currently registered on **{{site}}**.
It covers both the custom `wpdev/v1` endpoints built into this theme and the standard `wp/v2`
WordPress core routes.

**Base URL:** `{{rest_base}}`

---

## Authentication

Two authentication methods are accepted. You must use one on every request that is not public.

### API Key *(external clients)*

Issue a key under [API Keys]({{admin_url}}) in wp-admin, then pass it as a header:

```http
X-API-Key: <your-key>
```

### Cookie + Nonce *(wp-admin / browser)*

When making requests from a logged-in browser session, pass a nonce created with the
`wp_rest` action. The **Try it** button on this page injects the nonce automatically.

```http
X-WP-Nonce: <nonce>
```

Generate one in PHP with `wp_create_nonce( 'wp_rest' )` or in JS via `wpApiSettings.nonce`.

---

## Error Format

All errors follow the WordPress error envelope:

```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": { "status": 403 }
}
```

| Status | Code example | Meaning |
|--------|-------------|---------|
| `400` | `rest_invalid_param` | Bad request — invalid or missing parameters |
| `401` | `rest_not_logged_in` | Unauthorized — no valid session or API key |
| `403` | `rest_forbidden` | Forbidden — authenticated but not permitted |
| `404` | `rest_post_invalid_id` | Not found — resource does not exist |
| `500` | `rest_unknown_error` | Internal server error |

---

## Versioning

This API follows WordPress REST API conventions. Breaking changes are introduced in new
namespace versions (`/v2`, `/v3`, …). The current theme version is `{{version}}`.
