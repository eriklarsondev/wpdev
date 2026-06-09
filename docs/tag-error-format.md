All errors follow the WordPress error envelope:

```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": { "status": 403 }
}
```

| Status | Code | Meaning |
|--------|------|---------|
| `400` | `rest_invalid_param` | Bad request — invalid or missing parameters |
| `401` | `rest_not_logged_in` | Unauthorized — no valid session or API key |
| `403` | `rest_forbidden` | Forbidden — authenticated but not permitted |
| `404` | `rest_post_invalid_id` | Not found — resource does not exist |
| `500` | `rest_unknown_error` | Internal server error |
