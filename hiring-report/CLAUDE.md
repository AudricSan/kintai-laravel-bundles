# CLAUDE.md — hiring-report

Bundle officiel « Rapport d'embauche » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

## Architecture

- `bundle.json` — manifest read by Kintai's bundle installer/registry: slug,
  version, `kintai_core` compatibility range, `entry_class`.
- `src/HiringReportBundle.php` — the entry point
  (`kintai\Bundles\Installed\HiringReport\HiringReportBundle`, extends
  `kintai\Core\BundleContract\Bundle`). Unlike most bundles, `register()`
  only calls `loadViewsFrom(..., 'hiring-report')` and
  `loadRoutesFrom(routes.php)` — it doesn't bind any repository. That's
  deliberate: `HiringReportRepositoryInterface` is bound by Kintai Core
  itself (`RepositoryServiceProvider`), because Core's `AdminUserController`
  depends on it directly to auto-generate a hiring report on every employee
  creation (standard form and Excel quick-create) — a side effect of the
  core user-management workflow that must keep working even when this bundle
  is uninstalled. Uninstalling/disabling this bundle removes only the
  browse/edit/PDF UI (this repo), never that auto-generation or the
  underlying data (which stay in Kintai Core regardless).
- `routes.php` — one group under `/admin`: `admin.reports.hiring` (all
  reports across managed stores) and `admin.stores.{id}.reports.hiring.*`
  (per-store list/create/show/edit/delete/PDF), all gated by
  `AuthMiddleware` + `PermissionMiddleware` (`hiring_reports.*` permissions).
- `src/Controllers/Web/AdminHiringReportController.php` — CRUD + PDF
  preview/download, sharing logic with the Resignation/Salary report
  controllers (in Kintai Core) via the `HasStaffReportCrud` trait
  (`kintai\UI\Controller\Web\Staff\HasStaffReportCrud`) — a plain PHP trait,
  not a DI-resolved service, so it works the same regardless of where this
  controller class lives.
- `Views/reports-hiring*.blade.php` — list (all-stores and per-store), form,
  detail, PDF template. Registered under the `hiring-report::` view namespace.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. Keys used by `HiringReportBundle` itself
  (`bundle_hiring_report`, `bundle_hiring_report_desc`) must exist in every
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
