# kintai-bundle-store-photos

🌐 **English** · [Français](#français)

Official "Store Photos" bundle for [Kintai](https://github.com/AudricSan/Kintai) — employees submit photos of the sales floor per store, with automatic same-day consolidation, rotation, and delayed cleanup.

This bundle used to live inside the main Kintai repository (`src/Bundles/StorePhoto/`); it's now distributed independently, the same way any third-party bundle would be — see [docs/creating-a-bundle.md](https://github.com/AudricSan/Kintai/blob/main/docs/creating-a-bundle.md) in the main repository for the full distribution model (manifest, registry, installer).

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Store Photos" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService`.

## Structure

```
bundle.json          # manifest — slug, version, Kintai core compatibility, entry class
src/
  StorePhotoBundle.php                    # kintai\Bundles\Installed\StorePhoto\StorePhotoBundle
  Controllers/Web/StorePhotoController.php
Views/                # submission list, detail, form, settings
lang/{en,fr,ja}.json  # bundle-specific translation keys
routes.php            # loaded by StorePhotoBundle::register() via loadRoutesFrom()
```

Unlike most other bundles, this one does **not** bind its own repository:
`StorePhotoRepositoryInterface` stays bound in Kintai Core's own
`RepositoryServiceProvider`, because `GithubUpdateService` (a one-time
best-effort catch-up on every self-update) and the CLI maintenance script
`scripts/consolidate-daily-photo-reports.php` both depend on it directly.
`StorePhotoConsolidationService` itself lives in Kintai Core
(`kintai\Core\Services`) for the same reason — its only two callers are
Core-resident, this bundle's own controller never uses it. Disabling
"store-photos" only removes the submission/consultation UI, not the
underlying data or that consolidation catch-up.

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

Bundle officiel "Photos de magasin" pour [Kintai](https://github.com/AudricSan/Kintai) — les employés envoient des photos de l'espace de vente par magasin, avec consolidation automatique du même jour, rotation et nettoyage différé.

Ce bundle vivait auparavant dans le dépôt principal de Kintai (`src/Bundles/StorePhoto/`) ; il est désormais distribué indépendamment, exactement comme n'importe quel bundle tiers — voir [docs/creating-a-bundle.md](https://github.com/AudricSan/Kintai/blob/main/docs/creating-a-bundle.md) dans le dépôt principal pour le modèle de distribution complet (manifest, registry, installeur).

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Store Photos" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService`.

Contrairement à la plupart des autres bundles, celui-ci ne lie **pas** son propre repository : `StorePhotoRepositoryInterface` reste lié dans le `RepositoryServiceProvider` du Core de Kintai, car `GithubUpdateService` (un rattrapage ponctuel à chaque auto-mise-à-jour) et le script CLI de maintenance `scripts/consolidate-daily-photo-reports.php` en dépendent directement. `StorePhotoConsolidationService` lui-même vit dans le Core de Kintai (`kintai\Core\Services`) pour la même raison — ses deux seuls appelants sont Core-résidents, le contrôleur de ce bundle ne l'utilise jamais. Désactiver "store-photos" retire uniquement l'UI d'envoi/consultation, pas les données elles-mêmes ni ce rattrapage de consolidation.

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
