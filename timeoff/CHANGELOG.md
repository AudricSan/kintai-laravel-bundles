# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main) est décrit dans
`.github/workflows/release.yml`.

## [Unreleased]

## [2.0.0] - 2026-10-23

Première version stable de la ligne 2 : elle regroupe, à l'identique, tout ce qui a été publié en alpha et en bêta depuis la ligne 1 (voir les sections ci-dessous), sans changement fonctionnel propre à cette version.

### Changed
- **Traductions conformes au contrat** : 25 clés renommées avec le préfixe du bundle (`timeoff_…`) et 7 clés redéfinies du Core supprimées (le bundle utilise celles du Core), sans changement de texte affiché ; `tests/TranslationContractTest.php` impose désormais parité fr/en/ja, préfixe, collisions et clés connues.

### Added

- Contrat de bundle 1.24 : `ContractUsageTest` vérifie aussi que le bundle n'appelle que des fonctions globales déclarées par le contrat (`ContractUsageScanner::functionViolations()`).

### Changed

- Notifications (contrat 1.23, `kintai_contract` `^1.23`) : types déclarés au format enrichi (catégorie « requests », décisions aussi par e-mail par défaut, `reference_type` `timeoff`) ; le lien d'une notification ouvre la liste avec la demande concernée en évidence (`?highlight=`) ; ouvrir la liste marque comme lues les notifications des demandes affichées.

- Tests : plus aucun avis PHPUnit (30 auparavant) — les doublures sans attente sont des bouchons (`createStub()`), les mocks ne sont créés que dans les tests qui posent une attente, et les vérifications `->with()` sont conservées (`expects($this->atLeastOnce())`, ou `willReturnCallback()` par argument dans les méthodes utilitaires).
- Extraction du Core (étape 4 du plan du contrat) : le bundle possède désormais tout son code et ses données. Le modèle `TimeoffRequest` et le dépôt `TimeoffRequestRepositoryInterface` / `DatabaseTimeoffRequestRepository` (`src/Models`, `src/Repositories`) quittent le Core ; la table `timeoff_requests` garde son nom et ses données. Une migration la crée si elle manque (installation neuve) ; une seconde renomme, sur les instances installées avec l'ancien schéma SQL, `reviewed_by` / `reviewed_at` / `review_notes` en `processed_by` / `processed_at` / `admin_note` en gardant les données (traiter une demande par l'API y échouait) ; une table déjà au bon schéma n'est pas touchée. « Ajouter un congé » (gérant) écrit désormais `processed_by` / `processed_at` : il échouait (erreur 500) sur une installation neuve. Les widgets « Congés en attente » des tableaux de bord gérant et employé (`dashboardWidgets()`, même clé `pending_timeoff` : les préférences sont conservées) et la carte « Congés en attente » du tableau de bord employé (`dashboardStats()`, contrat 1.19, `"kintai_contract": "^1.19"`) sont fournis par le bundle, ainsi que ses types de notification (`notificationTypes()`) ; côté gérant, la carte est pleine largeur comme les autres widgets de bundles (le Core la plaçait à côté des échanges) et la case du panneau « Personnaliser » n'est proposée qu'avec `timeoff.view`. Désactivé ou désinstallé, le bundle ne fournit plus de congés au Core (conflits du planning, export iCal, statistiques). Les libellés de la catégorie de permissions, de la fonctionnalité, des notifications, du journal d'activité, des widgets et des messages passent dans `lang/`. `ContractUsageTest` n'admet plus aucune classe du Core hors contrat ; `ContractDeclarationsTest` vérifie chaque déclaration valeur par valeur. Tests repris du Core (affectation de masse de l'API) ; nouveaux tests `TimeoffRequestsMigrationTest` (installation neuve, ancien schéma avec données, schéma déjà correct) et `PendingTimeoffWidgetTest`.
- Contrat de bundle versionné : le bundle n'utilise plus que les noms du contrat `kintai\Contract\…` du Core (requête, réponse, routes, middlewares, exceptions, droits, données du Core, services, vues et composants) et déclare `"kintai_contract": "^1.0"` dans `bundle.json` (`kintai_core.min` relevé à `0.3.6`, première version du Core qui fournit le contrat). Nouveau test `ContractUsageTest` : toute utilisation du Core hors contrat échoue, hormis 1 classe(s) propre(s) au bundle qui vivent encore dans le Core (extraction prévue).

### Added

- Contrat de bundle 1.11 : le bundle déclare lui-même ce qu'il apporte au Core (`navigation()`, `permissions()`, `storeFeatures()`, `pendingRequestsProviders()`, `absenceProviders()`) au lieu que le Core le connaisse à l'avance ; déclarations identiques à ce que le Core affichait pour ce bundle, rien ne change à l'écran. `bundle.json` demande désormais `"kintai_contract": "^1.11"` (Kintai Core avec les points d'extension et fournisseurs du contrat). Nouveau test `ContractDeclarationsTest` : chaque déclaration est comparée au repli historique du Core.
- Socle de tests PHPUnit (composer.json, phpunit.xml) et CI qui l'exécute contre le vrai Core : les tests que le Core faisait tourner sur une copie figée du bundle (fixtures 1.0.0) vivent désormais ici et testent le code réel.

