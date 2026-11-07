# Panel localization (English and Polish)

SeviaFlow UI uses Laravel's current application locale. No template rewriting,
rendered-HTML substitution, or separate UI locale is required:

```dotenv
APP_LOCALE=pl
APP_FALLBACK_LOCALE=en
```

An application can also call `App::setLocale('en')` in its locale middleware.
That middleware must run for both page requests and Livewire updates. Switching
locale does not require recompiling Blade views. The package does not add a
language picker or persist a user's language preference.

## Translation contract

- `seviaflow-ui::ui.*` retains the existing navigation, state and action keys.
- `seviaflow-ui::panel.*` covers resource forms and controls, tables, pagination,
  action confirmations, authentication, profile screens and package messages.
- `seviaflow-ui::passwords.*` supplies password-broker messages when the host
  has no translation for that status in the current locale. Existing host
  `passwords.*` translations take precedence; custom broker statuses still work.
- Counts use Laravel `trans_choice`; variable text uses Laravel placeholders.
- Navigation and dashboard caches include locale and fallback locale.
  Clearing the navigation cache invalidates all locale variants.

Override package text using the standard Laravel directory structure, for
example `lang/vendor/seviaflow-ui/pl/panel.php`:

```php
<?php

return [
    'save' => 'Zachowaj',
];
```

Optionally publish the catalogs:

```sh
php artisan vendor:publish --tag=seviaflow-ui-translations
```

Application resource/field/action labels, custom messages, record values,
workflow state identifiers, filter values, request names and export schemas
remain application-owned. Supply localized resource labels in resource
definitions. Panel headings now reuse the resource's navigation label instead
of always deriving English-looking headings from its URL slug. Custom branding
and resource-specific descriptions remain host application customizations.

Laravel validation rule messages remain under the host's `validation.*`
translations. Install the application's Polish validation catalog when Polish
rule messages are required. The package translates its validation summary and
does not replace validation rules, field attributes or custom validation text.

## JavaScript and published assets

Blade supplies translated text in escaped `data-*` attributes on the form and
rich-text control. JavaScript reads the current element each time it needs a
message, including after Livewire updates or navigation. It uses `textContent`
for editor status messages. No translated JavaScript bundle or injected global
script is needed. This covers leaving a dirty form, editor loading/failure and
the link URL prompt. Browser-native `beforeunload` dialog text is controlled by
the browser and cannot be replaced by the package.

Previously published views remain usable with the new bundle: missing message
attributes fall back to the previous English messages. Full Polish JavaScript
messages require the updated views as well as the updated compiled bundle.

## Compatibility and removing SeviaConsent's PolishPanel

This change does not alter form submit methods, field paths, validation rules,
permissions, persistence or redirects. English remains the English catalog's
output; applications already running with `pl` now receive native Polish panel
text. Published view overrides continue to take precedence and therefore need
review. Old unnamespaced JSON entries used only by the adapter do not override
the new namespaced keys.

`0.1.0-beta.1` does **not** contain this change. Remove `PolishPanel` only after
installing a release (or an explicitly pinned commit) containing this patch:

1. Update `seviaflow/ui`; keep the tested Core version and the application's
   own localized resource definitions and validation translations.
2. Review any `resources/views/vendor/seviaflow-ui` overrides and merge the
   translation calls and new message attributes. Applications without published
   overrides use the package views directly.
3. Publish the updated assets and invalidate the application's asset/CDN cache:
   `php artisan vendor:publish --tag=seviaflow-ui-assets --force`.
   Remove the host's JavaScript source-string replacement for the leave prompt;
   retain its normal asset cache-busting strategy.
4. Remove the `PolishPanel::register()` call, Blade source-preparation hook and
   render-time translation wrappers. Preserve any desired SeviaConsent branding
   or resource-specific descriptions as ordinary host view data/composers,
   independently of localization. Do not blindly delete the host's `pl.json`:
   application-owned labels may still use it.
5. Clear compiled views and UI caches (`php artisan view:clear` and the host's
   normal SeviaFlow cache-clear procedure).
6. Run SeviaConsent's language and browser regression tests **with the adapter
   disabled**: login and failures, profile, resource lists and pagination,
   configurator validation and all save modes, inline relations, confirmation
   actions and dirty-form navigation. Repeat the language checks with `en`.

After these checks pass, the adapter class and its adapter-only dictionary
entries can be deleted safely. Merging this package patch alone does not verify
the SeviaConsent deployment or remove its other integration workarounds.

## Verification

Package tests cover both locales, runtime locale switching with compiled Blade,
catalog key/placeholder parity, every referenced translation key, fallback and
host overrides, password-broker and credential errors, validation and persistence,
edit/create-another behavior, escaping and unchanged user values, pagination,
selection and locale-separated cache invalidation. Node tests cover both
languages in navigation cancellation/acceptance, current-element message reads
and compatibility with older published views.

Run `composer check`, `npm test` and `npm run build`. Committed `public/dist`
assets must match a clean build. Browser checks are still required in the host
application before removing its adapter.
