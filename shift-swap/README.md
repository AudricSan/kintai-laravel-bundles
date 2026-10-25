# shift-swap

🌐 **English** · [Français](#français)

Official "Shift Swap" bundle for [Kintai](https://github.com/AudricSan/kintai-laravel) — employees request to swap one of their shifts with a colleague's, a manager approves or rejects the request.

Part of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), the official bundles of Kintai on Laravel: views are Blade templates, and the bundle requires bundle contract `^1.31`. Its tests, CI and releases are shared with the other bundles of that repository (see its README).

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Shift Swap" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService` (here the `shift-swap-vX.Y.Z` release of kintai-laravel-bundles, of which only the `shift-swap/` folder is installed).

## Structure

```
bundle.json          # manifest — slug, version, Kintai core compatibility, entry class
src/
  ShiftSwapBundle.php                    # kintai\Bundles\Installed\ShiftSwap\ShiftSwapBundle
  Controllers/Web/EmployeeSwapController.php
  Controllers/Web/AdminSwapController.php
  Controllers/Api/ShiftSwapRequestController.php
Views/                # swap request form, employee/admin swap lists
lang/{en,fr,ja}.json  # bundle-specific translation keys
routes.php            # loaded by ShiftSwapBundle::register() via loadRoutesFrom()
```

Unlike most other bundles, this one does **not** bind its own repository:
`ShiftSwapRequestRepositoryInterface` stays bound in Kintai Core's own
`RepositoryServiceProvider`, because several Core components
(`StoreStatsService`, `HomeController`, `EmployeeController`'s dashboard)
depend on it directly for calculations that must keep working even when this
bundle is disabled or uninstalled. Disabling "shift-swap" only removes the
swap-request management UI, not the underlying data.

## Releasing a new version

Releases are cut from the root of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases): bump `version` in `bundle.json` only when opening a new `X.Y` line, add your changes to `CHANGELOG.md` under `## [Unreleased]`, and merge into a channel branch; the release workflow tags `shift-swap-vX.Y.Z` and publishes the GitHub Release.

## License

AGPL-3.0-only, same as Kintai itself — see [LICENSE](LICENSE).

---

## Français

Bundle officiel "Échanges de shifts" pour [Kintai](https://github.com/AudricSan/kintai-laravel) — un employé demande à échanger l'un de ses shifts avec celui d'un collègue, un manager approuve ou rejette la demande.

Fait partie de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), les bundles officiels de Kintai sous Laravel : les vues sont des gabarits Blade, et le bundle requiert le contrat de bundle `^1.31`. Ses tests, sa CI et ses releases sont communs aux autres bundles de ce dépôt (voir son README).

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Shift Swap" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService` (ici la release `shift-swap-vX.Y.Z` de kintai-laravel-bundles, dont seul le dossier `shift-swap/` est installé).

Contrairement à la plupart des autres bundles, celui-ci ne lie **pas** son propre repository : `ShiftSwapRequestRepositoryInterface` reste lié dans le `RepositoryServiceProvider` du Core de Kintai, car plusieurs composants Core (`StoreStatsService`, `HomeController`, le tableau de bord d'`EmployeeController`) en dépendent directement pour des calculs qui doivent continuer de fonctionner même si ce bundle est désactivé ou désinstallé. Désactiver "shift-swap" retire uniquement l'UI de gestion des échanges, pas les données elles-mêmes.

### Publier une nouvelle version

Les releases se publient depuis la racine de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases) : incrémenter `version` dans `bundle.json` uniquement à l'ouverture d'une nouvelle ligne `X.Y`, ajouter les changements dans `CHANGELOG.md` sous `## [Unreleased]`, et merger vers une branche de canal ; le workflow de release tague `shift-swap-vX.Y.Z` et publie la release GitHub.

### Licence

AGPL-3.0-only, comme Kintai lui-même — voir [LICENSE](LICENSE).
