# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

This is the standalone distribution repo for the official "Shift Claim"
(open shifts / shift bidding) bundle of [Kintai](https://github.com/AudricSan/Kintai).
It used to live inside the main Kintai monorepo at `src/Bundles/ShiftClaim/`
and was extracted so it can be installed independently, the same way any
third-party bundle would be (see `docs/creating-a-bundle.md` in the main
Kintai repo for the full bundle distribution model — manifest, registry,
installer).

There is no build or test suite in this repo (no `composer.json`, no
PHPUnit). The code is not runnable or functionally testable standalone: every
class under `src/` depends on `kintai\Core\*` (repositories, middleware,
`Request`/`Response`, `ViewRenderer`, `PermissionService`, etc.) that only
exist inside a running Kintai instance. Verifying a behavior change means
installing the bundle into a real Kintai instance, not running anything in
this repo. CI here only checks PHP syntax and manifest validity (see
"CI and branches" below) — it cannot catch logic errors.

Kintai never `git clone`/`pull`s bundles (many shared-hosting environments
have no `git` CLI available to PHP) — `BundleInstallerService` always
downloads a tagged GitHub Release's zipball. This repo's only "build output"
is therefore the GitHub Release itself; nothing here gets compiled or
packaged.

## CI and branches

This repo mirrors the branch/release model of the main Kintai repo:

- `main`, `alpha`, and `beta` are protected branches — no direct push; land
  changes via a PR (see `CONTRIBUTING.md`). New work targets `alpha` (the
  active channel); promote a line forward by merging `alpha` → `beta` → `main`.
- `.github/workflows/tests.yml` runs a `test` job (PHP syntax check via
  `php -l` on every `.php` file, plus JSON validation of `bundle.json` and
  `lang/*.json`) on every push and PR to these branches. This is the required
  status check gating merges.
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
  version, `kintai_core` compatibility range, `entry_class`.
- `src/ShiftClaimBundle.php` — the entry point
  (`kintai\Bundles\Installed\ShiftClaim\ShiftClaimBundle`, extends
  `kintai\Core\BundleContract\Bundle`). `register()` binds
  `ShiftClaimRepositoryInterface` to `DatabaseShiftClaimRepository` as a
  singleton. **Two Kintai Core components still read this interface**
  despite it being bundle-owned: `HomeController` (the "pending requests"
  dashboard KPI) and `AdminRequestsController` (the `/admin/requests`
  summary page's claims section). Both resolve it lazily — never as a
  constructor dependency — gated by `feat_bundle('open_shifts') &&
  Container::has(ShiftClaimRepositoryInterface::class)` before ever calling
  `make()`, so they degrade to omitting that section instead of crashing
  when this bundle isn't installed. Don't remove those guards if you ever
  touch either controller on the Kintai side.
- `routes.php` — three groups: `/employee/open-shifts*` (`AuthMiddleware`
  only — claim/withdraw a published open shift), `/admin/open-shifts*` +
  `/admin/shifts/{id}/publish|unpublish` (`AuthMiddleware` +
  `PermissionMiddleware`, `open_shifts.*` permissions), and
  `/api/v1/shift-claims*` (REST, `ApiAuthMiddleware` + `ApiPermissionMiddleware`).
- `src/Controllers/Web/EmployeeShiftClaimController.php` — browse open
  shifts, claim one, withdraw a pending claim.
- `src/Controllers/Web/AdminShiftClaimController.php` — publish/unpublish a
  shift as "open", approve/reject a claim.
- `src/Controllers/Api/ShiftClaimController.php` — REST CRUD over claims.
- `Views/open-shifts.php`, `Views/open-shifts-select.php`. Registered under
  the `shift-claim::` view namespace.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. Keys used by `ShiftClaimBundle` itself
  (`bundle_shift_claim`, `bundle_shift_claim_desc`) must exist in every
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
inline script uses `<script nonce="<?= function_exists('csp_nonce') ? csp_nonce() : '' ?>">`. `Button::attrs()`
already escapes values: pass raw ones. `tests.yml` fails when a violation reappears. Full reference: [Content
Security Policy](https://github.com/AudricSan/Kintai/blob/develop/docs/creating-a-bundle.md#content-security-policy-no-inline-scripts) in Kintai's bundle guide.
