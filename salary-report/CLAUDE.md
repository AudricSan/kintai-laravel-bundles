# CLAUDE.md — salary-report

Bundle officiel « Rapport de salaire » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

## Architecture

- `bundle.json` — manifest read by Kintai's bundle installer/registry: slug,
  version, `kintai_core` compatibility range, `entry_class`. `kintai_core.min`
  is `0.2.0` — the Kintai version that introduced the bundle-assets mechanism
  this bundle uses for its own CSS/JS (`public/css/`, `public/js/`).
- `src/SalaryReportBundle.php` — the entry point
  (`kintai\Bundles\Installed\SalaryReport\SalaryReportBundle`, extends
  `kintai\Core\BundleContract\Bundle`). Its `register()` binds
  `SalaryReportRepositoryInterface` to `DatabaseSalaryReportRepository` as a
  singleton in the app container (like `ResignationReportBundle`/
  `ShiftClaimBundle`/`StorePhotoBundle` — no other Core component depends on
  this interface, so the binding lives entirely in this bundle, not in Core's
  own `RepositoryServiceProvider`), then calls `loadViewsFrom(..., 'salary-report')`,
  `loadRoutesFrom(routes.php)`, and `loadAssetsFrom('public')`.
- **Bundle→bundle dependency**: `AdminSalaryReportController` reads
  `DailyReportRepositoryInterface` (bound in Kintai Core, independently of
  whether `daily-report` itself is installed) to pre-fill a month's revenue
  figure from validated/submitted daily reports when calculating a preset.
  This interface is Core-bound regardless of the `daily-report` bundle's
  install state, so it always resolves — this bundle depends on the stable
  Core interface, never on the `daily-report` bundle's own code directly.
- `routes.php` — one route group, `/admin/*` (`AuthMiddleware` +
  `PermissionMiddleware`): a global list/export (`/admin/reports/salary`,
  JSON and PDF, single and bulk) plus per-store CRUD + PDF
  (`/admin/stores/{id}/reports/salary/*`). Reuses the generic `payroll.view`/
  `payroll.generate`/`payroll.export` permissions (`PermissionCatalog`) —
  deliberately **not** a dedicated `salary_report.*` category, since
  `payroll.*` must stay assignable even when this bundle is uninstalled (the
  Core-native `AdminStoreController::employeeReport()`/`employeeStats()`
  pages, unrelated to this bundle, depend on it too — see the "Adjacent Core
  feature" note below).
- `src/Controllers/Web/AdminSalaryReportController.php` — CRUD for a
  store/employee-scoped monthly report, a recalculation endpoint pulling
  hours/cost from `ShiftRepositoryInterface`/`ShiftTypeRepositoryInterface`/
  `UserShiftTypeRateRepositoryInterface` (via `ShiftWageCalculator`), and PDF
  rendering/export shared with `daily-report`/`hiring-report`/
  `resignation-report` through Core's `HasStaffReportCrud` trait.
  `findByStoreAndMonth()` rejects a duplicate `(store, month[, user])`
  combination before `save()` is ever called.
- `Views/` — `reports-salary.blade.php` (global list + export), `reports-salary-show.blade.php`/
  `reports-salary-form.blade.php` (detail/edit, money fields formatted client-side
  by `public/js/salary-report-form.js` rather than native `<input type="number">`,
  which can't display thousands separators), `reports-salary-pdf.blade.php`/
  `reports-salary-export-pdf.blade.php` (single and bulk PDF, mPDF via
  `HasStaffReportCrud`).
- `public/css/salary-report.css` / `public/js/salary-report-form.js` /
  `public/css/pdf-salary-report.css` — this bundle's own assets, declared via
  `loadAssetsFrom('public')` and referenced from views through
  `bundle_asset()`/`bundle_asset_path()` (never a hardcoded path — see
  `docs/creating-a-bundle.md`'s "Assets" section in the main Kintai repo).
  The three Core-shared PDF stylesheets (`pdf-base.css`/`pdf-brand.css`/
  `pdf-preview.css`) are referenced directly via `BASE_PATH`, unchanged — only
  this bundle's own PDF stylesheet moved.
- **Adjacent Core feature, not part of this bundle**: `AdminStoreController::employeeReport()`/
  `employeeStats()` (Kintai Core, `payroll.view`, routes
  `admin.stores.employee_report`/`employee_stats`) show aggregate shift
  hours/cost stats and a "💰 Salary report" button linking into this bundle's
  `/admin/stores/{id}/reports/salary/create` — they never touch
  `SalaryReportRepositoryInterface` themselves, and keep working identically
  whether this bundle is installed or not.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys (`sr_*` prefix),
  merged into Kintai's `__()` translator. `bundle_salary_report`/
  `bundle_salary_report_desc` are required in every locale (used by
  `getLabel()`/`getDescription()`).

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
