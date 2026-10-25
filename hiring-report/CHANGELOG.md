# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main, tags `hiring-report-vX.Y.Z`) est décrit dans
`.github/workflows/release.yml`, à la racine du dépôt kintai-laravel-bundles.

## [Unreleased]

### Changed

- **Portage vers Kintai sous Laravel** (ligne 3.0, dépôt [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles)) : les vues sont des gabarits Blade (`Views/*.blade.php`), au rendu identique ; le bundle requiert le contrat de bundle `^1.31` (vues Blade dans les bundles, fourni par Kintai sous Laravel) ; ses tests tournent contre `kintai-laravel`.

### Fixed

- **PDF : feuilles de style et barre d'aperçu à nouveau incluses.** Leurs chemins étaient calculés depuis l'ancien emplacement du bundle dans le Core (`src/Bundles/…`) : une fois le bundle installé dans `storage/bundles/`, l'aperçu et le PDF sortaient sans mise en forme ni bouton de téléchargement. Ils passent désormais par la racine de Kintai et le partiel du Core.

## [2.0.0] - 2026-10-23

Première version stable de la ligne 2 : elle regroupe, à l'identique, tout ce qui a été publié en alpha et en bêta depuis la ligne 1 (voir les sections ci-dessous), sans changement fonctionnel propre à cette version.

### Changed
- **Traductions conformes au contrat** : 20 clés renommées avec le préfixe du bundle (`hiring_report_…`) et 1 clés redéfinies du Core supprimées (le bundle utilise celles du Core), sans changement de texte affiché ; `tests/TranslationContractTest.php` impose désormais parité fr/en/ja, préfixe, collisions et clés connues.

### Added

- Contrat de bundle 1.24 : `ContractUsageTest` vérifie aussi que le bundle n'appelle que des fonctions globales déclarées par le contrat (`ContractUsageScanner::functionViolations()`).

### Changed

- Tests : plus aucun avis PHPUnit (10 auparavant) — les doublures sans attente sont des bouchons (`createStub()`), les mocks ne sont créés que dans les tests qui posent une attente, et les vérifications `->with()` sont conservées (`expects($this->atLeastOnce())`, ou `willReturnCallback()` par argument dans les méthodes utilitaires).
- Aperçu PDF du rapport d'embauche : le bloc `<style>` porte le nonce CSP de la requête, en vue du retrait de `'unsafe-inline'` des styles dans la CSP de Kintai. Contrôle CI ajouté contre les styles en ligne dans les vues.
- Extraction du Core (étape 4 du plan du contrat) : le bundle possède désormais tout son code et ses données. Le modèle `HiringReport` et le dépôt `HiringReportRepositoryInterface` / `DatabaseHiringReportRepository` (`src/Models`, `src/Repositories`) quittent le Core ; la table `hiring_reports` garde son nom et ses données, et une migration du bundle la crée si elle manque (installation neuve), directement au schéma actuel (furigana en nom/prénom). Le rapport d'embauche d'un nouvel employé est désormais créé par le bundle, à l'écoute de l'événement `EmployeeCreated` du Core (`eventListeners()`, contrat 1.13, `"kintai_contract": "^1.13"`) : bundle désactivé, aucun rapport n'est créé. Celui d'un employé créé par l'import Excel est rempli comme celui du formulaire (fiche de l'employé et date d'embauche du jour), au lieu du seul magasin, numéro et nom. Les libellés de la catégorie de permissions, du choix du magasin, des filtres et du message « rapport introuvable » passent dans `lang/`. `ContractUsageTest` n'admet plus aucune classe du Core hors contrat ; `ContractDeclarationsTest` vérifie chaque déclaration valeur par valeur. Nouveaux tests `HiringReportsMigrationTest` et `HiringReportOnEmployeeCreatedTest`.
- Contrat de bundle versionné : le bundle n'utilise plus que les noms du contrat `kintai\Contract\…` du Core (requête, réponse, routes, middlewares, exceptions, droits, données du Core, services, vues et composants) et déclare `"kintai_contract": "^1.0"` dans `bundle.json` (`kintai_core.min` relevé à `0.3.6`, première version du Core qui fournit le contrat). Nouveau test `ContractUsageTest` : toute utilisation du Core hors contrat échoue, hormis 1 classe(s) propre(s) au bundle qui vivent encore dans le Core (extraction prévue).

