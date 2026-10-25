# resignation-report

🌐 **English** · [Français](#français)

Official "Resignation Report" bundle for [Kintai](https://github.com/AudricSan/kintai-laravel) — resignation reports per employee, JSON/PDF export (single and bulk), and reactivating a resigned employee's account, at `/admin/reports/resignation` and `/admin/stores/{id}/reports/resignation/*`.

Part of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), the official bundles of Kintai on Laravel: views are Blade templates, and the bundle requires bundle contract `^1.31`. Its tests, CI and releases are shared with the other bundles of that repository (see its README).

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Resignation Report" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService` (here the `resignation-report-vX.Y.Z` release of kintai-laravel-bundles, of which only the `resignation-report/` folder is installed).

## Structure

```
bundle.json          # manifest — slug, version, Kintai core compatibility, entry class
src/
  ResignationReportBundle.php                      # kintai\Bundles\Installed\ResignationReport\ResignationReportBundle
  Controllers/Web/AdminResignationReportController.php
Views/                # list, form, detail, PDF template (single + bulk export)
lang/{en,fr,ja}.json  # bundle-specific translation keys
routes.php            # loaded by ResignationReportBundle::register() via loadRoutesFrom()
```

## Releasing a new version

Releases are cut from the root of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases): bump `version` in `bundle.json` only when opening a new `X.Y` line, add your changes to `CHANGELOG.md` under `## [Unreleased]`, and merge into a channel branch; the release workflow tags `resignation-report-vX.Y.Z` and publishes the GitHub Release.

## License

AGPL-3.0-only, same as Kintai itself — see [LICENSE](LICENSE).

---

## Français

Bundle officiel "Rapport de démission" pour [Kintai](https://github.com/AudricSan/kintai-laravel) — rapports de démission par employé, export JSON/PDF (unitaire et groupé), et réactivation du compte d'un employé démissionnaire, sur `/admin/reports/resignation` et `/admin/stores/{id}/reports/resignation/*`.

Fait partie de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), les bundles officiels de Kintai sous Laravel : les vues sont des gabarits Blade, et le bundle requiert le contrat de bundle `^1.31`. Ses tests, sa CI et ses releases sont communs aux autres bundles de ce dépôt (voir son README).

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Resignation Report" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService` (ici la release `resignation-report-vX.Y.Z` de kintai-laravel-bundles, dont seul le dossier `resignation-report/` est installé).

### Publier une nouvelle version

Les releases se publient depuis la racine de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases) : incrémenter `version` dans `bundle.json` uniquement à l'ouverture d'une nouvelle ligne `X.Y`, ajouter les changements dans `CHANGELOG.md` sous `## [Unreleased]`, et merger vers une branche de canal ; le workflow de release tague `resignation-report-vX.Y.Z` et publie la release GitHub.

### Licence

AGPL-3.0-only, comme Kintai lui-même — voir [LICENSE](LICENSE).
