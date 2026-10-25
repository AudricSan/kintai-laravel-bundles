# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

The official bundles of Kintai on Laravel ([kintai-laravel](https://github.com/AudricSan/kintai-laravel)), one folder
per bundle (`{slug}/`, named after the bundle's slug). Each folder is a complete bundle and the only thing a Kintai
instance installs from this repository; everything at the root (tests bootstrap, `composer.json`, `phpunit.xml`,
workflows, `registry.json`, `scripts/`) is tooling. Each bundle's own `CLAUDE.md` describes its architecture.

## Tests

```bash
composer install                              # PHPUnit only; the Core comes from ../kintai-laravel
vendor/bin/phpunit                            # every bundle
vendor/bin/phpunit --testsuite planning-check # one bundle
node --test scripts/*.test.js                 # registry sync
node scripts/validate-registry.js
```

`tests/bootstrap.php` loads the autoloader of the real Kintai Core, cloned next to this repository
(`../kintai-laravel`, with `composer install` run there) or named by `KINTAI_CORE`, then this repository's autoloader
(bundle namespaces from the root `composer.json`). `BASE_PATH` is the Core's root, as in the application.
`tests/Shared/BladeViewsTest.php` compiles every Blade view of every bundle and checks every `@includeInScope` target.

## Views

Views are Blade templates (`{slug}/Views/**/*.blade.php`, bundle contract `^1.31`), rendered under the bundle's view
namespace (`loadViewsFrom($this->getPath() . '/Views', '{slug}')`, so `{slug}::name`). `@includeInScope('{slug}::x')`
inserts a view of the bundle in the current view's scope (it reads and can set the caller's variables, like a PHP
`include`); `@includeInScope('_partials.x')` inserts a Core partial. Escaped output is `{{ }}`, raw output `{!! !!}`
(translations `__()` are HTML-safe and printed raw, as before). Never `declare(strict_types=1)` in a view (Blade
prepends code to the compiled file), never `__DIR__`-relative paths (a bundle is installed under
`storage/bundles/{slug}/{version}/`): use `BASE_PATH` for Core files. Kintai's CSP forbids inline event handlers,
`javascript:` URLs and `<script>` without `nonce="{!! csp_nonce() !!}"`; CI checks it.

## Branches and releases

- Work lands on `develop`. `alpha`, `beta` and `main` are release channels: no direct push, a PR merged once CI is
  green. **Any push to one of them publishes GitHub Releases**: never push there without the owner's OK.
- `.github/workflows/release.yml` tags each bundle on its own, `{slug}-vX.Y.Z`: on alpha/beta, `Z` = highest
  `{slug}-vX.Y.*` + 1, only for bundles whose folder changed since their last tag; on main, `{slug}-vX.Y.0` once per
  line. `X.Y` comes from `version` in `{slug}/bundle.json` (written `X.Y.0`, bumped by hand only for a new line).
  Notes come from `{slug}/CHANGELOG.md`: `## [Unreleased]` (alpha/beta, falling back to `## [X.Y.0]`), `## [X.Y.0]`
  (main). A channel push with no matching section fails the job.
- `.github/workflows/sync-registry.yml` (after a release, hourly, or by hand) runs `scripts/sync-registry.js` and opens
  a PR into `main` updating `registry.json`: versions per channel (from the release's branch and prerelease flag) and
  each version's commit, pinned once and never changed automatically (a moved tag fails the job). Kintai reads
  `registry.json` from `main`; each entry's `repository_url` is the bundle's folder
  (`https://github.com/AudricSan/kintai-laravel-bundles/tree/main/{slug}`), which tells Kintai's installer to resolve
  the `{slug}-v{version}` release and install only that folder of the zipball.

## Conventions

- PHP 8.3, `declare(strict_types=1)` in every PHP class file; code comments in French.
- A bundle only uses `kintai\Contract\…` classes and the contract's global functions (each bundle's
  `ContractUsageTest`); translation keys are prefixed with the bundle's prefix (`TranslationContractTest`).
- Commit messages and PR titles in French; each bundle's `CHANGELOG.md` follows Keep a Changelog.
