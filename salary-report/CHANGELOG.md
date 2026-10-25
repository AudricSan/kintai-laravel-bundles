# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main) est décrit dans
`.github/workflows/release.yml`.

## [Unreleased]

## [2.0.0] - 2026-10-23

Première version stable de la ligne 2 : elle regroupe, à l'identique, tout ce qui a été publié en alpha et en bêta depuis la ligne 1 (voir les sections ci-dessous), sans changement fonctionnel propre à cette version.

### Changed
- **Traductions conformes au contrat** : 70 clés renommées avec le préfixe du bundle (`salary_report_…`) et 13 clés redéfinies du Core supprimées (le bundle utilise celles du Core), sans changement de texte affiché ; `tests/TranslationContractTest.php` impose désormais parité fr/en/ja, préfixe, collisions et clés connues.

### Added

- Contrat de bundle 1.24 : `ContractUsageTest` vérifie aussi que le bundle n'appelle que des fonctions globales déclarées par le contrat (`ContractUsageScanner::functionViolations()`) ; les helpers de fiche de paie (`payslip_*`) sont désormais fournis par le bundle (`Views/_payslip-helpers.php`) au lieu d'un partiel du Core.
- Contrat de bundle 1.11 : le bundle déclare lui-même ce qu'il apporte au Core (`navigation()`, `actions()`) au lieu que le Core le connaisse à l'avance ; déclarations identiques à ce que le Core affichait pour ce bundle, rien ne change à l'écran. `bundle.json` demande désormais `"kintai_contract": "^1.11"` (Kintai Core avec les points d'extension et fournisseurs du contrat). Nouveau test `ContractDeclarationsTest` : chaque déclaration est comparée au repli historique du Core.

### Changed

- Tests : plus aucun avis PHPUnit (28 auparavant) — les doublures sans attente sont des bouchons (`createStub()`), les mocks ne sont créés que dans les tests qui posent une attente, et les vérifications `->with()` sont conservées (`expects($this->atLeastOnce())`, ou `willReturnCallback()` par argument dans les méthodes utilitaires).
- Aperçus PDF (rapport et export) : les blocs `<style>` portent le nonce CSP de la requête, en vue du retrait de `'unsafe-inline'` des styles dans la CSP de Kintai. Contrôle CI ajouté contre les styles en ligne dans les vues.
- Ligne de version 1.2.0 (au lieu de 1.1.0), comme les onze autres bundles extraits du Core : tous exigent désormais le contrat ^1.19 et sont publiés sur la même ligne (premiers alphas v1.2.1).
- Extraction du Core (étape 4 du plan du contrat) : le bundle possède désormais tout son code et ses données. Le modèle `SalaryReport` et le dépôt `SalaryReportRepositoryInterface` / `DatabaseSalaryReportRepository` (`src/Models`, `src/Repositories`) quittent le Core ; la table `salary_reports` garde son nom et ses données, et une migration du bundle la crée si elle manque (installation neuve), directement au schéma actuel (rapport par employé, salaire par virement). Le total des ventes du mois n'est plus lu dans la table des rapports journaliers mais auprès des fournisseurs de ventes du contrat (`kintai\Contract\Services\Sales`, contrat 1.14, `"kintai_contract": "^1.14"`) : même calcul (jours validés ou soumis, valeurs du jour même en saisie cumulée), et sans daily-report le total reste à 0. Les libellés de l'entrée de menu, du bulletin de paie et du message « rapport introuvable » passent dans `lang/`. `ContractUsageTest` n'admet plus aucune classe du Core hors contrat ; `ContractDeclarationsTest` vérifie chaque déclaration valeur par valeur. Nouveau test `SalaryReportsMigrationTest`.
- Contrat de bundle versionné : le bundle n'utilise plus que les noms du contrat `kintai\Contract\…` du Core (requête, réponse, routes, middlewares, exceptions, droits, données du Core, services, vues et composants) et déclare `"kintai_contract": "^1.0"` dans `bundle.json` (`kintai_core.min` relevé à `0.3.6`, première version du Core qui fournit le contrat). Nouveau test `ContractUsageTest` : toute utilisation du Core hors contrat échoue, hormis 4 classe(s) propre(s) au bundle qui vivent encore dans le Core (extraction prévue).

### Fixed

- Description du bundle complétée (`bundle.json` et `lang/`) : un rapport de salaire peut viser un magasin ou un employé, la description ne citait que le magasin.
- Sécurité — le `user_id` posté à la création d'un rapport de salaire n'était pas vérifié : il doit être membre du magasin du rapport.

### Changed

- Compatibilité avec la Content-Security-Policy stricte de Kintai (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : les 10 attributs d'événements inline des vues (`onclick=`/`onchange=`/`onsubmit=`/`oninput=`) sont remplacés par des attributs `data-*` (`data-on-click`, `data-submit-on-change`, `data-confirm`… gérés par `csp-actions.js` du Core) et le `<script>` inline porte désormais le nonce de la requête (`csp_nonce()`, gardé par `function_exists` pour ne pas planter sur un Core plus ancien). Sans ce changement, les boutons, sélecteurs et confirmations de ces vues ne font plus rien sous la nouvelle politique, sans aucune erreur visible. **Nécessite Kintai Core 0.3.0 ou plus** (`kintai_core.min`), version qui introduit `csp-actions.js` et la CSP à nonce. `tests.yml` échoue désormais si un handler inline, un lien `javascript:` ou un `<script>` sans nonce réapparaît dans `Views/` ou `src/`.

## [1.0.0] - 2026-09-29

### Added

- Extraction depuis le monorepo Kintai (`src/Bundles/SalaryReport`), republication en dépôt autonome — même opération que pour `hiring-report`/`resignation-report`. Rapports de salaire mensuels par magasin ou par employé, pré-remplis depuis les rapports journaliers et les shifts, avec modale de recalcul et export PDF (unitaire et groupé).
- Contrairement à ses cousins `daily-report`/`hiring-report`/`resignation-report`, ce dépôt embarque dès sa création une vraie suite PHPUnit (`tests/`, 42 tests) : `composer.json` mappe `kintai\` directement vers les vraies classes du dépôt Kintai principal, sur le modèle de `kintai-bundle-notebook`. Couvre le contrôleur (CRUD, export JSON/PDF, calcul multi-tranches via `ShiftWageCalculator`, retenues, rapport employé vs magasin), les routes (protection `AuthMiddleware`/`PermissionMiddleware`) et le rendu des vues détail/formulaire (formatage monétaire, devise kanji/internationale).
- Son propre CSS/JS (`.sr-period*`, `salary-report-form.js`, le CSS du PDF) est fourni dès le départ via le nouveau mécanisme `Bundle::loadAssetsFrom()`/`bundle_asset()`/`bundle_asset_path()` de Kintai Core (`kintai_core.min: "0.2.0"`) — pas de dette à migrer après coup, contrairement aux bundles déjà publiés avant l'existence de ce mécanisme.
