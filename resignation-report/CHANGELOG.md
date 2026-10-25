# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main, tags `resignation-report-vX.Y.Z`) est décrit dans
`.github/workflows/release.yml`, à la racine du dépôt kintai-laravel-bundles.

## [Unreleased]

## [3.0.0] - 2026-10-25

### Changed

- **Portage vers Kintai sous Laravel** (ligne 3.0, dépôt [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles)) : les vues sont des gabarits Blade (`Views/*.blade.php`), au rendu identique ; le bundle requiert le contrat de bundle `^1.31` (vues Blade dans les bundles, fourni par Kintai sous Laravel) ; ses tests tournent contre `kintai-laravel`.

### Fixed

- **PDF : feuilles de style et barre d'aperçu à nouveau incluses.** Leurs chemins étaient calculés depuis l'ancien emplacement du bundle dans le Core (`src/Bundles/…`) : une fois le bundle installé dans `storage/bundles/`, l'aperçu et le PDF sortaient sans mise en forme ni bouton de téléchargement. Ils passent désormais par la racine de Kintai et le partiel du Core.

## [2.0.0] - 2026-10-23

Première version stable de la ligne 2 : elle regroupe, à l'identique, tout ce qui a été publié en alpha et en bêta depuis la ligne 1 (voir les sections ci-dessous), sans changement fonctionnel propre à cette version.

### Changed
- **Traductions conformes au contrat** : 26 clés renommées avec le préfixe du bundle (`resignation_report_…`), sans changement de texte affiché ; `tests/TranslationContractTest.php` impose désormais parité fr/en/ja, préfixe, collisions et clés connues.

### Added

- Contrat de bundle 1.24 : `ContractUsageTest` vérifie aussi que le bundle n'appelle que des fonctions globales déclarées par le contrat (`ContractUsageScanner::functionViolations()`).

### Changed

- Tests : plus aucun avis PHPUnit (29 auparavant) — les doublures sans attente sont des bouchons (`createStub()`), les mocks ne sont créés que dans les tests qui posent une attente, et les vérifications `->with()` sont conservées (`expects($this->atLeastOnce())`, ou `willReturnCallback()` par argument dans les méthodes utilitaires).
- Aperçus PDF (rapport et export) : les blocs `<style>` portent le nonce CSP de la requête, en vue du retrait de `'unsafe-inline'` des styles dans la CSP de Kintai. Contrôle CI ajouté contre les styles en ligne dans les vues.
- Rapport de démission : le compte de l'employé n'est plus désactivé à la création du rapport mais **à sa date de démission** (tout de suite si elle est aujourd'hui ou passée), pour qu'il garde l'accès à son planning pendant son préavis. Nouvelle tâche planifiée `resignation-deactivate`, à lancer une fois par jour (`/cron/run/resignation-deactivate` avec un jeton cron, ou `php scripts/cron.php resignation-deactivate`) ; nouvelle colonne `resignation_reports.deactivated_at` (un rapport n'est appliqué qu'une fois : un compte réactivé à la main le reste ; les rapports existants sont marqués appliqués). Modifier la date suit (repousser une date atteinte rend l'accès), changer d'employé réactive l'ancien, supprimer un rapport pas encore appliqué ne touche plus au compte. Un gérant ne peut plus créer de rapport à son propre nom (il se coupait l'accès) ni, sauf un Owner, au nom d'un compte qui gère lui-même un magasin. Indication sous la date dans le formulaire.
- Extraction du Core (étape 4 du plan du contrat) : le bundle possède désormais tout son code et ses données. Le modèle `ResignationReport` et le dépôt `ResignationReportRepositoryInterface` / `DatabaseResignationReportRepository` (`src/Models`, `src/Repositories`) quittent le Core ; la table `resignation_reports` garde son nom et ses données, et une migration du bundle la crée si elle manque (installation neuve). Les libellés de l'entrée de menu, de la catégorie de permissions, du lien « Licencier », du choix du magasin et du message « rapport introuvable » passent dans `lang/`. `ContractUsageTest` n'admet plus aucune classe du Core hors contrat ; `ContractDeclarationsTest` vérifie chaque déclaration valeur par valeur. Nouveau test `ResignationReportsMigrationTest` ; `ResignationReportFilterTest` (filtres du dépôt sur une vraie base) repris du Core.
- Contrat de bundle versionné : le bundle n'utilise plus que les noms du contrat `kintai\Contract\…` du Core (requête, réponse, routes, middlewares, exceptions, droits, données du Core, services, vues et composants) et déclare `"kintai_contract": "^1.0"` dans `bundle.json` (`kintai_core.min` relevé à `0.3.6`, première version du Core qui fournit le contrat). Nouveau test `ContractUsageTest` : toute utilisation du Core hors contrat échoue, hormis 2 classe(s) propre(s) au bundle qui vivent encore dans le Core (extraction prévue).

### Added

- Contrat de bundle 1.11 : le bundle déclare lui-même ce qu'il apporte au Core (`navigation()`, `permissions()`, `actions()`) au lieu que le Core le connaisse à l'avance ; déclarations identiques à ce que le Core affichait pour ce bundle, rien ne change à l'écran. `bundle.json` demande désormais `"kintai_contract": "^1.11"` (Kintai Core avec les points d'extension et fournisseurs du contrat). Nouveau test `ContractDeclarationsTest` : chaque déclaration est comparée au repli historique du Core.
- Socle de tests PHPUnit (composer.json, phpunit.xml) et CI qui l'exécute contre le vrai Core : les tests que le Core faisait tourner sur une copie figée du bundle (fixtures 1.0.0) vivent désormais ici et testent le code réel.

