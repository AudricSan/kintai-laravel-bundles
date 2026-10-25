# CLAUDE.md — shift-claim

Bundle officiel « Bourse aux shifts » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

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
- `Views/open-shifts.blade.php`, `Views/open-shifts-select.blade.php`. Registered under
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
inline script uses `<script nonce="{!! function_exists('csp_nonce') ? csp_nonce() : '' !!}">` (views are Blade templates). `Button::attrs()`
already escapes values: pass raw ones. `tests.yml` fails when a violation reappears. Full reference: [Content
Security Policy](https://github.com/AudricSan/Kintai/blob/develop/docs/creating-a-bundle.md#content-security-policy-no-inline-scripts) in Kintai's bundle guide.
