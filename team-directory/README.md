# team-directory

🌐 **English** · [Français](#français)

Official "Team Directory" bundle for [Kintai](https://github.com/AudricSan/kintai-laravel) — a colleague directory scoped to shared stores, with an extended profile page (bio, skills, languages, hobbies) an employee can opt into sharing.

Part of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), the official bundles of Kintai on Laravel: views are Blade templates, and the bundle requires bundle contract `^1.31`. Its tests, CI and releases are shared with the other bundles of that repository (see its README).

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Team Directory" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService` (here the `team-directory-vX.Y.Z` release of kintai-laravel-bundles, of which only the `team-directory/` folder is installed).

## Structure

```
bundle.json          # manifest — slug, version, Kintai core compatibility, entry class
src/
  TeamDirectoryBundle.php                    # kintai\Bundles\Installed\TeamDirectory\TeamDirectoryBundle
  Controllers/Web/TeamDirectoryController.php
Views/                # directory list, colleague profile page
lang/{en,fr,ja}.json  # bundle-specific translation keys
routes.php            # loaded by TeamDirectoryBundle::register() via loadRoutesFrom()
```

A full extraction: this bundle doesn't bind any repository of its own (it
only reads `UserRepositoryInterface`/`StoreRepositoryInterface`/
`StoreUserRepositoryInterface`, all of which are always Core-bound
regardless), and no Core component depends on anything specific to this
bundle — disabling "team-directory" removes the `/team` and `/team/{id}`
pages entirely, with no side effects elsewhere.

## Releasing a new version

Releases are cut from the root of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases): bump `version` in `bundle.json` only when opening a new `X.Y` line, add your changes to `CHANGELOG.md` under `## [Unreleased]`, and merge into a channel branch; the release workflow tags `team-directory-vX.Y.Z` and publishes the GitHub Release.

## License

AGPL-3.0-only, same as Kintai itself — see [LICENSE](LICENSE).

---

## Français

Bundle officiel "Annuaire des collègues" pour [Kintai](https://github.com/AudricSan/kintai-laravel) — un annuaire des collègues scopé aux magasins partagés, avec une fiche profil enrichie (bio, compétences, langues, loisirs) qu'un employé peut choisir de rendre visible.

Fait partie de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), les bundles officiels de Kintai sous Laravel : les vues sont des gabarits Blade, et le bundle requiert le contrat de bundle `^1.31`. Ses tests, sa CI et ses releases sont communs aux autres bundles de ce dépôt (voir son README).

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Team Directory" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService` (ici la release `team-directory-vX.Y.Z` de kintai-laravel-bundles, dont seul le dossier `team-directory/` est installé).

Extraction complète : ce bundle ne lie aucun repository qui lui soit propre (il ne fait que lire `UserRepositoryInterface`/`StoreRepositoryInterface`/`StoreUserRepositoryInterface`, tous déjà liés dans le Core de toute façon), et aucun composant Core ne dépend de quoi que ce soit propre à ce bundle — désactiver "team-directory" retire entièrement les pages `/team` et `/team/{id}`, sans effet de bord ailleurs.

### Publier une nouvelle version

Les releases se publient depuis la racine de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases) : incrémenter `version` dans `bundle.json` uniquement à l'ouverture d'une nouvelle ligne `X.Y`, ajouter les changements dans `CHANGELOG.md` sous `## [Unreleased]`, et merger vers une branche de canal ; le workflow de release tague `team-directory-vX.Y.Z` et publie la release GitHub.

### Licence

AGPL-3.0-only, comme Kintai lui-même — voir [LICENSE](LICENSE).
