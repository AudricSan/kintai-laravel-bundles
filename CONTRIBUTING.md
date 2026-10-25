# Contributing to kintai-laravel-bundles

Thank you for your interest in contributing!

## Before you start

These bundles are licensed under the **GNU Affero General Public License v3.0** (AGPL-3.0), same as
[Kintai](https://github.com/AudricSan/kintai-laravel) itself. By contributing, you agree that your contributions will
be licensed under the same terms.

## Submitting a pull request

1. Fork the repository and create a branch: `feat/<bundle>-my-feature` or `fix/<bundle>-my-bug`.
2. Make your changes in the bundle's folder, following the conventions below, and add them to that bundle's
   `CHANGELOG.md` under `## [Unreleased]`.
3. Open a pull request against `develop`. `alpha`, `beta` and `main` are protected release channels: merging into one
   of them tags and publishes a GitHub Release for each bundle that changed (see [README](README.md#releases)).
4. The `Tests` workflow must pass before merge.

## Conventions

- PHP 8.3+, `declare(strict_types=1)` in every PHP class file (never in a Blade view).
- Namespace root of a bundle: `kintai\Bundles\Installed\{Name}\` → `{slug}/src/`; tests: `kintai\Tests\Bundles\{Name}\`
  → `{slug}/tests/` (both declared in the root `composer.json`).
- Controllers: `final class`, constructor injection, signature `method(Request $request): Response`; route parameters
  are read with `$request->param('name')`.
- Only `kintai\Contract\…` classes and the contract's global functions (checked by each bundle's `ContractUsageTest`).
- Views are Blade templates (`Views/*.blade.php`): `{{ }}` escapes, `{!! !!}` prints raw, `@includeInScope('{slug}::x')`
  inserts another view of the bundle. No inline `style="..."`, no inline event handlers (`onclick=`…), no
  `javascript:` links and no `<script>` without `nonce="{!! csp_nonce() !!}"`: Kintai's Content Security Policy blocks
  them silently. Use `data-on-click` / `data-on-change` / `data-args`, `data-submit-on-change`, `data-confirm` and
  `data-stop-propagation` (handled by the Core's `csp-actions.js`).
- Comments in code are written in French; commit messages and PR titles too.

## Running checks locally

See [README › Development](README.md#development): `vendor/bin/phpunit` runs every bundle's suite against
`../kintai-laravel`.
