# store-photos

🌐 **English** · [Français](#français)

Official "Store Photos" bundle for [Kintai](https://github.com/AudricSan/kintai-laravel) — employees submit photos of the sales floor per store, with automatic same-day consolidation, rotation, and delayed cleanup.

Part of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), the official bundles of Kintai on Laravel: views are Blade templates, and the bundle requires bundle contract `^1.31`. Its tests, CI and releases are shared with the other bundles of that repository (see its README).

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Store Photos" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService` (here the `store-photos-vX.Y.Z` release of kintai-laravel-bundles, of which only the `store-photos/` folder is installed).

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

Releases are cut from the root of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases): bump `version` in `bundle.json` only when opening a new `X.Y` line, add your changes to `CHANGELOG.md` under `## [Unreleased]`, and merge into a channel branch; the release workflow tags `store-photos-vX.Y.Z` and publishes the GitHub Release.

## License

AGPL-3.0-only, same as Kintai itself — see [LICENSE](LICENSE).

---

## Français

Bundle officiel "Photos de magasin" pour [Kintai](https://github.com/AudricSan/kintai-laravel) — les employés envoient des photos de l'espace de vente par magasin, avec consolidation automatique du même jour, rotation et nettoyage différé.

Fait partie de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), les bundles officiels de Kintai sous Laravel : les vues sont des gabarits Blade, et le bundle requiert le contrat de bundle `^1.31`. Ses tests, sa CI et ses releases sont communs aux autres bundles de ce dépôt (voir son README).

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Store Photos" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService` (ici la release `store-photos-vX.Y.Z` de kintai-laravel-bundles, dont seul le dossier `store-photos/` est installé).

Contrairement à la plupart des autres bundles, celui-ci ne lie **pas** son propre repository : `StorePhotoRepositoryInterface` reste lié dans le `RepositoryServiceProvider` du Core de Kintai, car `GithubUpdateService` (un rattrapage ponctuel à chaque auto-mise-à-jour) et le script CLI de maintenance `scripts/consolidate-daily-photo-reports.php` en dépendent directement. `StorePhotoConsolidationService` lui-même vit dans le Core de Kintai (`kintai\Core\Services`) pour la même raison — ses deux seuls appelants sont Core-résidents, le contrôleur de ce bundle ne l'utilise jamais. Désactiver "store-photos" retire uniquement l'UI d'envoi/consultation, pas les données elles-mêmes ni ce rattrapage de consolidation.

### Publier une nouvelle version

Les releases se publient depuis la racine de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases) : incrémenter `version` dans `bundle.json` uniquement à l'ouverture d'une nouvelle ligne `X.Y`, ajouter les changements dans `CHANGELOG.md` sous `## [Unreleased]`, et merger vers une branche de canal ; le workflow de release tague `store-photos-vX.Y.Z` et publie la release GitHub.

### Licence

AGPL-3.0-only, comme Kintai lui-même — voir [LICENSE](LICENSE).
