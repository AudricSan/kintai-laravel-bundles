# Contributing to kintai-bundle-messaging

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
- Namespace root: `kintai\Bundles\Installed\Messaging\` → `src/`
- Controllers: `final class`, constructor injection, signature
  `method(Request $request): Response` — route parameters are read via
  `$request->param('name')`, never as method arguments
- Persistence goes through `MessageRepositoryInterface` (bound in
  `MessagingBundle::registerServices()`) — controllers never touch storage
  directly. Kintai Core's `MessageCountMiddleware` also depends on this
  interface but degrades gracefully when it's unbound — see
  [CLAUDE.md](CLAUDE.md#architecture)
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

There is no PHPUnit suite in this repo (see [CLAUDE.md](CLAUDE.md) for why).
Before opening a PR, run what CI runs:

```bash
find src Views -name '*.php' -print0 | xargs -0 -n1 php -l
php -l routes.php
for f in bundle.json lang/*.json; do jq empty "$f"; done
# No inline handlers / javascript: / nonce-less <script> (Kintai's CSP would block them silently)
! grep -rnE "[[:space:]]on(click|change|submit|input|load|error|focus|blur|dblclick|keyup|keydown)[[:space:]]*=|['\"]on(click|change|submit|input)['\"][[:space:]]*=>|(href|src|action)[[:space:]]*=[[:space:]]*[\"']javascript:|<script>" Views src
```

Functional testing requires installing the bundle into a real Kintai
instance — there's no way to exercise the controllers standalone.

## Where to look first

- [CLAUDE.md](CLAUDE.md) — architecture, branch model, release process
- [CHANGELOG.md](CHANGELOG.md) — what's been done recently
- [README.md](README.md) — what the bundle does, how it's installed
