# shift-claim

🌐 **English** · [Français](#français)

Official "Shift Claim" (open shifts / shift bidding) bundle for [Kintai](https://github.com/AudricSan/kintai-laravel) — managers publish an unfilled shift as "open", employees browse and claim it, a manager approves or rejects the claim.

Part of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), the official bundles of Kintai on Laravel: views are Blade templates, and the bundle requires bundle contract `^1.31`. Its tests, CI and releases are shared with the other bundles of that repository (see its README).

## Installing

From a running Kintai instance, as the Owner: `/admin/bundles/market` → find "Shift Claim" (listed as official, from the official Kintai registry) → Install.

Manual installation isn't supported: Kintai never does `git clone`/`pull` for bundles (many shared-hosting environments have no `git` CLI available to PHP) — it always downloads a tagged GitHub Release's zipball through `BundleInstallerService` (here the `shift-claim-vX.Y.Z` release of kintai-laravel-bundles, of which only the `shift-claim/` folder is installed).

## Structure

```
bundle.json          # manifest — slug, version, Kintai core compatibility, entry class
src/
  ShiftClaimBundle.php                          # kintai\Bundles\Installed\ShiftClaim\ShiftClaimBundle
  Controllers/Web/EmployeeShiftClaimController.php
  Controllers/Web/AdminShiftClaimController.php
  Controllers/Api/ShiftClaimController.php
Views/                # open shifts list, shift-to-publish picker
lang/{en,fr,ja}.json  # bundle-specific translation keys
routes.php            # loaded by ShiftClaimBundle::register() via loadRoutesFrom()
```

## Releasing a new version

Releases are cut from the root of [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases): bump `version` in `bundle.json` only when opening a new `X.Y` line, add your changes to `CHANGELOG.md` under `## [Unreleased]`, and merge into a channel branch; the release workflow tags `shift-claim-vX.Y.Z` and publishes the GitHub Release.

## License

AGPL-3.0-only, same as Kintai itself — see [LICENSE](LICENSE).

---

## Français

Bundle officiel "Bourse aux shifts" pour [Kintai](https://github.com/AudricSan/kintai-laravel) — un manager publie un shift non pourvu comme "ouvert", les employés le consultent et candidatent, un manager approuve ou rejette la candidature.

Fait partie de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles), les bundles officiels de Kintai sous Laravel : les vues sont des gabarits Blade, et le bundle requiert le contrat de bundle `^1.31`. Ses tests, sa CI et ses releases sont communs aux autres bundles de ce dépôt (voir son README).

### Installation

Depuis une instance Kintai en cours d'exécution, en tant qu'Owner : `/admin/bundles/market` → trouver "Shift Claim" (listé comme officiel, depuis le registry officiel Kintai) → Installer.

L'installation manuelle n'est pas prise en charge : Kintai ne fait jamais de `git clone`/`pull` pour ses bundles (de nombreux hébergements mutualisés n'exposent pas le CLI `git` à PHP) — il télécharge toujours le zipball d'une release GitHub taguée via `BundleInstallerService` (ici la release `shift-claim-vX.Y.Z` de kintai-laravel-bundles, dont seul le dossier `shift-claim/` est installé).

### Publier une nouvelle version

Les releases se publient depuis la racine de [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles#releases) : incrémenter `version` dans `bundle.json` uniquement à l'ouverture d'une nouvelle ligne `X.Y`, ajouter les changements dans `CHANGELOG.md` sous `## [Unreleased]`, et merger vers une branche de canal ; le workflow de release tague `shift-claim-vX.Y.Z` et publie la release GitHub.

### Licence

AGPL-3.0-only, comme Kintai lui-même — voir [LICENSE](LICENSE).
