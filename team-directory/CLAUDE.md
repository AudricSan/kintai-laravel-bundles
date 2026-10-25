# CLAUDE.md — team-directory

Bundle officiel « Annuaire des collègues » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

## Architecture

- `bundle.json` — manifest read by Kintai's bundle installer/registry: slug,
  version, `kintai_core` compatibility range, `entry_class`.
- `src/TeamDirectoryBundle.php` — the entry point
  (`kintai\Bundles\Installed\TeamDirectory\TeamDirectoryBundle`, extends
  `kintai\Core\BundleContract\Bundle`). A full extraction: `register()` only
  wires up `Views/` and `routes.php`, no repository binding — the controller
  only reads `UserRepositoryInterface`/`StoreRepositoryInterface`/
  `StoreUserRepositoryInterface`, all of which are Core-resident regardless
  of this bundle's state, and no Core component depends on anything specific
  to this bundle. Disabling/uninstalling it has zero side effects elsewhere.
- `routes.php` — one group: `/team*`, `AuthMiddleware` only (any authenticated
  user, no RBAC — self-service in the same spirit as `/profile`): `/team`
  (directory list) and `/team/{id}` (a colleague's extended profile).
- `src/Controllers/Web/TeamDirectoryController.php` — lists colleagues
  scoped to shared stores (an Owner sees the whole organization), excluding
  anyone with `show_in_directory = 0` (on `users`, opt-out from `/profile`,
  on by default) entirely. `Views/team-directory-show.blade.php` then further
  gates the contact fields it renders on `share_email`/`share_phone`/
  `share_mobile_phone` (also on `users`, set from `/profile`, all off by
  default) — the directory listing itself never shows contact details,
  only the individual profile page does, and only for fields that colleague
  explicitly opted into sharing.
- `Views/team-directory.blade.php` (list), `Views/team-directory-show.blade.php`
  (single profile). Registered under the `team-directory::` view namespace.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. Keys used by `TeamDirectoryBundle` itself
  (`bundle_team_directory`, `bundle_team_directory_desc`) must exist in
  every locale file.

## Views and the Content Security Policy

Kintai (Core 0.3.0+) sends `script-src 'self' 'nonce-…'`, without `'unsafe-inline'`. In `Views/` and any HTML
built in `src/`, an inline event attribute (`onclick=`, `onchange=`, `onsubmit=`, `oninput=`…), a `javascript:`
link or a `<script>` without a nonce is blocked by the browser **silently** — nothing fails on the server, so no
other check would catch it. Write declarative attributes handled by the Core's `csp-actions.js` instead:
`data-on-click="fn"` / `data-on-change="fn"` / `data-on-input="fn"` (+ `data-args='["a", "@value"]'`, with
`@this`/`@value`/`@checked`), `data-submit-on-change`, `data-submit-form="id"`, `data-goto="url"`,
`data-stop-propagation`, and `data-confirm="message"` on a form or a submit button (global confirmation modal).
`data-on-*` only calls a function *you* defined on `window`; browser built-ins are refused on purpose. A needed
inline script uses `<script nonce="{!! function_exists('csp_nonce') ? csp_nonce() : '' !!}">` (views are Blade templates). `Button::attrs()`
already escapes values: pass raw ones. `tests.yml` fails when a violation reappears. Full reference: [Content
Security Policy](https://github.com/AudricSan/Kintai/blob/develop/docs/creating-a-bundle.md#content-security-policy-no-inline-scripts) in Kintai's bundle guide.
