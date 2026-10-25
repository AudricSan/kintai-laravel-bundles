# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

This is the standalone distribution repo for the official "Salary Report"
bundle of [Kintai](https://github.com/AudricSan/Kintai) — monthly salary
reports per store or per employee, pre-filled from daily reports and shifts,
with a recalculation modal and PDF export (single and bulk), at
`/admin/reports/salary` and `/admin/stores/{id}/reports/salary/*`. It follows
the same distribution model as every other Kintai bundle (see
`docs/creating-a-bundle.md` in the main Kintai repo — manifest, registry,
installer). Unlike its `daily-report`/`hiring-report`/`resignation-report`
cousins (which ship no tests at all), this repo **does** ship a real PHPUnit
suite (`tests/`) — see "Running tests" below for how, and
`kintai-bundle-notebook`'s own `CLAUDE.md` for the precedent this follows.

The `salary_reports` table already exists in Kintai Core (three historical
migrations, predating the `BundleMigrationRunner` mechanism `notebook`
introduced) — this bundle has no `database/migrations/` of its own and never
needs one.

Nothing here is a reimplementation or fake of Kintai Core: `composer.json`'s
`autoload-dev` maps the `kintai\` namespace root straight to the real Kintai
repository's `src/` via a relative path (`../../Kintai/src/`), the same
sibling-repo layout `KintaiBundleDev/` already uses locally — so
`Request`/`Response`, `PermissionService`, the repository interfaces, and
everything else this bundle's classes depend on are the actual Core classes,
just never booted into a running app (no HTTP layer, no real database, no
`Application` instance). Tests mock only what a running app would inject
(repository interfaces, `Log`) — never Core's own logic. This still can't
replace installing the bundle into a real running instance for a final check
(routing, views, RBAC middleware, and anything actually hitting the
configured database driver are all out of scope for these tests), but it
exercises every controller decision branch (scope resolution, duplicate-month
rejection, recalculation from shifts/daily reports, employee-scoped vs
store-wide reports) without that step.

Kintai never `git clone`/`pull`s bundles (many shared-hosting environments
have no `git` CLI available to PHP) — `BundleInstallerService` always
downloads a tagged GitHub Release's zipball. This repo's only "build output"
is therefore the GitHub Release itself; nothing here gets compiled or
packaged.

## Running tests

```bash
composer install
vendor/bin/phpunit
```

Requires a checkout of [`AudricSan/Kintai`](https://github.com/AudricSan/Kintai)
at `../../Kintai` relative to this repo's root — exactly the layout this repo
already has locally inside `KintaiBundleDev/` (this repo and `Kintai/` as
siblings). Nothing else to configure: no database, no `.env`, no booted
`Application`. `tests/` mirrors `src/`'s structure (`Controllers/Web/`) plus
a `Views/` directory for the two view-rendering regression tests and a
top-level `RoutesTest.php` — follows the same conventions as
`kintai-bundle-notebook`'s suite: mock the repository interfaces, use
`ReflectionProperty`/`sys_get_temp_dir()` where a real dependency (a view
file, a `Response` header) needs to exist on disk. The two `tests/Views/*`
tests render `Views/reports-salary-show.php`/`reports-salary-form.php`
through a real `ViewRenderer` and assert on the produced HTML (currency
formatting, conditional rows) — `reports-salary-show.php` includes Kintai
Core's `src/UI/View/_partials/_payslip-helpers.php` via the global
`BASE_PATH` constant (exactly as it would in production, where `BASE_PATH`
always points at the real Kintai root regardless of where this bundle is
installed), so these two tests each define `BASE_PATH` themselves, pointed at
the sibling `../../Kintai` checkout — not at this bundle's own root, which is
what a production `BASE_PATH` would never resolve to either.

## CI and branches

This repo mirrors the branch/release model of the main Kintai repo:

- `main`, `alpha`, and `beta` are protected branches — no direct push; land
  changes via a PR (see `CONTRIBUTING.md`). New work targets `alpha` (the
  active channel); promote a line forward by merging `alpha` → `beta` → `main`.
- `.github/workflows/tests.yml` runs a `test` job on every push and PR to
  these branches — this is the required status check gating merges. It checks
  out this repo AND `AudricSan/Kintai` side by side (`Kintai` as a sibling of
  `KintaiBundleDev/`, matching the relative path in `composer.json`), lints
  every `.php` file (`php -l`), validates `bundle.json`/`lang/*.json` as JSON,
  then runs `composer install` and the real `vendor/bin/phpunit` suite — see
  "Running tests" above. It pins the Kintai checkout to `develop`, not
  `main` (Kintai's actual GitHub default branch) — this bundle's dependency
  on the bundle-assets mechanism (`Bundle::loadAssetsFrom()`,
  `bundle_asset()`/`bundle_asset_path()`) only exists there for now. Update
  that `ref:` once a Kintai release actually ships it through
  `alpha`/`beta`/`main`, and set `bundle.json`'s `kintai_core.min` to that
  version at the same time.
- Merging into any of the three branches triggers
  `.github/workflows/release.yml`, which tags and publishes a GitHub Release
  — see "Release process" below.

## Release process

`.github/workflows/release.yml` triggers on push to `alpha`, `beta`, or
`main` (i.e. on every merge, since those branches are protected) and computes
and pushes the tag itself — never tag or `gh release create` by hand:

- Version line `X.Y` comes from `version` in `bundle.json`, which is always
  written as the placeholder `X.Y.0` and is only bumped by hand when opening a
  new release line (new `Y`).
- `alpha`/`beta` merges tag `vX.Y.Z` as a prerelease, where `Z` is the highest
  existing `vX.Y.*` tag + 1 — a counter shared and cumulative across alpha and
  beta within the same line, never reset between them.
- `main` merges tag `vX.Y.0` as the stable release for that line. If `vX.Y.0`
  already exists, the job skips cleanly (a line only ever gets one stable
  release; further fixes require opening a new line).
- Release notes are extracted from `CHANGELOG.md`: `## [Unreleased]` for
  alpha/beta (falling back to `## [X.Y.0]` if `Unreleased` is empty, i.e. the
  release commit already renamed it), or `## [X.Y.0]` directly for `main`. A
  push to a channel with no matching CHANGELOG section fails the job — always
  update `CHANGELOG.md` in your PR before merging.

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
- `Views/` — `reports-salary.php` (global list + export), `reports-salary-show.php`/
  `reports-salary-form.php` (detail/edit, money fields formatted client-side
  by `public/js/salary-report-form.js` rather than native `<input type="number">`,
  which can't display thousands separators), `reports-salary-pdf.php`/
  `reports-salary-export-pdf.php` (single and bulk PDF, mPDF via
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
inline script uses `<script nonce="<?= function_exists('csp_nonce') ? csp_nonce() : '' ?>">`. `Button::attrs()`
already escapes values: pass raw ones. `tests.yml` fails when a violation reappears. Full reference: [Content
Security Policy](https://github.com/AudricSan/Kintai/blob/develop/docs/creating-a-bundle.md#content-security-policy-no-inline-scripts) in Kintai's bundle guide.
