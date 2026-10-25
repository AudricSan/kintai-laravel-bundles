# CLAUDE.md — notebook

Bundle officiel « Carnet de notes » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

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
- `Views/notebook.blade.php` — the full list/management page, registered under the
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
inline script uses `<script nonce="{!! function_exists('csp_nonce') ? csp_nonce() : '' !!}">` (views are Blade templates). `Button::attrs()`
already escapes values: pass raw ones. `tests.yml` fails when a violation reappears. Full reference: [Content
Security Policy](https://github.com/AudricSan/Kintai/blob/develop/docs/creating-a-bundle.md#content-security-policy-no-inline-scripts) in Kintai's bundle guide.
