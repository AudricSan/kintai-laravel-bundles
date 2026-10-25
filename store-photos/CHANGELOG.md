# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main, tags `store-photos-vX.Y.Z`) est décrit dans
`.github/workflows/release.yml`, à la racine du dépôt kintai-laravel-bundles.

## [Unreleased]

### Changed

- **Portage vers Kintai sous Laravel** (ligne 3.0, dépôt [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles)) : les vues sont des gabarits Blade (`Views/*.blade.php`), au rendu identique ; le bundle requiert le contrat de bundle `^1.31` (vues Blade dans les bundles, fourni par Kintai sous Laravel) ; ses tests tournent contre `kintai-laravel`.

## [2.0.0] - 2026-10-23

Première version stable de la ligne 2 : elle regroupe, à l'identique, tout ce qui a été publié en alpha et en bêta depuis la ligne 1 (voir les sections ci-dessous), sans changement fonctionnel propre à cette version.

### Changed
- **Traductions conformes au contrat** : 36 clés renommées avec le préfixe du bundle (`store_photos_…`), sans changement de texte affiché ; `tests/TranslationContractTest.php` impose désormais parité fr/en/ja, préfixe, collisions et clés connues.

### Added

- Contrat de bundle 1.24 : `ContractUsageTest` vérifie aussi que le bundle n'appelle que des fonctions globales déclarées par le contrat (`ContractUsageScanner::functionViolations()`).
### Changed

- Notifications (contrat 1.23, `kintai_contract` `^1.23`) : type `photo_archived` déclaré au format enrichi (catégorie « reports », push par défaut, `reference_type` `photo_submission`) ; le lien ouvre l'envoi archivé (`/admin/photos/{store}/{id}`) au lieu de la liste, et l'ouvrir marque la notification comme lue.

### Fixed

- Description de `bundle.json` alignée sur celle de `lang/fr.json`, pour que le catalogue et la page Bundles affichent le même texte.

### Changed

- Tests : plus aucun avis PHPUnit (25 auparavant) — les doublures sans attente sont des bouchons (`createStub()`), les mocks ne sont créés que dans les tests qui posent une attente, et les vérifications `->with()` sont conservées (`expects($this->atLeastOnce())`, ou `willReturnCallback()` par argument dans les méthodes utilitaires).
- Envoi de photos : la barre de progression n'a plus d'attribut `style="width:0%"` (largeur initiale déjà nulle dans le CSS du Core), en vue du retrait de `'unsafe-inline'` des styles dans la CSP de Kintai. Contrôle CI ajouté contre les styles en ligne dans les vues.
- Extraction du Core (étape 4 du plan du contrat) : le bundle possède désormais tout son code et ses données. Les modèles `StorePhotoSubmission` / `StorePhotoImage` et le dépôt `StorePhotoRepositoryInterface` / `DatabaseStorePhotoRepository` (`src/Models`, `src/Repositories`) quittent le Core ; les tables `store_photo_submissions` / `store_photo_images` gardent leur nom et leurs données, et une migration du bundle les crée si elles manquent (installation neuve). La rétention des photos, jusqu'ici un script CLI du Core (`scripts/photo-retention.php`), devient la tâche planifiée `photo-retention` du bundle (`cronJobs()`, à lancer via `/cron/run/photo-retention` avec un jeton cron) : archivage des envois plus vieux que la durée de conservation, puis suppression définitive après le délai de nettoyage ; le dossier vide du magasin est maintenant supprimé lui aussi. L'avertissement « photos archivées », un e-mail qui ne partait plus depuis la suppression de `users.is_admin`, devient une notification aux Owners (type `photo_archived`, une fois par envoi). Les libellés de l'entrée de menu, de la fonctionnalité, de la catégorie de permissions et deux messages d'erreur passent dans `lang/`. `ContractUsageTest` n'admet plus aucune classe du Core hors contrat ; `ContractDeclarationsTest` vérifie chaque déclaration valeur par valeur. Nouveaux tests `PhotoRetentionServiceTest`, `StorePhotosMigrationTest` et `StorePhotoTodaySubmissionTest` (repris du Core avec le dépôt).
- Contrat de bundle versionné : le bundle n'utilise plus que les noms du contrat `kintai\Contract\…` du Core (requête, réponse, routes, middlewares, exceptions, droits, données du Core, services, vues et composants) et déclare `"kintai_contract": "^1.0"` dans `bundle.json` (`kintai_core.min` relevé à `0.3.6`, première version du Core qui fournit le contrat). Nouveau test `ContractUsageTest` : toute utilisation du Core hors contrat échoue, hormis 1 classe(s) propre(s) au bundle qui vivent encore dans le Core (extraction prévue).

