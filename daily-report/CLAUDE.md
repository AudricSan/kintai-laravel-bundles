# CLAUDE.md — daily-report

Bundle officiel « Rapports journaliers » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

## Architecture

- `bundle.json` — manifest read by Kintai's bundle installer/registry: slug,
  version, `kintai_core` compatibility range, `entry_class`.
- `src/DailyReportBundle.php` — the entry point
  (`kintai\Bundles\Installed\DailyReport\DailyReportBundle`, extends
  `kintai\Core\BundleContract\Bundle`). `register()` binds
  `DailyReportRepositoryInterface` to `DatabaseDailyReportRepository` as a
  singleton, then wires four Kintai Core services this bundle depends on but
  doesn't own — `DailyReportPermissionService`, `DailyReportPdfService`
  (mPDF generation), `DailyReportMailService`, and
  `DailyReportAutoValidateService` (the cron auto-validate job) — before
  calling `loadViewsFrom(..., 'daily-report')` and `loadRoutesFrom(routes.php)`.
  Only `DailyReportRepositoryInterface` is owned by this bundle; the four
  services above stay in Kintai Core (`kintai\Core\Services\*`) because
  nothing else currently depends on them, but they're still Core-resident —
  don't assume the whole feature moves with this repo.
- `routes.php` — three groups: `/admin/stores/{id}/daily-reports*` (create,
  list, edit, submit/validate workflow, PDF preview/download, settings —
  `AuthMiddleware` + `PermissionMiddleware`, several routes use the
  `membership` permission rule so a plain employee can manage their own
  store's reports without an explicit RBAC grant), `/admin/daily-reports`
  (Owner-wide list across all managed stores), and `/api/v1/daily-reports*`
  (REST CRUD + submit/validate, `ApiAuthMiddleware` + `ApiPermissionMiddleware`).
- `src/Controllers/Web/DailyReportController.php` — the bulk of the bundle:
  CRUD + a draft → submitted → validated workflow, PDF preview/download
  (delegates byte generation to `DailyReportPdfService`), manual/automatic
  mail sending on validation (`DailyReportSettings.auto_send_on_validate`,
  stored as JSON on the `stores` row), and per-store settings
  (reminder/auto-validate/no-report times, visible columns, cumulative mode).
  Notifies store managers holding `daily_reports.approve` on submission.
- `src/Controllers/Api/DailyReportController.php` — REST CRUD mirroring the
  web controller's workflow actions.
- `Views/*.php` — `daily-reports.blade.php` (per-store list), `daily-reports-all.blade.php`
  (Owner cross-store view), `daily-report-form.blade.php` (create/edit),
  `daily-report-show.blade.php` (detail), `daily-report-settings.blade.php`,
  `daily-report-pdf.blade.php` (mPDF template). Registered under the
  `daily-report::` view namespace.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. Keys used by `DailyReportBundle` itself
  (`bundle_daily_report`, `bundle_daily_report_desc`) must exist in every
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
