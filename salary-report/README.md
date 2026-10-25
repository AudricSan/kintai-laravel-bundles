# kintai-bundle-salary-report

🌐 **English** · [Français](#français)

Official "Salary Report" bundle for [Kintai](https://github.com/AudricSan/Kintai) — monthly salary reports per store or per employee, pre-filled from daily reports and shifts, with a recalculation modal and PDF export (single and bulk), at `/admin/reports/salary` and `/admin/stores/{id}/reports/salary/*`.

This bundle used to live inside the main Kintai repository (`src/Bundles/SalaryReport/`); it's now distributed independently, the same way any third-party bundle would be — see [docs/creating-a-bundle.md](https://github.com/AudricSan/Kintai/blob/main/docs/creating-a-bundle.md) in the main repository for the full distribution model (manifest, registry, installer). Unlike its `daily-report`/`hiring-report`/`resignation-report` cousins, this bundle ships a real PHPUnit suite (`tests/`) — see [CLAUDE.md](CLAUDE.md#running-tests) for how.

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Salary Report" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService`.

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

Bundle officiel "Rapport de salaire" pour [Kintai](https://github.com/AudricSan/Kintai) — rapports de salaire mensuels par magasin ou par employé, pré-remplis depuis les rapports journaliers et les shifts, avec une modale de recalcul et un export PDF (unitaire et groupé), sur `/admin/reports/salary` et `/admin/stores/{id}/reports/salary/*`.

Ce bundle vivait auparavant dans le dépôt principal de Kintai (`src/Bundles/SalaryReport/`) ; il est désormais distribué indépendamment, exactement comme n'importe quel bundle tiers — voir [docs/creating-a-bundle.md](https://github.com/AudricSan/Kintai/blob/main/docs/creating-a-bundle.md) dans le dépôt principal pour le modèle de distribution complet (manifest, registry, installeur). Contrairement à ses cousins `daily-report`/`hiring-report`/`resignation-report`, ce bundle embarque une vraie suite PHPUnit (`tests/`) — voir [CLAUDE.md](CLAUDE.md#running-tests) pour le détail.

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Salary Report" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService`.

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
