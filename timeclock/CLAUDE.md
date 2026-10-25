# CLAUDE.md — timeclock

Bundle officiel « Pointage » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

## Architecture

- `bundle.json` — manifest read by Kintai's bundle installer/registry: slug,
  version, `kintai_core` compatibility range, `entry_class`.
- `src/TimeclockBundle.php` — the entry point
  (`kintai\Bundles\Installed\Timeclock\TimeclockBundle`, extends
  `kintai\Core\BundleContract\Bundle`). **Unlike most bundles, `register()`
  does not bind any repository**: `TimeclockRepositoryInterface` stays bound
  in Kintai Core's own `RepositoryServiceProvider`, because `HomeController`,
  `EmployeeController`'s dashboard, and `DashboardAlertService` all depend
  on it directly (constructor-injected, unconditional) for "clocked in"
  widgets/alerts that must keep working even if this bundle is disabled or
  uninstalled entirely. `register()` only wires up `Views/` and `routes.php`.
  Don't add a repository binding here — it would just shadow the Core one
  and add confusion, not capability.
- `routes.php` — three groups: `/employee/timeclock*` (`AuthMiddleware`
  only — clock in/out, view own history), `/admin/timeclocks*`
  (`AuthMiddleware` + `PermissionMiddleware`, `timeclock.*` permissions), and
  `/api/v1/timeclocks*` (REST, `ApiAuthMiddleware` + `ApiPermissionMiddleware`,
  self-scoped for clock-in/clock-out/index via the `self: 'user_id'`
  permission option).
- `src/Controllers/Web/EmployeeTimeclockController.php` — clock in, clock
  out, view own timeclock history.
- `src/Controllers/Web/AdminTimeclockController.php` — list/edit/delete
  timeclock entries across the organization.
- `src/Controllers/Api/TimeclockController.php` — REST CRUD + clock-in/out
  over the API.
- `Views/employee-timeclock.blade.php`, `Views/timeclocks.blade.php`. Registered under
  the `timeclock::` view namespace.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. Keys used by `TimeclockBundle` itself
  (`bundle_timeclock`, `bundle_timeclock_desc`) must exist in every locale
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
