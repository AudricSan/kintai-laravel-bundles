# Contributing to kintai-bundle-planning-check

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
- Namespace root: `kintai\Bundles\Installed\PlanningCheck\` → `src/`
- Controllers: `final class`, constructor injection, signature
  `method(Request $request): Response` — route parameters are read via
  `$request->param('name')`, never as method arguments
- Persistence goes through `PlanningCheckRepositoryInterface` (implemented by `DatabasePlanningCheckRepository`)
- Comments in code are written in French; everything else (commit messages,
  PR descriptions, docs) in English
- Never use inline `style="..."` in `Views/*.php` — they're rendered inside
  Kintai's own layout and should follow the host app's CSS conventions
- No inline event handlers (`onclick=`, `onchange=`, `onsubmit=`, `oninput=`…), no `javascript:` links and no
  `<script>` without a nonce in `Views/*.php` or `src/` — Kintai's Content-Security-Policy
  (`script-src 'self' 'nonce-…'`, Kintai Core 0.3.0+) blocks them **silently**: the control just does nothing and
  the server sees no error. Use `data-on-click` / `data-on-change` / `data-args`, `data-submit-on-change`,
  `data-confirm` and `data-stop-propagation` (handled by the Core's `csp-actions.js`), and
  `<script nonce="<?= csp_nonce() ?>">` for an inline script that is really needed. Reference:
  [Content Security Policy](https://github.com/AudricSan/Kintai/blob/develop/docs/creating-a-bundle.md#content-security-policy-no-inline-scripts) in Kintai's bundle guide. CI (`tests.yml`) fails on a violation.

## Running Checks Locally

```bash
composer install
vendor/bin/phpunit
```

`tests.yml` runs the same suite in CI, plus a lint of `src`, `Views`, `database` and `tests`, a JSON check of
`bundle.json` and `lang/*.json`, and the CSP guard (no inline handlers, `javascript:` links or nonce-less `<script>`).

## Where to look first

- [CLAUDE.md](CLAUDE.md) — architecture, branch model, release process
- [CHANGELOG.md](CHANGELOG.md) — what's been done recently
- [README.md](README.md) — what the bundle does, how it's installed