### Added

- Libellé de la tâche planifiée `photo-retention` (`cron_job_photo_retention`, fr/en/ja), affiché par `php scripts/cron.php` depuis le contrat 1.20 (convention de traduction ; sans la clé, le nom de la tâche s'affiche, aucune contrainte de version à relever).
- Contrat de bundle 1.11 : le bundle déclare lui-même ce qu'il apporte au Core (`navigation()`, `permissions()`, `storeFeatures()`) au lieu que le Core le connaisse à l'avance ; déclarations identiques à ce que le Core affichait pour ce bundle, rien ne change à l'écran. `bundle.json` demande désormais `"kintai_contract": "^1.11"` (Kintai Core avec les points d'extension et fournisseurs du contrat). Nouveau test `ContractDeclarationsTest` : chaque déclaration est comparée au repli historique du Core.
- Socle de tests PHPUnit (composer.json, phpunit.xml) et CI qui l'exécute contre le vrai Core : les tests que le Core faisait tourner sur une copie figée du bundle (fixtures 1.0.0) vivent désormais ici et testent le code réel.

### Changed

- Compatibilité avec la Content-Security-Policy stricte de Kintai (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : les 8 attributs d'événements inline des vues (`onclick=`/`onchange=`/`onsubmit=`/`oninput=`) sont remplacés par des attributs `data-*` (`data-on-click`, `data-submit-on-change`, `data-confirm`… gérés par `csp-actions.js` du Core) et le `<script>` inline porte désormais le nonce de la requête (`csp_nonce()`, gardé par `function_exists` pour ne pas planter sur un Core plus ancien). Sans ce changement, les boutons, sélecteurs et confirmations de ces vues ne font plus rien sous la nouvelle politique, sans aucune erreur visible. **Nécessite Kintai Core 0.3.0 ou plus** (`kintai_core.min`), version qui introduit `csp-actions.js` et la CSP à nonce. `tests.yml` échoue désormais si un handler inline, un lien `javascript:` ou un `<script>` sans nonce réapparaît dans `Views/` ou `src/`.

### Changed

- Aucun changement fonctionnel — bump de version pour aligner ce bundle sur la ligne 1.1.0 commune à tous les bundles officiels. Le bug d'affichage des photos inter-magasins signalé récemment vient du Core de Kintai (`PermissionMiddleware`), pas de ce bundle — voir `AudricSan/Kintai#65`.
- Le CSS (`.photo-*`, liste/détail/carrousel) et le JS du carrousel (`photo-carousel.js`) vivaient dans Kintai Core. Ils vivent maintenant dans `public/css/photos.css`/`public/js/photo-carousel.js`, fournis par ce bundle via `Bundle::loadAssetsFrom()`/`bundle_asset()`. Les règles génériques `.progress-bar*`/`.card` (aussi utilisées nativement par le Core, ex. `/admin/bundles/market`) restent des composants Core partagés, pas migrées. **Nécessite** `kintai_core.min: "0.2.0"`.

## [1.0.0] - 2026-09-19

### Added

- Extraction initiale depuis Kintai (`src/Bundles/StorePhoto`).
