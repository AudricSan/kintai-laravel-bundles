# salary-report

🌐 **English** · [Français](#français)

Official "Salary Report" bundle for [Kintai](https://github.com/AudricSan/kintai-laravel) — monthly salary reports per store or per employee, pre-filled from daily reports and shifts, with a recalculation modal and PDF export (single and bulk), at `/admin/reports/salary` and `/admin/stores/{id}/reports/salary/*`.

Part of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), the official bundles of Kintai on Laravel: views are Blade templates, and the bundle requires bundle contract `^1.31`. Its tests, CI and releases are shared with the other bundles of that repository (see its README). Unlike its `daily-report`/`hiring-report`/`resignation-report` cousins, this bundle ships a real PHPUnit suite (`tests/`) — see [CLAUDE.md](CLAUDE.md#running-tests) for how.

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Salary Report" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService` (here the `salary-report-vX.Y.Z` release of kintai-laravel-bundles, of which only the `salary-report/` folder is installed).

## Structure

```
bundle.json          # manifest — slug, version, Kintai core compatibility, entry class
src/
  SalaryReportBundle.php                        # kintai\Bundles\Installed\SalaryReport\SalaryReportBundle
  Controllers/Web/AdminSalaryReportController.php
Views/                # list, form, detail, PDF template (single + bulk export)
public/{css,js}/      # this bundle's own CSS/JS, served via Bundle::loadAssetsFrom()
lang/{en,fr,ja}.json  # bundle-specific translation keys
routes.php            # loaded by SalaryReportBundle::register() via loadRoutesFrom()
tests/                # real PHPUnit suite (unusual for a Kintai bundle — see CLAUDE.md#running-tests)
composer.json         # test-only tooling; never used at runtime (Kintai loads this bundle dynamically, not via Composer)
```

## Releasing a new version

Releases are cut from the root of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases): bump `version` in `bundle.json` only when opening a new `X.Y` line, add your changes to `CHANGELOG.md` under `## [Unreleased]`, and merge into a channel branch; the release workflow tags `salary-report-vX.Y.Z` and publishes the GitHub Release.

## License

AGPL-3.0-only, same as Kintai itself — see [LICENSE](LICENSE).

---

## Français

Bundle officiel "Rapport de salaire" pour [Kintai](https://github.com/AudricSan/kintai-laravel) — rapports de salaire mensuels par magasin ou par employé, pré-remplis depuis les rapports journaliers et les shifts, avec une modale de recalcul et un export PDF (unitaire et groupé), sur `/admin/reports/salary` et `/admin/stores/{id}/reports/salary/*`.

Fait partie de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), les bundles officiels de Kintai sous Laravel : les vues sont des gabarits Blade, et le bundle requiert le contrat de bundle `^1.31`. Ses tests, sa CI et ses releases sont communs aux autres bundles de ce dépôt (voir son README). Contrairement à ses cousins `daily-report`/`hiring-report`/`resignation-report`, ce bundle embarque une vraie suite PHPUnit (`tests/`) — voir [CLAUDE.md](CLAUDE.md#running-tests) pour le détail.

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Salary Report" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService` (ici la release `salary-report-vX.Y.Z` de kintai-laravel-bundles, dont seul le dossier `salary-report/` est installé).

### Publier une nouvelle version

Les releases se publient depuis la racine de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases) : incrémenter `version` dans `bundle.json` uniquement à l'ouverture d'une nouvelle ligne `X.Y`, ajouter les changements dans `CHANGELOG.md` sous `## [Unreleased]`, et merger vers une branche de canal ; le workflow de release tague `salary-report-vX.Y.Z` et publie la release GitHub.

### Licence

AGPL-3.0-only, comme Kintai lui-même — voir [LICENSE](LICENSE).
