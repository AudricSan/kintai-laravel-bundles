# CLAUDE.md — store-photos

Bundle officiel « Photos de magasin » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

## Architecture

- `bundle.json` — manifest read by Kintai's bundle installer/registry: slug
  (`store-photos`, unchanged from the legacy monorepo bundle), version,
  `kintai_core` compatibility range, `entry_class`.
- `src/StorePhotoBundle.php` — the entry point
  (`kintai\Bundles\Installed\StorePhoto\StorePhotoBundle`, extends
  `kintai\Core\BundleContract\Bundle`). **Unlike most bundles, `register()`
  does not bind any repository**: `StorePhotoRepositoryInterface` stays bound
  in Kintai Core's own `RepositoryServiceProvider`, because
  `GithubUpdateService` (constructor-injected, unconditional) and the CLI
  script `scripts/consolidate-daily-photo-reports.php` both depend on it
  directly — they must keep working even if this bundle is disabled or
  uninstalled entirely. `register()` only wires up `Views/` and `routes.php`.
- `StorePhotoConsolidationService` (merges same-day photo submissions
  retroactively) also lives in Kintai Core (`kintai\Core\Services`), not in
  this repo — its only two callers (`GithubUpdateService`'s one-time
  best-effort catch-up on every self-update, and the CLI script above) are
  both Core-resident; `StorePhotoController` never uses it. Don't move it
  back here if you ever touch it — it would just reintroduce the fragile
  cross-repo dependency this extraction removed.
- `routes.php` — one group: `/admin/photos*` (`AuthMiddleware` +
  `PermissionMiddleware`, `photos.*` permissions) — list, settings,
  create/upload, rotate, show, delete.
- `src/Controllers/Web/StorePhotoController.php` — CRUD + image
  upload/rotation/cleanup. Uses Kintai's `storage_path()` helper for the
  upload directory (`storage/uploads/img/`) — never a relative path computed
  from `__DIR__`, since this controller's location on disk is no longer
  fixed once bundles are distributed independently.
- `Views/store-photos.blade.php`, `Views/store-photos-detail.blade.php`,
  `Views/store-photos-form.blade.php`, `Views/store-photos-settings.blade.php`.
  Registered under the `store-photos::` view namespace.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. Keys used by `StorePhotoBundle` itself
  (`bundle_store_photos`, `bundle_store_photos_desc`) must exist in every
  locale file.

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