### Fixed

- « Supprimer définitivement » (rapport et compte) anonymise désormais le compte, comme la fiche employé et l'API (données personnelles effacées, compte désactivé, droits et jetons retirés, historique conservé sous « Utilisateur supprimé ») : avant, une suppression douce laissait nom, e-mail, téléphone et adresse en base. Utilise `kintai\Contract\Auth\UserAnonymizer` : **nécessite le contrat 1.21** (`"kintai_contract": "^1.21"`).
- Sécurité — la création d'un rapport de démission désactivait le compte `user_id` posté sans le contrôler, et « Supprimer définitivement » le supprimait : un gérant pouvait ainsi désactiver puis supprimer n'importe quel compte (autre magasin, Owner). Le compte doit maintenant être membre du magasin du rapport, un Owner n'est modifiable que par un Owner, et la suppression définitive exige de gérer tous les magasins du compte.

### Changed

- Compatibilité avec la Content-Security-Policy stricte de Kintai (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : les 7 attributs d'événements inline des vues (`onclick=`/`onchange=`/`onsubmit=`/`oninput=`) sont remplacés par des attributs `data-*` (`data-on-click`, `data-submit-on-change`, `data-confirm`… gérés par `csp-actions.js` du Core) et le `<script>` inline porte désormais le nonce de la requête (`csp_nonce()`, gardé par `function_exists` pour ne pas planter sur un Core plus ancien). Sans ce changement, les boutons, sélecteurs et confirmations de ces vues ne font plus rien sous la nouvelle politique, sans aucune erreur visible. **Nécessite Kintai Core 0.3.0 ou plus** (`kintai_core.min`), version qui introduit `csp-actions.js` et la CSP à nonce. `tests.yml` échoue désormais si un handler inline, un lien `javascript:` ou un `<script>` sans nonce réapparaît dans `Views/` ou `src/`.

### Changed

- Aucun changement fonctionnel — bump de version pour aligner ce bundle sur la ligne 1.1.0 commune à tous les bundles officiels.
- Le CSS du PDF (`pdf-resignation-report.css`) et le JS de la modale de suppression (`resignation-report-delete-modal.js`) vivaient dans Kintai Core. Ils vivent maintenant dans `public/css/pdf-resignation-report.css`/`public/js/resignation-report-delete-modal.js`, fournis par ce bundle via `Bundle::loadAssetsFrom()`/`bundle_asset()`/`bundle_asset_path()` — corrige au passage le même bug latent de `dirname()` que `daily-report`. `pdf-export-table.css` (export PDF global, partagé avec `staff/users-export-pdf.php` du Core) reste inchangé, non migré. **Nécessite** `kintai_core.min: "0.2.0"`.

## [1.0.0] - 2026-09-19

### Added

- Extraction initiale depuis Kintai (`src/Bundles/ResignationReport`).
