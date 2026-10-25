# messaging

🌐 **English** · [Français](#français)

Official "Messaging" bundle for [Kintai](https://github.com/AudricSan/kintai-laravel) — internal messaging between store members: threaded conversations with live updates (SSE), an admin screen gated by RBAC (`messaging.view`/`send`/`delete`), an employee screen open to everyone, and a REST API (`/api/v1/messages/*`).

Part of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), the official bundles of Kintai on Laravel: views are Blade templates, and the bundle requires bundle contract `^1.31`. Its tests, CI and releases are shared with the other bundles of that repository (see its README).

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Messaging" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService` (here the `messaging-vX.Y.Z` release of kintai-laravel-bundles, of which only the `messaging/` folder is installed).

## Structure

```
bundle.json          # manifest — slug, version, Kintai core compatibility, entry class
src/
  MessagingBundle.php                    # kintai\Bundles\Installed\Messaging\MessagingBundle
  Controllers/Web/MessageController.php
  Controllers/Web/MessageStreamController.php  # SSE
  Controllers/Api/MessageController.php
Views/                # inbox, compose, thread
lang/{en,fr,ja}.json  # bundle-specific translation keys
routes.php            # loaded by MessagingBundle::register() via loadRoutesFrom()
```

## Releasing a new version

Releases are cut from the root of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases): bump `version` in `bundle.json` only when opening a new `X.Y` line, add your changes to `CHANGELOG.md` under `## [Unreleased]`, and merge into a channel branch; the release workflow tags `messaging-vX.Y.Z` and publishes the GitHub Release.

## License

AGPL-3.0-only, same as Kintai itself — see [LICENSE](LICENSE).

---

## Français

Bundle officiel "Messagerie" pour [Kintai](https://github.com/AudricSan/kintai-laravel) — messagerie interne entre membres des magasins : conversations en fil de discussion avec mise à jour en direct (SSE), un écran admin protégé par RBAC (`messaging.view`/`send`/`delete`), un écran employé ouvert à tous, et une API REST (`/api/v1/messages/*`).

Fait partie de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), les bundles officiels de Kintai sous Laravel : les vues sont des gabarits Blade, et le bundle requiert le contrat de bundle `^1.31`. Ses tests, sa CI et ses releases sont communs aux autres bundles de ce dépôt (voir son README).

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Messaging" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService` (ici la release `messaging-vX.Y.Z` de kintai-laravel-bundles, dont seul le dossier `messaging/` est installé).

### Publier une nouvelle version

Les releases se publient depuis la racine de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases) : incrémenter `version` dans `bundle.json` uniquement à l'ouverture d'une nouvelle ligne `X.Y`, ajouter les changements dans `CHANGELOG.md` sous `## [Unreleased]`, et merger vers une branche de canal ; le workflow de release tague `messaging-vX.Y.Z` et publie la release GitHub.

### Licence

AGPL-3.0-only, comme Kintai lui-même — voir [LICENSE](LICENSE).
