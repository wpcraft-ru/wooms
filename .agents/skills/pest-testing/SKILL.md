---
name: pest-testing
description: >-
    Tests this project with Pest 4 in a WordPress/WooCommerce wp-env setup. Activates when writing or
    updating tests, debugging failures, running TDD loops, adding assertions/mocks/datasets, or when
    the user asks to verify plugin behavior.
---

# Pest Testing For WooMS (WordPress + WooCommerce)

## When to Apply

Activate this skill when:

- Creating or modifying tests for plugin logic in `includes/`
- Debugging failing Pest tests in wp-env
- Running TDD cycles for WordPress/WooCommerce integration behavior
- Adding assertions, datasets, stubs, fixtures, and mocks
- Verifying regressions before finishing code changes

## Documentation

Use Pest documentation for syntax and expectations, but always align commands and bootstrap with this repository.

## Project Context (Important)

- This repository is a WordPress plugin, not Laravel.
- Tests are executed through WP-CLI command `wp test:wooms`, defined in `tests/add-wp-cli.php`.
- Pest runs inside the wp-env CLI container, with WordPress bootstrapped from `tests/bootstrap.php`.
- WooCommerce is expected to be installed/active in the local dev environment.
- Do not use Laravel-specific commands like `php artisan test` or `php artisan make:test`.

## Test Locations

- Primary Pest suite is configured in `phpunit.xml`.
- Current suite points at `tests/includes/`.
- Use existing project structure when adding tests:
    - `tests/includes/` for active Pest tests.
    - `tests/tdd/` for focused debug/TDD files when requested.
    - Legacy folders like `tests/testeroid/` exist; do not migrate or delete them unless explicitly asked.

## Running Tests

Prefer the smallest relevant run first, then expand to full suite.

- From host (preferred high-level command):
    - `make test`
- Direct via wp-env:
    - `npx wp-env run cli wp test:wooms`
    - `npx wp-env run cli wp test:wooms --filter="adds numbers correctly"`
    - `npx wp-env run cli wp test:wooms tests/tdd/debug.php`

- Ensure dev dependencies are installed inside wp-env plugin directory when needed:
    - `npx wp-env run cli --env-cwd=wp-content/plugins/wooms composer install`

- If test data/environment prep is needed, run seeding command:
    - `npx wp-env run cli wp test:wooms:data-seeding`
    - Optional flags: `--force`, `--clean`

## Writing Tests

Use Pest style and concise expectations.

<code-snippet name="Basic WooMS Pest Test" lang="php">

it('loads wordpress core functions', function (): void {
        expect(function_exists('get_post'))->toBeTrue();

        $posts = get_posts();

        expect($posts)->toBeArray();
});

</code-snippet>

Guidelines:

- Keep tests deterministic and independent.
- Prefer fixture-based input from `tests/data/` for payload-heavy scenarios.
- Cover WooCommerce behavior with realistic WP/WC state where relevant.
- Do not remove existing tests without explicit approval.

## Assertions

Prefer explicit Pest expectations over vague checks.

Good examples:

- `expect($value)->toBe(...)`
- `expect($array)->toHaveCount(...)->toContain(...)`
- `expect(function_exists('wc_get_product'))->toBeTrue()` when WC functions are required.

Avoid brittle assertions tied to localized text unless that exact text is the behavior under test.

## Mocking, Stubs, and Fixtures

- For plugin logic that can run with full WP bootstrap, prefer realistic integration-style tests first.
- For isolated unit-style behavior, use available dev tooling (including Brain Monkey if appropriate).
- Keep external API payloads as local fixtures in `tests/data/` and avoid live network calls in tests.

## TDD Workflow For This Repo

1. Reproduce with a focused run (`--filter` or single file).
2. Implement the minimal code change.
3. Re-run focused test.
4. Run broader suite (`wp test:wooms`) before finishing.
5. If scenario depends on WooCommerce baseline config, run `wp test:wooms:data-seeding` first.

## Common Pitfalls

- Using Laravel commands (`artisan`) in this WordPress project.
- Running tests outside wp-env and getting missing WordPress/WooCommerce symbols.
- Forgetting to install composer dependencies in the wp-env plugin directory.
- Assuming all `tests/` directories are part of active phpunit suite without checking `phpunit.xml`.
- Adding tests that rely on mutable global state without cleanup.

## Definition Of Done (Testing)

- Relevant tests added/updated in the appropriate project folder.
- Focused test run passes.
- Full `wp test:wooms` run passes (unless user asked otherwise).
- No accidental changes to unrelated legacy tests.
