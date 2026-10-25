# kintai-bundle-timeclock

🌐 **English** · [Français](#français)

Official "Timeclock" bundle for [Kintai](https://github.com/AudricSan/Kintai) — employees clock in/out, view their own history, managers manage everyone's timeclock entries.

This bundle used to live inside the main Kintai repository (`src/Bundles/Timeclock/`); it's now distributed independently, the same way any third-party bundle would be — see [docs/creating-a-bundle.md](https://github.com/AudricSan/Kintai/blob/main/docs/creating-a-bundle.md) in the main repository for the full distribution model (manifest, registry, installer).

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Timeclock" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService`.

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

`main`, `alpha`, and `beta` are protected branches — no direct push. Releases
are cut by opening a PR into the target channel branch and merging it once CI
is green; you never tag or run `gh release create` by hand. See
[CONTRIBUTING.md](CONTRIBUTING.md) and [CLAUDE.md](CLAUDE.md#release-process)
for the full flow.

1. Bump `version` in `bundle.json` by hand only when opening a new `X.Y` line.
2. Add your changes to `CHANGELOG.md` under `## [Unreleased]`.
3. Open a PR targeting `alpha` (new work), `beta`, or `main`, and merge it once CI passes.
4. `.github/workflows/release.yml` computes the tag (`vX.Y.Z` on alpha/beta, `vX.Y.0` on main) and creates the GitHub Release automatically — Kintai's installer reads its `zipball_url` directly, nothing else to build or upload.

## License

AGPL-3.0-only, same as Kintai itself — see [LICENSE](LICENSE).

---

## Français

Bundle officiel "Pointage" pour [Kintai](https://github.com/AudricSan/Kintai) — les employés pointent/dépointent, consultent leur propre historique, les managers gèrent les entrées de pointage de tous.

Ce bundle vivait auparavant dans le dépôt principal de Kintai (`src/Bundles/Timeclock/`) ; il est désormais distribué indépendamment, exactement comme n'importe quel bundle tiers — voir [docs/creating-a-bundle.md](https://github.com/AudricSan/Kintai/blob/main/docs/creating-a-bundle.md) dans le dépôt principal pour le modèle de distribution complet (manifest, registry, installeur).

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Timeclock" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService`.

Contrairement à la plupart des autres bundles, celui-ci ne lie **pas** son propre repository : `TimeclockRepositoryInterface` reste lié dans le `RepositoryServiceProvider` du Core de Kintai, car les widgets "pointage en cours" de `HomeController` et `EmployeeController::dashboard()`, ainsi que `DashboardAlertService`, en dépendent directement pour des calculs qui doivent continuer de fonctionner même si ce bundle est désactivé ou désinstallé. Désactiver "timeclock" retire uniquement l'UI dédiée (pointer/dépointer, historique employé, gestion admin des entrées, `/api/v1/timeclocks`), pas les données elles-mêmes ni ces widgets.

### Publier une nouvelle version

`main`, `alpha` et `beta` sont des branches protégées — pas de push direct.
Les releases sont publiées en ouvrant une PR vers la branche de canal visée
et en la mergeant une fois la CI verte ; on ne tague ni ne lance
`gh release create` à la main. Voir [CONTRIBUTING.md](CONTRIBUTING.md) et
[CLAUDE.md](CLAUDE.md#release-process) pour le détail du flux.

1. Incrémenter `version` dans `bundle.json` à la main, uniquement à l'ouverture d'une nouvelle ligne `X.Y`.
2. Ajouter les changements dans `CHANGELOG.md` sous `## [Unreleased]`.
3. Ouvrir une PR vers `alpha` (travail courant), `beta`, ou `main`, et la merger une fois la CI verte.
4. `.github/workflows/release.yml` calcule le tag (`vX.Y.Z` sur alpha/beta, `vX.Y.0` sur main) et crée automatiquement la GitHub Release — l'installeur de Kintai lit directement son `zipball_url`, rien d'autre à construire ni à uploader.

### Licence

AGPL-3.0-only, comme Kintai lui-même — voir [LICENSE](LICENSE).
