# timeclock

🌐 **English** · [Français](#français)

Official "Timeclock" bundle for [Kintai](https://github.com/AudricSan/kintai-laravel) — employees clock in/out, view their own history, managers manage everyone's timeclock entries.

Part of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), the official bundles of Kintai on Laravel: views are Blade templates, and the bundle requires bundle contract `^1.31`. Its tests, CI and releases are shared with the other bundles of that repository (see its README).

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Timeclock" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService` (here the `timeclock-vX.Y.Z` release of kintai-laravel-bundles, of which only the `timeclock/` folder is installed).

## Structure

```
bundle.json          # manifest — slug, version, Kintai core compatibility, entry class
src/
  TimeclockBundle.php                    # kintai\Bundles\Installed\Timeclock\TimeclockBundle
  Controllers/Web/EmployeeTimeclockController.php
  Controllers/Web/AdminTimeclockController.php
  Controllers/Api/TimeclockController.php
Views/                # employee timeclock, admin timeclocks list
lang/{en,fr,ja}.json  # bundle-specific translation keys
routes.php            # loaded by TimeclockBundle::register() via loadRoutesFrom()
```

Unlike most other bundles, this one does **not** bind its own repository:
`TimeclockRepositoryInterface` stays bound in Kintai Core's own
`RepositoryServiceProvider`, because `HomeController`'s and
`EmployeeController::dashboard()`'s "clocked in" widgets, plus
`DashboardAlertService`, depend on it directly for calculations that must
keep working even when this bundle is disabled or uninstalled. Disabling
"timeclock" only removes the dedicated UI (clock in/out, employee history,
admin entry management, `/api/v1/timeclocks`), not the underlying data or
those widgets.

## Releasing a new version

Releases are cut from the root of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases): bump `version` in `bundle.json` only when opening a new `X.Y` line, add your changes to `CHANGELOG.md` under `## [Unreleased]`, and merge into a channel branch; the release workflow tags `timeclock-vX.Y.Z` and publishes the GitHub Release.

## License

AGPL-3.0-only, same as Kintai itself — see [LICENSE](LICENSE).

---

## Français

Bundle officiel "Pointage" pour [Kintai](https://github.com/AudricSan/kintai-laravel) — les employés pointent/dépointent, consultent leur propre historique, les managers gèrent les entrées de pointage de tous.

Fait partie de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), les bundles officiels de Kintai sous Laravel : les vues sont des gabarits Blade, et le bundle requiert le contrat de bundle `^1.31`. Ses tests, sa CI et ses releases sont communs aux autres bundles de ce dépôt (voir son README).

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Timeclock" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService` (ici la release `timeclock-vX.Y.Z` de kintai-laravel-bundles, dont seul le dossier `timeclock/` est installé).

Contrairement à la plupart des autres bundles, celui-ci ne lie **pas** son propre repository : `TimeclockRepositoryInterface` reste lié dans le `RepositoryServiceProvider` du Core de Kintai, car les widgets "pointage en cours" de `HomeController` et `EmployeeController::dashboard()`, ainsi que `DashboardAlertService`, en dépendent directement pour des calculs qui doivent continuer de fonctionner même si ce bundle est désactivé ou désinstallé. Désactiver "timeclock" retire uniquement l'UI dédiée (pointer/dépointer, historique employé, gestion admin des entrées, `/api/v1/timeclocks`), pas les données elles-mêmes ni ces widgets.

### Publier une nouvelle version

Les releases se publient depuis la racine de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases) : incrémenter `version` dans `bundle.json` uniquement à l'ouverture d'une nouvelle ligne `X.Y`, ajouter les changements dans `CHANGELOG.md` sous `## [Unreleased]`, et merger vers une branche de canal ; le workflow de release tague `timeclock-vX.Y.Z` et publie la release GitHub.

### Licence

AGPL-3.0-only, comme Kintai lui-même — voir [LICENSE](LICENSE).
