# kintai-laravel-bundles

🌐 **English** · [Français](#français)

The official bundles of [Kintai](https://github.com/AudricSan/kintai-laravel) on Laravel, one folder per bundle. Their views are Blade templates and they require bundle contract `^1.31` (provided by Kintai on Laravel). Kintai's official registry points to this repository's [`registry.json`](registry.json).

| Folder | Bundle |
| --- | --- |
| [`daily-report/`](daily-report) | Daily reports: draft → submitted → validated, PDF, e-mail, REST API |
| [`hiring-report/`](hiring-report) | Hiring reports: browse, edit, PDF |
| [`messaging/`](messaging) | Internal messaging between store members, live updates (SSE), REST API |
| [`notebook/`](notebook) | Team notebook on the admin and employee dashboards: pinning, automatic expiry |
| [`planning-check/`](planning-check) | Before noon, the manager confirms tomorrow's shifts; validation, PDF to the boss, escalation when late |
| [`resignation-report/`](resignation-report) | Resignation reports per employee, JSON/PDF exports, account reactivation |
| [`salary-report/`](salary-report) | Monthly salary reports per store or employee, pre-filled from daily reports and shifts |
| [`shift-claim/`](shift-claim) | Open shifts: publication, employee applications, manager approval |
| [`shift-swap/`](shift-swap) | Shift swap requests between employees: proposal, acceptance, applied by a manager |
| [`store-photos/`](store-photos) | Weekly photos of the sales floor: upload and follow-up |
| [`team-directory/`](team-directory) | Enriched profile (photo, bio, skills, languages) and colleague directory per store |
| [`timeclock/`](timeclock) | Clock in / out, history and admin management of time entries |
| [`timeoff/`](timeoff) | Time-off requests and approval |

Each folder is a complete bundle (`bundle.json`, `src/`, `Views/`, `lang/`, `routes.php`, `database/migrations/`, `tests/`, `README.md`, `CHANGELOG.md`): it is what a Kintai instance installs, nothing else from this repository.

## Installing

From a running Kintai instance, as the Owner: **Bundles › Catalogue** (`/admin/bundles/market`), pick the bundle (listed as official) and install it. Kintai never runs `git clone`: its installer downloads the zipball of the bundle's GitHub Release (`{slug}-vX.Y.Z`), checks it against the commit pinned in `registry.json`, and installs only the `{slug}/` folder.

## Development

```bash
git clone https://github.com/AudricSan/kintai-laravel.git
git clone https://github.com/AudricSan/kintai-laravel-bundles.git
(cd kintai-laravel && composer install)
cd kintai-laravel-bundles
composer install
vendor/bin/phpunit                          # every bundle
vendor/bin/phpunit --testsuite timeoff      # one bundle
```

The tests run against the real Kintai Core: `tests/bootstrap.php` loads the autoloader of `../kintai-laravel` (or of the folder named by `KINTAI_CORE`), then this repository's. `tests/Shared/BladeViewsTest.php` compiles every Blade view and checks that every `@includeInScope` target exists. CI (`.github/workflows/tests.yml`) also lints PHP, validates JSON and refuses inline scripts and event handlers, which Kintai's Content Security Policy blocks silently.

Views: `Views/*.blade.php`, rendered by Kintai's view engine under the bundle's namespace (`timeoff::timeoff`). `@includeInScope('timeoff::partial')` inserts another view of the bundle in the scope of the current one, like a PHP `include`; `@includeInScope('_partials._pdf-preview-toolbar')` inserts a Core partial. A bundle only uses `kintai\Contract\…` classes and the global functions of the contract (`ContractUsageTest` of each bundle).

## Releases

Each bundle is released on its own, tagged `{slug}-vX.Y.Z`; the scheme is that of Kintai (see [`.github/workflows/release.yml`](.github/workflows/release.yml)):

- `develop` collects the work; `alpha`, `beta` and `main` are the release channels (no direct push, merge a PR once CI is green).
- A merge into `alpha` or `beta` publishes a prerelease `{slug}-vX.Y.Z` for each bundle whose folder changed since its last tag (Z is a counter shared by alpha and beta within a line, starting at 1). Notes come from the bundle's `CHANGELOG.md`, section `## [Unreleased]`.
- A merge into `main` publishes `{slug}-vX.Y.0`, the stable release of the line, once per line; its notes come from `## [X.Y.0]` (the release commit renames `[Unreleased]`).
- `version` in `bundle.json` (written `X.Y.0`) is bumped by hand only when opening a new line.
- After a release, `sync-registry.yml` opens a pull request into `main` that adds the new versions to `registry.json`, each pinned to its tag's commit. Review the commits, then merge: Kintai reads `registry.json` from `main`.

## License

AGPL-3.0-only, same as Kintai itself — see [LICENSE](LICENSE).

---

## Français

Les bundles officiels de [Kintai](https://github.com/AudricSan/kintai-laravel) sous Laravel, un dossier par bundle. Leurs vues sont des gabarits Blade et ils requièrent le contrat de bundle `^1.31` (fourni par Kintai sous Laravel). Le registry officiel de Kintai pointe vers le [`registry.json`](registry.json) de ce dépôt.

Chaque dossier est un bundle complet (`bundle.json`, `src/`, `Views/`, `lang/`, `routes.php`, `database/migrations/`, `tests/`, `README.md`, `CHANGELOG.md`) : c'est ce qu'une instance Kintai installe, rien d'autre de ce dépôt.

### Installation

Depuis une instance Kintai, en tant qu'Owner : **Bundles › Catalogue** (`/admin/bundles/market`), choisir le bundle (listé comme officiel) et l'installer. Kintai ne fait jamais de `git clone` : son installeur télécharge le zipball de la release GitHub du bundle (`{slug}-vX.Y.Z`), le compare au commit épinglé dans `registry.json`, et n'installe que le dossier `{slug}/`.

### Développement

```bash
git clone https://github.com/AudricSan/kintai-laravel.git
git clone https://github.com/AudricSan/kintai-laravel-bundles.git
(cd kintai-laravel && composer install)
cd kintai-laravel-bundles
composer install
vendor/bin/phpunit                          # tous les bundles
vendor/bin/phpunit --testsuite timeoff      # un bundle
```

Les tests tournent contre le vrai Core de Kintai : `tests/bootstrap.php` charge l'autoloader de `../kintai-laravel` (ou du dossier désigné par `KINTAI_CORE`), puis celui de ce dépôt. `tests/Shared/BladeViewsTest.php` compile chaque vue Blade et vérifie que chaque vue insérée par `@includeInScope` existe. La CI (`.github/workflows/tests.yml`) vérifie aussi la syntaxe PHP, les JSON, et refuse les scripts et gestionnaires d'événements en ligne, que la Content Security Policy de Kintai bloque en silence.

Vues : `Views/*.blade.php`, rendues par le moteur de vues de Kintai sous le namespace du bundle (`timeoff::timeoff`). `@includeInScope('timeoff::partiel')` insère une autre vue du bundle dans la portée de la vue courante, comme un `include` PHP ; `@includeInScope('_partials._pdf-preview-toolbar')` insère un partiel du Core. Un bundle n'utilise que les classes `kintai\Contract\…` et les fonctions globales du contrat (`ContractUsageTest` de chaque bundle).

### Releases

Chaque bundle est publié séparément, tagué `{slug}-vX.Y.Z` ; le schéma est celui de Kintai (voir [`.github/workflows/release.yml`](.github/workflows/release.yml)) :

- `develop` reçoit le travail ; `alpha`, `beta` et `main` sont les canaux de release (pas de push direct, une PR mergée une fois la CI verte).
- Un merge vers `alpha` ou `beta` publie une préversion `{slug}-vX.Y.Z` pour chaque bundle dont le dossier a changé depuis son dernier tag (Z est un compteur partagé par alpha et beta au sein d'une ligne, qui démarre à 1). Les notes viennent du `CHANGELOG.md` du bundle, section `## [Unreleased]`.
- Un merge vers `main` publie `{slug}-vX.Y.0`, la release stable de la ligne, une seule fois par ligne ; ses notes viennent de `## [X.Y.0]` (le commit de release renomme `[Unreleased]`).
- `version` dans `bundle.json` (écrit `X.Y.0`) ne s'incrémente à la main qu'à l'ouverture d'une nouvelle ligne.
- Après une release, `sync-registry.yml` ouvre une PR vers `main` qui ajoute les nouvelles versions à `registry.json`, chacune épinglée au commit de son tag. Vérifier les commits, puis merger : Kintai lit `registry.json` sur `main`.

### Licence

AGPL-3.0-only, comme Kintai lui-même — voir [LICENSE](LICENSE).
