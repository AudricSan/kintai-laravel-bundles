# notebook

🌐 **English** · [Français](#français)

Official "Notebook" bundle for [Kintai](https://github.com/AudricSan/kintai-laravel) — a team notebook: members post notes visible on the admin and employee dashboards, with pinning, per-note scope (a single store or the whole organization), and automatic expiration.

Part of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), the official bundles of Kintai on Laravel: views are Blade templates, and the bundle requires bundle contract `^1.31`. Its tests, CI and releases are shared with the other bundles of that repository (see its README). It's the first bundle to ship its own database table via the `database/migrations/` mechanism rather than depending on a table Kintai Core already provides.

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Notebook" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService` (here the `notebook-vX.Y.Z` release of kintai-laravel-bundles, of which only the `notebook/` folder is installed).

## Structure

```
bundle.json                     # manifest — slug, version, Kintai core compatibility, entry class
src/
  NotebookBundle.php                    # kintai\Bundles\Installed\Notebook\NotebookBundle
  Controllers/Web/NotebookController.php
  Controllers/Api/NotebookController.php
database/migrations/            # creates the notebook_entries table (see CLAUDE.md)
Views/notebook.blade.php              # list / management page
lang/{en,fr,ja}.json            # bundle-specific translation keys
routes.php                      # loaded by NotebookBundle::register() via loadRoutesFrom()
tests/                          # real PHPUnit suite (unusual for a Kintai bundle — see CLAUDE.md#running-tests)
composer.json                   # test-only tooling; never used at runtime (Kintai loads this bundle dynamically, not via Composer)
```

## Releasing a new version

Releases are cut from the root of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases): bump `version` in `bundle.json` only when opening a new `X.Y` line, add your changes to `CHANGELOG.md` under `## [Unreleased]`, and merge into a channel branch; the release workflow tags `notebook-vX.Y.Z` and publishes the GitHub Release.

## License

AGPL-3.0-only, same as Kintai itself — see [LICENSE](LICENSE).

---

## Français

Bundle officiel "Notebook" pour [Kintai](https://github.com/AudricSan/kintai-laravel) — un carnet de notes d'équipe : les membres postent des messages visibles sur les dashboards admin et employé, avec épinglage, portée par note (un store précis ou toute l'organisation) et expiration automatique.

Fait partie de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), les bundles officiels de Kintai sous Laravel : les vues sont des gabarits Blade, et le bundle requiert le contrat de bundle `^1.31`. Ses tests, sa CI et ses releases sont communs aux autres bundles de ce dépôt (voir son README). C'est le premier bundle à créer sa propre table via le mécanisme `database/migrations/`, plutôt que de dépendre d'une table déjà fournie par Kintai Core.

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Notebook" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService` (ici la release `notebook-vX.Y.Z` de kintai-laravel-bundles, dont seul le dossier `notebook/` est installé).

### Publier une nouvelle version

Les releases se publient depuis la racine de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases) : incrémenter `version` dans `bundle.json` uniquement à l'ouverture d'une nouvelle ligne `X.Y`, ajouter les changements dans `CHANGELOG.md` sous `## [Unreleased]`, et merger vers une branche de canal ; le workflow de release tague `notebook-vX.Y.Z` et publie la release GitHub.

### Licence

AGPL-3.0-only, comme Kintai lui-même — voir [LICENSE](LICENSE).
