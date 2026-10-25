# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

This is the standalone distribution repo for the official "Notebook" bundle of
[Kintai](https://github.com/AudricSan/Kintai) — a team notebook: members post
notes visible on both the admin and employee dashboards, with pinning,
per-note scope (a single store, or the whole organization), and automatic
expiration. It follows the same distribution model as every other Kintai
bundle (see `docs/creating-a-bundle.md` in the main Kintai repo — manifest,
registry, installer), but is the first bundle to ship its own database schema
via the `database/migrations/` mechanism (see "Architecture" below) rather
than depending on a table Kintai Core already provides.

Unlike every other Kintai bundle, this repo **does** ship a real PHPUnit
suite (`tests/`) — see "Running tests" below for how. Nothing here is a
reimplementation or fake of Kintai Core: `composer.json`'s `autoload-dev`
maps the `kintai\` namespace root straight to the real Kintai repository's
`src/` via a relative path (`../../Kintai/src/`), the same sibling-repo
layout `KintaiBundleDev/` already uses locally — so `Request`/`Response`,
`PermissionService`, the repository interfaces, `Database\Migration`, and
everything else this bundle's classes depend on are the actual Core classes,
just never booted into a running app (no HTTP layer, no real database beyond
an in-memory SQLite `Capsule` tests set up themselves, no `Application`
instance). Tests mock only what a running app would inject (repositories,
`NotificationService`) — never Core's own logic. This still can't replace
installing the bundle into a real running instance for a final check (routing,
views, RBAC middleware, and anything actually hitting the configured database
driver are all out of scope for these tests), but it exercises every
controller's actual decision logic (authorization branches, scope
resolution, notification recipients) and the migration file itself, without
that step.

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
`Application`. `tests/` mirrors `src/`'s structure (`Controllers/Web/`,
`Controllers/Api/`, plus `Database/` for the migration test) and follows the
same conventions as Kintai's own test suite — mock the repository interfaces
and `NotificationService`, construct a real `PermissionService` with mocked
`RoleAssignmentRepositoryInterface`/`RoleRepositoryInterface` rather than
mocking `PermissionService` itself (it's `final`, and mocking it would test
nothing about how RBAC actually resolves), use `ReflectionProperty` to set
`Request`'s private `jsonBody` for API tests and to read `Response`'s private
`headers['Location']` for redirect assertions — see any existing test file
for the exact pattern. The migration test instantiates the real
`kintai\Core\Database\BundleMigrationRunner` against an in-memory SQLite
`Capsule` (same reflection-based construction technique as Kintai's own
`BundleMigrationRunnerTest`) rather than asserting anything about the
migration file's contents directly — it proves the file actually produces
the right schema through the exact mechanism `BundleInstallerService` uses at
install time, not just that it's syntactically valid.

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
  `main` (Kintai's actual GitHub default branch) — this bundle's Core
  dependencies (`NotebookEntryRepositoryInterface`, `BundleMigrationRunner`)
  only exist there for now. Update that `ref:` once a Kintai release
  actually ships them through `alpha`/`beta`/`main`, and set `bundle.json`'s
  `kintai_core.min` to that version at the same time.
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
  must be at or above the Kintai version that introduced bundle-owned
  migrations (`BundleMigrationRunner`, see below) — an older Core silently
  ignores `database/migrations/`, leaving the bundle installed with no table.
- `src/NotebookBundle.php` — the entry point (`kintai\Bundles\Installed\Notebook\NotebookBundle`,
  extends `kintai\Core\BundleContract\Bundle`). Its `register()` binds
  `NotebookEntryRepositoryInterface` to `DatabaseNotebookEntryRepository` as a
  singleton in the app container, then calls `loadViewsFrom(..., 'notebook')`
  and `loadRoutesFrom(routes.php)`. Like Feedback's `FeedbackRepositoryInterface`,
  this binding only happens when the bundle is active — Kintai Core's
  dashboard widgets guard every access with `Container::has(...)`, so nothing
  crashes while this bundle isn't installed.
- `database/migrations/2026_09_25_000001_create_notebook_entries_table.php` —
  creates the `notebook_entries` table (`author_id`, nullable `store_id` where
  `null` means "visible to the whole organization", `content`, `pinned`,
  `expires_at`, timestamps). Same file format as Kintai's own Core migrations
  (`return new class($this->capsule) extends Migration {...}`), run
  automatically by `BundleMigrationRunner` at install/update time (tracked in
  a `bundle_migrations` table, scoped by bundle slug — never Kintai's own
  `migrations` table). This is the first bundle to use this mechanism; see
  Kintai's `docs/creating-a-bundle.md` → "Database migrations" for the full
  contract. `NotebookEntryRepositoryInterface`/`DatabaseNotebookEntryRepository`/
  the Eloquent model itself live in Kintai Core (`src/Core/Repositories/`,
  `src/Domain/Eloquent/`), following the same "stable interface" convention
  every other bundle uses — only the table's *creation* is delegated to this
  bundle, not the repository contract.
- `routes.php` — two route groups: `/notebook/*` (`AuthMiddleware` +
  `PermissionMiddleware`, permissions `notebook.view`/`notebook.create`/`notebook.manage`)
  and `/api/v1/notebook-entries` (`ApiAuthMiddleware` + `ApiPermissionMiddleware`).
  Unlike Feedback/Messaging, there's no `/admin` vs `/employee` split — access
  is purely RBAC-driven (an Owner can grant `notebook.create` to the Employee
  role if they want self-service posting, or keep it Manager-only).
- `src/Controllers/Web/NotebookController.php` — `index()` lists notes visible
  to the user (their managed/member stores + organization-wide notes),
  pinned first, hiding expired ones unless `?show_expired=1`. `store()`
  validates content, resolves `store_id` (empty = organization-wide, only
  allowed for a globally-scoped user — `managed_store_ids === null`), saves,
  audit-logs, and notifies (`notifyMembers()`) every user holding
  `notebook.view` in scope. `update()`/`destroy()` are allowed for the note's
  author OR anyone holding `notebook.manage` on its store; `togglePin()` is
  gated to `notebook.manage` at the route level.
- `src/Controllers/Api/NotebookController.php` — REST CRUD, `restrictToScope`/
  ownership pattern (author OR `notebook.manage`) matching every other
  bundle's API controller — see `ShiftSwapRequestController` in Core if you
  need the reference audit-fix history for this pattern.
- `Views/notebook.php` — the full list/management page, registered under the
  `notebook::` view namespace (`notebook::notebook`). Reuses the
  `.notebook-widget-item` CSS classes Kintai Core ships for its own dashboard
  widget cards (`public/assets/css/src/components/notebook.css`), so this
  page and the dashboard widgets look identical.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. `bundle_notebook`/`bundle_notebook_desc` are
  required in every locale (used by `getLabel()`/`getDescription()`). Keys
  already defined in Kintai Core (`widget_team_notes`, `no_notebook_entries`,
  `notebook_pinned`, `notebook_org_wide`, `notebook_anonymous_author` — used
  by the Core dashboard widgets this bundle's data feeds) are **not**
  redeclared here; only vocabulary exclusive to this bundle's own pages is.

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
