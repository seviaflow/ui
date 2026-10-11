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
