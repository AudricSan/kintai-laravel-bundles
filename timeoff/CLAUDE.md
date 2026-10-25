# CLAUDE.md — timeoff

Bundle officiel « Congés » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

## Architecture

- `bundle.json` — manifest read by Kintai's bundle installer/registry: slug,
  version, `kintai_core` compatibility range, `entry_class`.
- `src/TimeOffBundle.php` — the entry point
  (`kintai\Bundles\Installed\TimeOff\TimeOffBundle`, extends
  `kintai\Core\BundleContract\Bundle`). **Unlike most bundles, `register()`
  does not bind any repository**: `TimeoffRequestRepositoryInterface` stays
  bound in Kintai Core's own `RepositoryServiceProvider`, because
  `StoreStatsService`, `ShiftService`, `AdminShiftController`,
  `IcalController`, and `HomeController` all depend on it directly
  (constructor-injected, unconditional) for calculations that must keep
  working even if this bundle is disabled or uninstalled entirely.
  `register()` only wires up `Views/` and `routes.php`. Don't add a
  repository binding here — it would just shadow the Core one and add
  confusion, not capability.
- `routes.php` — three groups: `/employee/timeoff*` (`AuthMiddleware` only —
  create/cancel a time-off request), `/admin/timeoff*` (`AuthMiddleware` +
  `PermissionMiddleware`, `timeoff.*` permissions), and
  `/api/v1/timeoff-requests*` (REST, `ApiAuthMiddleware` +
  `ApiPermissionMiddleware`, self-scoped for index/store via the
  `self: 'user_id'` permission option).
- `src/Controllers/Web/EmployeeTimeoffController.php` — request/cancel a
  time-off request.
- `src/Controllers/Web/AdminTimeoffController.php` — list, create on an
  employee's behalf, approve/refuse/delete requests.
- `src/Controllers/Api/TimeoffRequestController.php` — REST CRUD over
  time-off requests.
- `Views/employee-timeoff.blade.php`, `Views/timeoff.blade.php`, `Views/timeoff-form.blade.php`.
  Registered under the `timeoff::` view namespace.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. Keys used by `TimeOffBundle` itself
  (`bundle_timeoff`, `bundle_timeoff_desc`) must exist in every locale file.

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