### Added

- Contrat de bundle 1.11 : le bundle déclare lui-même ce qu'il apporte au Core (`navigation()`, `permissions()`) au lieu que le Core le connaisse à l'avance ; déclarations identiques à ce que le Core affichait pour ce bundle, rien ne change à l'écran. `bundle.json` demande désormais `"kintai_contract": "^1.11"` (Kintai Core avec les points d'extension et fournisseurs du contrat). Nouveau test `ContractDeclarationsTest` : chaque déclaration est comparée au repli historique du Core.
- Socle de tests PHPUnit (composer.json, phpunit.xml) et CI qui l'exécute contre le vrai Core : les tests que le Core faisait tourner sur une copie figée du bundle (fixtures 1.0.0) vivent désormais ici et testent le code réel.

### Fixed

- Création et modification manuelles d'un rapport d'embauche impossibles (erreur 500 « table hiring_reports has no column named furigana ») : le formulaire écrivait encore la colonne combinée `furigana`, supprimée par le Core le 23/09/2026 et absente de la migration du bundle. Le champ est retiré ; le rapport créé automatiquement à la création d'un employé n'était pas touché. Nouveau test : chaque champ du formulaire doit exister dans la table créée par la migration.
- Description du bundle corrigée (`bundle.json` et `lang/`) : elle affirmait que les rapports d'embauche étaient générés à la création d'un employé « même si ce bundle est désactivé », ce qui n'est plus vrai depuis l'extraction du Core (la génération passe par l'écouteur `EmployeeCreated` du bundle).
- Sécurité — le `user_id` posté à la création d'un rapport d'embauche n'était pas vérifié : il doit être membre du magasin du rapport. Le formulaire ne propose plus que les membres du magasin au lieu de tous les comptes de l'instance (le contrôleur reçoit désormais `StoreUserRepositoryInterface`).

### Changed

- Compatibilité avec la Content-Security-Policy stricte de Kintai (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : les 3 attributs d'événements inline des vues (`onclick=`/`onchange=`/`onsubmit=`/`oninput=`) sont remplacés par des attributs `data-*` (`data-on-click`, `data-submit-on-change`, `data-confirm`… gérés par `csp-actions.js` du Core). Sans ce changement, les boutons, sélecteurs et confirmations de ces vues ne font plus rien sous la nouvelle politique, sans aucune erreur visible. **Nécessite Kintai Core 0.3.0 ou plus** (`kintai_core.min`), version qui introduit `csp-actions.js` et la CSP à nonce. `tests.yml` échoue désormais si un handler inline, un lien `javascript:` ou un `<script>` sans nonce réapparaît dans `Views/` ou `src/`.

### Changed

- Aucun changement fonctionnel — bump de version pour aligner ce bundle sur la ligne 1.1.0 commune à tous les bundles officiels.
- Le CSS du PDF (`pdf-hiring-report.css`) vivait dans Kintai Core. Il vit maintenant dans `public/css/pdf-hiring-report.css`, fourni par ce bundle via `Bundle::loadAssetsFrom()`/`bundle_asset_path()`. Corrige au passage le même bug latent que `daily-report` : `dirname(__DIR__, 4)` ne résolvait pas correctement pour un bundle installé dynamiquement. **Nécessite** `kintai_core.min: "0.2.0"`.

## [1.0.0] - 2026-09-19

### Added

- Extraction initiale depuis Kintai (`src/Bundles/HiringReport`).
