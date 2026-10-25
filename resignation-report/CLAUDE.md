# CLAUDE.md — resignation-report

Bundle officiel « Rapport de démission » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

## Architecture

- `bundle.json` — manifest read by Kintai's bundle installer/registry: slug,
  version, `kintai_core` compatibility range, `entry_class`.
- `src/ResignationReportBundle.php` — the entry point
  (`kintai\Bundles\Installed\ResignationReport\ResignationReportBundle`,
  extends `kintai\Core\BundleContract\Bundle`). `register()` binds
  `ResignationReportRepositoryInterface` to
  `DatabaseResignationReportRepository` as a singleton, then calls
  `loadViewsFrom(..., 'resignation-report')` and
  `loadRoutesFrom(routes.php)`. Unlike Hiring Report, no Kintai Core
  component depends on this interface — uninstalling this bundle removes
  the feature entirely, data included.
- `routes.php` — one group under `/admin`: cross-store list + JSON/PDF
  export (`admin.reports.resignation*`) and per-store CRUD + PDF +
  "reactivate account" (`admin.stores.resignation_reports.*`), all gated by
  `AuthMiddleware` + `PermissionMiddleware` (`resignation_reports.*`
  permissions, `reactivate` uses `employees.update`).
- `src/Controllers/Web/AdminResignationReportController.php` — CRUD, PDF
  preview/download, JSON/PDF bulk export, and reactivating a resigned
  employee's account. Shares CRUD/PDF logic with the Hiring/Salary report
  controllers (in Kintai Core) via the `HasStaffReportCrud` trait
  (`kintai\UI\Controller\Web\Staff\HasStaffReportCrud`) — a plain PHP trait,
  not a DI-resolved service, so it works the same regardless of where this
  controller class lives.
- `Views/reports-resignation*.blade.php` — list, form, detail, PDF template
  (single + bulk export). Registered under the `resignation-report::` view
  namespace.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. Keys used by `ResignationReportBundle` itself
  (`bundle_resignation_report`, `bundle_resignation_report_desc`) must exist
  in every locale file.

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
