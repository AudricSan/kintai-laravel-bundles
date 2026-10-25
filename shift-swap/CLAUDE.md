# CLAUDE.md — shift-swap

Bundle officiel « Échanges de shifts » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

## Architecture

- `bundle.json` — manifest read by Kintai's bundle installer/registry: slug,
  version, `kintai_core` compatibility range, `entry_class`.
- `src/ShiftSwapBundle.php` — the entry point
  (`kintai\Bundles\Installed\ShiftSwap\ShiftSwapBundle`, extends
  `kintai\Core\BundleContract\Bundle`). **Unlike most bundles, `register()`
  does not bind any repository**: `ShiftSwapRequestRepositoryInterface` stays
  bound in Kintai Core's own `RepositoryServiceProvider`, because
  `StoreStatsService`, `HomeController`, and `EmployeeController`'s dashboard
  all depend on it directly (constructor-injected, unconditional) for
  calculations that must keep working even if this bundle is disabled or
  uninstalled entirely. `register()` only wires up `Views/` and `routes.php`.
  Don't add a repository binding here — it would just shadow the Core one and
  add confusion, not capability.
- `routes.php` — three groups: `/employee/swaps*` (`AuthMiddleware` only —
  create/accept/refuse/cancel a swap request), `/admin/swap-requests*`
  (`AuthMiddleware` + `PermissionMiddleware`, `swaps.*` permissions), and
  `/api/v1/shift-swap-requests*` (REST, `ApiAuthMiddleware` +
  `ApiPermissionMiddleware`).
- `src/Controllers/Web/EmployeeSwapController.php` — request a swap, respond
  to one received, cancel a pending one.
- `src/Controllers/Web/AdminSwapController.php` — list/approve/refuse/delete
  swap requests, create one on an employee's behalf.
- `src/Controllers/Api/ShiftSwapRequestController.php` — REST CRUD over swap
  requests, with `PermissionService::requireOwnedResource()` re-checking the
  request's actual store on show/update/destroy (RBAC-V2 fix, see the
  controller's own docblock).
- `Views/swaps.blade.php`, `Views/swap-requests.blade.php`, `Views/swap-form.blade.php`,
  `Views/swaps-form.blade.php`. Registered under the `shift-swap::` view namespace.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. Keys used by `ShiftSwapBundle` itself
  (`bundle_shift_swap`, `bundle_shift_swap_desc`) must exist in every locale
  file.

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
