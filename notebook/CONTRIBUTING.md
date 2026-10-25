# Contributing to kintai-bundle-notebook

Thank you for your interest in contributing!

## Before You Start

This bundle is licensed under the **GNU Affero General Public License v3.0**
(AGPL-3.0), same as [Kintai](https://github.com/AudricSan/Kintai) itself. By
contributing, you agree that your contributions will be licensed under the
same terms.

## Submitting a Pull Request

1. Fork the repository
2. Create a branch: `git checkout -b feat/my-feature` or `fix/my-bug`
3. Make your changes following the conventions below
4. Open a pull request against the `alpha` branch (the active channel —
   `main`, `alpha`, and `beta` are protected release-channel branches with no
   direct push; merging into one of them automatically tags and publishes a
   GitHub Release, see [CLAUDE.md](CLAUDE.md#release-process))
5. The `test` check (`.github/workflows/tests.yml`) must pass before merge

## Code Conventions

- PHP 8.3+, strict types (`declare(strict_types=1)`) in every file
- Namespace root: `kintai\Bundles\Installed\Notebook\` → `src/`
- Controllers: `final class`, constructor injection, signature
  `method(Request $request): Response` — route parameters are read via
  `$request->param('name')`, never as method arguments
- Persistence goes through `NotebookEntryRepositoryInterface` (bound in
  `NotebookBundle::registerServices()`) — controllers never touch storage
  directly. The interface itself lives in Kintai Core
  (`src/Core/Repositories/`); only the `notebook_entries` table's *creation*
  is owned by this bundle, via `database/migrations/` — see
  [CLAUDE.md](CLAUDE.md#architecture)
- Schema changes go in a new file under `database/migrations/`, same format
  as Kintai's own Core migrations (`return new class($this->capsule) extends
  \kintai\Core\Database\Migration {...}`), named `YYYY_MM_DD_NNNNNN_description.php`.
  Always guard `up()` with `hasTable()`/`hasColumn()` — migrations must be
  idempotent, they can be replayed by a manual re-sync
- Comments in code are written in French; everything else (commit messages,
  PR descriptions, docs) in English
- Never use inline `style="..."` in `Views/notebook.php` — it's rendered
  inside Kintai's own layout and should follow the host app's CSS conventions
  (reuse the `.notebook-widget-item` classes Kintai Core already ships for
  the dashboard widget, see `public/assets/css/src/components/notebook.css`
  in the main repo, rather than inventing new ones for the same visual pattern)
- No inline event handlers (`onclick=`, `onchange=`, `onsubmit=`, `oninput=`…), no `javascript:` links and no
  `<script>` without a nonce in `Views/*.php` or `src/` — Kintai's Content-Security-Policy
  (`script-src 'self' 'nonce-…'`, Kintai Core 0.3.0+) blocks them **silently**: the control just does nothing and
  the server sees no error. Use `data-on-click` / `data-on-change` / `data-args`, `data-submit-on-change`,
  `data-confirm` and `data-stop-propagation` (handled by the Core's `csp-actions.js`), and
  `<script nonce="<?= csp_nonce() ?>">` for an inline script that is really needed. Reference:
  [Content Security Policy](https://github.com/AudricSan/Kintai/blob/develop/docs/creating-a-bundle.md#content-security-policy-no-inline-scripts) in Kintai's bundle guide. CI (`tests.yml`) fails on a violation.

## Running Checks Locally

Unlike most Kintai bundles, this repo ships a real PHPUnit suite — see
[CLAUDE.md](CLAUDE.md#running-tests) for the one-time setup (it needs a
sibling checkout of `AudricSan/Kintai`). Before opening a PR, run what CI runs:

```bash
find src Views database tests -name '*.php' -print0 | xargs -0 -n1 php -l
php -l routes.php
for f in bundle.json lang/*.json; do jq empty "$f"; done
composer install
vendor/bin/phpunit
# No inline handlers / javascript: / nonce-less <script> (Kintai's CSP would block them silently)
! grep -rnE "[[:space:]]on(click|change|submit|input|load|error|focus|blur|dblclick|keyup|keydown)[[:space:]]*=|['\"]on(click|change|submit|input)['\"][[:space:]]*=>|(href|src|action)[[:space:]]*=[[:space:]]*[\"']javascript:|<script>" Views src
```

Add a test alongside any new/changed controller or migration logic — see
existing files under `tests/` for the conventions. This still doesn't replace
installing the bundle into a real running Kintai instance for a final check
before release: routing, view rendering, RBAC middleware, and the actual
configured database driver are all outside what these tests exercise.

## Where to look first

- [CLAUDE.md](CLAUDE.md) — architecture, branch model, release process
- [CHANGELOG.md](CHANGELOG.md) — what's been done recently
- [README.md](README.md) — what the bundle does, how it's installed
