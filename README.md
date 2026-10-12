# SeviaFlow UI

Composer package: `seviaflow/ui`

Repository: `https://github.com/seviaflow/ui`

## Beta compatibility

The free beta supports Laravel 12 on PHP 8.2+ and Laravel 13 on PHP 8.3+. Laravel 11 is outside this beta's support scope; its security support ended on March 12, 2026. CI covers these framework targets with Livewire 3 and 4. Package unit tests use SQLite; the Northstar integration and browser suites use MariaDB 10.11. PostgreSQL is not yet a verified beta target.

UI is the optional Livewire and Blade operator interface over `seviaflow/core`. It owns web routes, views, frontend assets, navigation, dashboards, saved views and auth/profile screens.

UI depends on Core and Livewire. It does not require `seviaflow/api`.

## Authentication and Panel Access

The host must configure Core's `seviaflow.permissions.admin_gate`; the default
missing hook fails closed. The callable is checked for every protected request
and Livewire update, so removing a user's panel role denies subsequent reads and
mutations. Login authenticates through the configured Laravel guard, then applies
the same panel gate before creating a panel session.

Login throttling is enabled by default. If the host enables 2FA, the UI uses a
real Fortify backend or the explicit `auth.two_factor_login.verify` and
`consume_recovery_code` adapters. Without one of these adapters, profile and
login protection are unavailable rather than simulated. Password, 2FA and
browser-session security operations require a fresh `current_password` check.

### Fortify two-factor setup

Install `laravel/fortify:^1.30` in the host. It is optional for UI consumers;
the UI development dependency runs the real Fortify integration tests. Add
`Laravel\Fortify\TwoFactorAuthenticatable` to the host user model, hide
`two_factor_secret` and `two_factor_recovery_codes`, and add nullable text columns
with those names plus a nullable `two_factor_confirmed_at` timestamp.
Keep the secret and recovery-code attributes encrypted as Fortify stores them;
do not add an `encrypted` Eloquent cast on top of Fortify's encryption.

Configure `Features::twoFactorAuthentication(['confirm' => true])` in the host
Fortify feature list. Fortify's provider must be registered. When SeviaFlow owns
the host's login/profile routes, call `Fortify::ignoreRoutes()` from the host
provider's `register()` method. If the host already owns authentication, retain
its routes and ensure its login also enforces 2FA; SeviaFlow cannot enforce an
extra factor on sessions created by a different login implementation.

An operator enables 2FA at `/admin/profile/two-factor-authentication`, scans the
QR code, and confirms with a current password and TOTP. The next login requires
password plus a TOTP or a one-use recovery code. Recovery-code regeneration and
disabling 2FA also require the current password. The built-in login challenge
expires after 300 seconds (`auth.two_factor_login.challenge_timeout_seconds`),
and password changes or panel-access revocation invalidate it. Invalid codes
are throttled and never flashed back into the form. Fortify's TOTP replay checks
remain active; a setup code cannot immediately be reused to log in.

Custom login adapters remain supported. `verify` validates TOTP and
`consume_recovery_code` must atomically consume a recovery code; a rejected TOTP
can fall back to recovery. Custom `twoFactorSecret()` / `getTwoFactorSecret()`
methods return decrypted secrets. The profile enrollment UI requires a
Fortify-compatible user model and backend. Northstar demonstrates the complete
Fortify configuration and includes a database integration test.

## Record toolbar

Resource lists use one toolbar for search, filters, saved views and table tools.
Selecting a saved view applies it immediately. Its menu also contains saving
and deleting views. Bulk actions appear in the same toolbar after selecting
records; confirmation remains visible until confirmed or cancelled.

Column visibility and order, table density, records per page and trash scope
live in Table settings. Import and export are available under More when the
operator has permission. The
toolbar wraps on narrow screens, and dropdowns support keyboard navigation,
Escape and clicking outside. On phones, filters and views use icon buttons
with accessible labels. Re-publish package assets when updating an application
that uses published UI assets:

```bash
php artisan vendor:publish --tag=seviaflow-ui-assets --force
```

If the application has published or customized package views, also merge the
updated `livewire/resource-index.blade.php` into its view override.

## Installation Status

SeviaFlow is pre-release. This package is licensed under the [MIT License](LICENSE), which permits commercial use, modification and redistribution with its copyright and license notice. Release tags, distribution endpoints and support scope are documented separately.

The intended operator profile is Core + UI without API or Hub. The owner approved the free Core/UI/API/DevTools/Framework edition on 2026-10-10. MIT usage rights apply to this package; publication, support scope and the first stable tag remain release decisions.

```bash
composer require seviaflow/ui:^0.1@dev
```

## Local Checks

When testing this repository before Packagist registration, register a temporary local Core repository in your Composer root instead of committing a path repository:

```bash
composer validate --strict
composer config repositories.seviaflow-core '{"type":"path","url":"../core","options":{"symlink":true,"versions":{"seviaflow/core":"0.1.x-dev"}}}'
composer update
composer check
npm ci
npm run build
```

Assets build to `public/dist/seviaflow.css` and `public/dist/seviaflow.js`; those distribution assets are committed because they are part of the package installation strategy.

## Free edition scope

Core, UI, API, DevTools and Framework are free. API requires no paid license or runtime Hub activation. Copilot remains an optional paid module and is excluded from the free edition. The five free packages use MIT; Copilot retains its separate license. Repository visibility and published artifacts are separate from the usage license.
