Two authentication methods are accepted. You must use one on every request that is not public.

**API Key** — for external clients. Issue a key under [API Keys]({{admin_url}}) in wp-admin and send it as a request header:

```http
X-API-Key: <your-key>
```

**Cookie + Nonce** — for browser / wp-admin sessions. Pass a nonce created with the `wp_rest` action. The **Try it** button on this page injects it automatically.

```http
X-WP-Nonce: <nonce>
```

Generate one in PHP with `wp_create_nonce( 'wp_rest' )` or in JS via `wpApiSettings.nonce`.
