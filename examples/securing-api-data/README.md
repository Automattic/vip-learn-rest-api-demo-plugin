# Securing API data input and output

Runnable versions of the code in the VIP Learn lesson
[Best Practices for Securing API Data Input and Output](https://learn.wpvip.com/lesson/best-practices-for-securing-api-data-input-and-output/).

Each file is the lesson snippet as it should be written, so a reader can follow
the link from the lesson and get code that actually runs. Functions are global
and prefixed rather than namespaced, so the files match the lesson line for
line.

| File | Lesson section |
| --- | --- |
| `01-validating-input.php` | Validating Input Data |
| `02-validate-callback-closure.php` | Validating Input Data — custom `validate_callback` |
| `03-sanitizing-input.php` | Sanitizing Input Data |
| `04-output-escaping.php` | Escaping Output Data |
| `05-limiting-data-exposure.php` | Limiting Data Exposure |
| `06-response-schema.php` | Using JSON Schema for Response Validation |
| `07-nonces-and-cookie-auth.php` | Using Nonces for CSRF Protection |

## Enabling them

The files register live routes and filters, so they are opt-in. Add this to
`wp-config.php` and the plugin will load them:

```php
define( 'VIP_LEARN_LOAD_EXAMPLES', true );
```

## Verifying them

`verify-examples.php` exercises every example against the running site and
exits non-zero if a check fails. One check deliberately triggers a nonce failure, so WP-CLI prints a harmless `Cannot modify header information` warning:

```bash
wp eval-file wp-content/plugins/vip-learn-rest-api-demo-plugin/examples/securing-api-data/verify-examples.php
```

## Four things worth knowing

These came out of testing the lesson's original snippets, and each one is a
silent failure rather than an obvious one:

1. **A `validate_callback` receives three arguments** — the value, the
   `WP_REST_Request`, and the parameter name. Naming a built-in PHP function
   such as `is_numeric` throws `ArgumentCountError` on PHP 8, because internal
   functions reject surplus arguments, and the endpoint returns a 500. A
   user-defined function (`rest_is_integer`, say) is fine, which is what makes
   this easy to miss. Custom logic belongs in a closure.

2. **`wp_insert_post()` returns `0` on failure**, not a `WP_Error`, unless its
   second argument is `true`. An `is_wp_error()` check against the default
   return value never fires.

3. **Core already does the CSRF check for cookie-authenticated requests.**
   It reads `$_REQUEST['_wpnonce']` ahead of the `X-WP-Nonce` header and
   verifies it against the `wp_rest` action, before any callback runs. Sending
   your own nonce under the `_wpnonce` name therefore fails that check and
   returns `403 rest_cookie_invalid_nonce`. Without a valid nonce the request
   is downgraded to anonymous rather than failing open, so the
   `permission_callback` is what protects the endpoint. `check_admin_referer()`
   has no place in a REST callback either — it calls `wp_nonce_ays()`, which
   renders an HTML page and exits.

4. **`schema` is a route option, not an endpoint option.** When the array
   passed to `register_rest_route()` has `callback` at its top level, core
   treats the whole array as one endpoint and wraps it — carrying `schema`
   inside, where nothing reads it. The schema is then silently missing from
   the `OPTIONS` response. Nest the endpoint in its own array and leave
   `schema` beside it.
