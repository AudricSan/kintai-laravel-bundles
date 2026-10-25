# CLAUDE.md — messaging

Bundle officiel « Messagerie » de Kintai, dans le dépôt des bundles Laravel (`kintai-laravel-bundles`). Dépôt, tests,
CI, branches et releases : voir le `CLAUDE.md` à la racine du dépôt. Ce fichier ne décrit que ce bundle.

## Architecture

- `bundle.json` — manifest read by Kintai's bundle installer/registry: slug,
  version, `kintai_core` compatibility range, `entry_class`.
- `src/MessagingBundle.php` — the entry point
  (`kintai\Bundles\Installed\Messaging\MessagingBundle`, extends
  `kintai\Core\BundleContract\Bundle`). `register()` binds
  `MessageRepositoryInterface` to `DatabaseMessageRepository` as a singleton,
  then calls `loadViewsFrom(..., 'messaging')` and `loadRoutesFrom(routes.php)`.
  `MessageRepositoryInterface` is owned and registered by this bundle, not by
  Kintai Core, with one exception: Kintai Core's `MessageCountMiddleware`
  (global middleware, shares the unread-message-count badge on every
  authenticated page) also depends on it, but wraps the call in a
  `try`/`catch` and degrades to showing `0` if the bundle isn't installed —
  it never assumes the interface is bound. Don't remove that guard if you
  ever touch `MessageCountMiddleware` on the Kintai side.
- `routes.php` — four groups: an SSE stream (`GET /messages/{id}/stream`,
  `MessageStreamController`, `AuthMiddleware` only), `/admin/messages/*`
  (`AuthMiddleware` + `PermissionMiddleware`, permissions
  `messaging.view`/`send`/`delete`), `/employee/messages/*` (`AuthMiddleware`
  only — no RBAC gate, every authenticated employee can message), and
  `/api/v1/messages/*` (REST, `ApiAuthMiddleware` only, no per-route
  permission — threads/participants/messages CRUD).
- `src/Controllers/Web/MessageController.php` — compose/list/thread/reply/
  delete for both the admin and employee route groups (same methods, mounted
  twice under different middleware).
- `src/Controllers/Web/MessageStreamController.php` — SSE endpoint backing
  live message updates in an open thread.
- `src/Controllers/Api/MessageController.php` — REST CRUD over threads,
  messages, and participants.
- `Views/*.php` — `messages.blade.php` (inbox list), `messages-compose.blade.php`,
  `messages-thread.blade.php`. Registered under the `messaging::` view namespace.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. Keys used by `MessagingBundle` itself
  (`bundle_messaging`, `bundle_messaging_desc`) must exist in every locale file.

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
