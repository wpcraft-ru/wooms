# `wp eval` and `wp eval-file`

In this repository, run examples through `npx wp-env run cli wp ...`.

Use `wp eval` for short snippets that inspect the current WordPress runtime without opening an interactive shell.

- Good fits:
  - read-only checks against options, hooks, posts, users, plugins, or WooCommerce state
  - quick verification of object shapes or callback registration
  - targeted debugging when reproducing a bug inside the real WP bootstrap
- Avoid for:
  - long scripts that are hard to review inline
  - repeated automation; prefer a checked-in script or `wp eval-file`
  - risky write operations unless the user explicitly asked for them and the environment is confirmed safe

Examples:

- `npx wp-env run cli wp eval 'var_dump( get_option( "home" ) );'`
- `npx wp-env run cli wp eval 'global $wpdb; var_dump( $wpdb->prefix );'`
- `npx wp-env run cli wp eval-file /tmp/debug-hooks.php`

Reference:

- https://developer.wordpress.org/cli/commands/eval/
