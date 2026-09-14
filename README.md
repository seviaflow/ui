# SeviaFlow UI

Composer package: `seviaflow/ui`

Repository: `https://github.com/seviaflow/ui`

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

SeviaFlow is pre-release. The `main` branch is aliased as `0.1.x-dev`; the first stable tag is expected to start at `0.1.0`.

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
