# Debugging WP-CLI

In this repository, run WP-CLI examples through `npx wp-env run cli wp ...`.

## WP not found / wrong WP root

- Run `npx wp-env run cli wp --info`.
- Provide `--path=<wordpress-root>` if WP is not in the current directory.
- Confirm `wp-config.php` exists in the expected root.

## HTTP/URL targeting issues

- On multisite, include `--url=<site-url>` for site-specific actions.

## Permission/file ownership issues

- If running in containers, ensure you’re using the same user/volume mapping as the app.
- Avoid `--allow-root` unless you understand the environment and have no alternative.