### Fixed

- API en libre-service (audit n°8 de Kintai) : un employé sans permission lit enfin ses propres demandes de congé avec `GET /api/v1/timeoff-requests?user_id=<son id>`. La liste lui était toujours renvoyée vide : le filtrage par permission retirait aussi ses propres éléments. Il ne voit jamais ceux des autres ; un gérant garde exactement sa vue. Nouveau test `ApiSelfServiceListTest`.
- Approuver ou refuser un congé depuis le web enregistre désormais qui l'a fait et quand (`processed_by`, `processed_at`), comme l'API et « Ajouter un congé » ; la liste des congés du gérant l'affiche sous le statut (« Traité par … le … »). Les demandes traitées auparavant depuis le web restent sans ces informations.
- Description de `bundle.json` alignée sur celle de `lang/fr.json`, pour que le catalogue et la page Bundles affichent le même texte.
- Sécurité / intégrité — approuver ou refuser une demande de congé ignorait son statut : un gérant pouvait ressusciter une demande annulée par l'employé ou ré-approuver (double notification). Seule une demande en attente se décide. Les dates sont validées (format AAAA-MM-JJ, fin ≥ début) côté employé et gérant. Un employé multi-magasins choisit le magasin de sa demande (sélecteur) au lieu d'être rattaché à son premier magasin ; un gérant qui crée un congé pour un employé obtient son propre magasin au lieu d'un 403 ; un employé sans magasin ne peut plus déposer de demande (`store_id = 0`).
- Sécurité — `POST /api/v1/timeoff-requests` fusionnait le JSON brut du client dans `save()`, qui fait un upsert dès qu'un `id` est présent. La route de création étant en libre-service (`self => user_id`), un employé pouvait poster `status: approved` (auto-approbation), `processed_by`, un `store_id` qui n'est pas le sien, ou un `id` pour écraser la demande d'un collègue. Seuls `start_date`, `end_date`, `type` et `reason` sont lus ; `user_id` est celui de l'appelant (ou un tiers si `timeoff.create` sur le magasin), le statut initial est toujours `pending`, et l'appelant doit être membre du magasin. `PUT` ne modifie plus que les dates, le type, le motif, le statut et la note, et enregistre qui a traité la demande et quand ; `user_id` et `store_id` ne changent jamais. **Nécessite** Kintai Core avec `StoreUserRepositoryInterface` (déjà présent).

### Changed

- Compatibilité avec la Content-Security-Policy stricte de Kintai (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : l'attribut d'événement inline des vues (`onclick=`/`onchange=`/`onsubmit=`/`oninput=`) est remplacé par des attributs `data-*` (`data-on-click`, `data-submit-on-change`, `data-confirm`… gérés par `csp-actions.js` du Core). Sans ce changement, les boutons, sélecteurs et confirmations de ces vues ne font plus rien sous la nouvelle politique, sans aucune erreur visible. **Nécessite Kintai Core 0.3.0 ou plus** (`kintai_core.min`), version qui introduit `csp-actions.js` et la CSP à nonce. `tests.yml` échoue désormais si un handler inline, un lien `javascript:` ou un `<script>` sans nonce réapparaît dans `Views/` ou `src/`.

## [1.1.0] - 2026-09-29

### Added

- La soumission d'une demande de congé ne notifiait jamais les managers (contrairement à daily-report, feedback, shift-claim, notebook, qui le font tous) — ils ne l'apprenaient qu'en consultant `/admin/timeoff`. `EmployeeTimeoffController::storeTimeoff()` notifie désormais les membres du store détenant `timeoff.approve`, avec le type de congé et la plage de dates, lien vers `/admin/timeoff`.

### Changed

- Aucun changement fonctionnel — bump de version pour aligner ce bundle sur la ligne 1.1.0 commune à tous les bundles officiels.
- Les notifications de congé (ajout direct, approbation, refus) ne disaient rien du congé concerné et ne menaient nulle part au clic. Le corps précise désormais le type et la plage de dates (`notif_timeoff_added_body`/`_approved_body`/`_refused_body`, côté Kintai Core, gagnent les placeholders `:type`/`:start`/`:end`), et le clic renvoie vers `/employee/timeoff`. **Nécessite** la version de Kintai Core introduisant le paramètre `$link` sur `notify()`/`notifyMany()`.
- Le CSS du formulaire (`.timeoff-form*`) vivait mélangé au fichier Core `features/employee.css` (page espace employé), pas dans ce dépôt. Il vit maintenant dans `public/css/timeoff.css`, fourni par ce bundle via `Bundle::loadAssetsFrom()`/`bundle_asset()`. **Nécessite** `kintai_core.min: "0.2.0"`.

## [1.0.0] - 2026-09-19

### Added

- Extraction initiale depuis Kintai (`src/Bundles/TimeOff`).
