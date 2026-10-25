# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main, tags `shift-swap-vX.Y.Z`) est décrit dans
`.github/workflows/release.yml`, à la racine du dépôt kintai-laravel-bundles.

## [Unreleased]

## [3.0.0] - 2026-10-25

### Added

- **Brouillons de planning écartés** : quand le magasin publie son planning avant de le montrer (contrat 1.32, `shift_is_published()`), un shift encore en brouillon n'est ni proposé ni accepté dans une demande d'échange. Avec un Core plus ancien, rien ne change.

### Changed

- **Portage vers Kintai sous Laravel** (ligne 3.0, dépôt [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles)) : les vues sont des gabarits Blade (`Views/*.blade.php`), au rendu identique ; le bundle requiert le contrat de bundle `^1.31` (vues Blade dans les bundles, fourni par Kintai sous Laravel) ; ses tests tournent contre `kintai-laravel`.

## [2.0.0] - 2026-10-23

Première version stable de la ligne 2 : elle regroupe, à l'identique, tout ce qui a été publié en alpha et en bêta depuis la ligne 1 (voir les sections ci-dessous), sans changement fonctionnel propre à cette version.

### Changed
- **Traductions conformes au contrat** : 55 clés renommées avec le préfixe du bundle (`shift_swap_…`), sans changement de texte affiché ; `tests/TranslationContractTest.php` impose désormais parité fr/en/ja, préfixe, collisions et clés connues.

### Added

- Contrat de bundle 1.24 : `ContractUsageTest` vérifie aussi que le bundle n'appelle que des fonctions globales déclarées par le contrat (`ContractUsageScanner::functionViolations()`).

### Changed

- Notifications (contrat 1.23, `kintai_contract` `^1.23`) : les types sont déclarés au format enrichi (catégories « requests » et « planning », `reference_type` `swap`, e-mail par défaut pour l'échange validé et les changements de planning). L'échange créé par un gestionnaire et la restitution des shifts après suppression d'un échange utilisent leurs propres types, `swap_applied` et `swap_cancelled_restored`, au lieu de réutiliser le type `shift_assigned` du Core. Les liens ouvrent la liste des échanges avec la demande concernée en évidence (`?highlight=`) ; ouvrir la liste marque comme lues les notifications des échanges affichés.

- Tests : plus aucun avis PHPUnit (24 auparavant) — les doublures sans attente sont des bouchons (`createStub()`), les mocks ne sont créés que dans les tests qui posent une attente, et les vérifications `->with()` sont conservées (`expects($this->atLeastOnce())`, ou `willReturnCallback()` par argument dans les méthodes utilitaires).
- Extraction du Core (étape 4 du plan du contrat) : le bundle possède désormais tout son code et ses données. Le modèle `ShiftSwapRequest` et le dépôt `ShiftSwapRequestRepositoryInterface` / `DatabaseShiftSwapRequestRepository` (`src/Models`, `src/Repositories`) quittent le Core ; la table `shift_swap_requests` garde son nom et ses données. Une migration la crée si elle manque (installation neuve) ; une seconde reconstruit la table des instances installées avec l'ancien schéma SQL (`target_id` obligatoire, `reviewed_by`/`reviewed_at`, `peer_accepted_at`), sur lequel créer un échange échouait (erreur 500), en recopiant les demandes existantes (`target_id` → `target_user_id`, `peer_accepted_at` → `accepted_at`, `reviewed_*` → `approved_*`) ; une table déjà au bon schéma n'est pas touchée. Le bundle déclare désormais lui-même les échanges des statistiques du Core (`swapProviders()`, contrat 1.18), le widget « Échanges en attente » des tableaux de bord gérant et employé (`dashboardWidgets()`, même clé `pending_swaps` : les préférences sont conservées), la carte « Échanges » du tableau de bord employé (`dashboardStats()`, contrat 1.19, `"kintai_contract": "^1.19"`) et ses types de notification (`notificationTypes()`). Sur le tableau de bord gérant, le widget s'affiche désormais avec ceux des autres bundles (sa position change, son contenu non). Les libellés de la catégorie de permissions, de la fonctionnalité, des notifications, du journal d'activité et des widgets passent dans `lang/`. `ContractUsageTest` n'admet plus aucune classe du Core hors contrat ; `ContractDeclarationsTest` vérifie chaque déclaration valeur par valeur. Tests repris du Core (affectation de masse de l'API) ; nouveaux tests `ShiftSwapRequestsMigrationTest` (installation neuve, ancien schéma avec données, schéma déjà correct), `SwapRequestsProviderTest` et `PendingSwapsWidgetTest`.
- Contrat de bundle versionné : le bundle n'utilise plus que les noms du contrat `kintai\Contract\…` du Core (requête, réponse, routes, middlewares, exceptions, droits, données du Core, services, vues et composants) et déclare `"kintai_contract": "^1.0"` dans `bundle.json` (`kintai_core.min` relevé à `0.3.6`, première version du Core qui fournit le contrat). Nouveau test `ContractUsageTest` : toute utilisation du Core hors contrat échoue, hormis 1 classe(s) propre(s) au bundle qui vivent encore dans le Core (extraction prévue).

### Added

- Contrat de bundle 1.11 : le bundle déclare lui-même ce qu'il apporte au Core (`navigation()`, `permissions()`, `storeFeatures()`, `actions()`, `pendingRequestsProviders()`) au lieu que le Core le connaisse à l'avance ; déclarations identiques à ce que le Core affichait pour ce bundle, rien ne change à l'écran. `bundle.json` demande désormais `"kintai_contract": "^1.11"` (Kintai Core avec les points d'extension et fournisseurs du contrat). Nouveau test `ContractDeclarationsTest` : chaque déclaration est comparée au repli historique du Core.
- Socle de tests PHPUnit (composer.json, phpunit.xml) et CI qui l'exécute contre le vrai Core : les 46 tests que le Core faisait tourner sur une copie figée du bundle (fixtures 1.0.0) vivent désormais ici et testent le code réel.

### Fixed

- API en libre-service (audit n°8 de Kintai) : un employé sans permission lit enfin ses propres demandes d'échange (comme demandeur ou destinataire) avec `GET /api/v1/shift-swap-requests?user_id=<son id>` (nouveau filtre `user_id`, ouvert en libre-service). La liste lui était toujours renvoyée vide : le filtrage par permission retirait aussi ses propres éléments. Il ne voit jamais ceux des autres ; un gérant garde exactement sa vue. Nouveau test `ApiSelfServiceListTest`.
- Sécurité — des formulaires de filtre envoyés en GET contenaient le jeton CSRF de la session, qui se retrouvait dans l'URL (historique du navigateur, journaux d'accès du serveur, liens copiés). Les formulaires GET ne le portent plus ; nouveau test `NoCsrfTokenInGetFormsTest` qui échoue si l'un d'eux le réintroduit.
- Description de `bundle.json` alignée sur celle de `lang/fr.json`, pour que le catalogue et la page Bundles affichent le même texte.
- Widget « Échanges en attente » du tableau de bord gérant : carte complète avec sa marge haute (`card card--mt`), comme le demande le contrat des widgets ; elle se collait au widget précédent depuis la reprise du Core, où elle partageait une rangée avec les congés.
- `/admin/swap-requests/create` répondait 500 pour un gérant limité à ses magasins : `AdminSwapController` n'avait pas le dépôt des membres de magasin que le trait `HasAdminAccess` utilise. Le contrôleur le reçoit maintenant (`StoreUserRepositoryInterface`).
- Sécurité — l'approbation d'un échange appliquait les shifts sans garde : approuver une demande déjà acceptée ré-échangeait les shifts (et annulait l'échange), approuver une demande refusée l'appliquait, refuser un échange appliqué le marquait « refusé » sans restituer les shifts. Seule une demande en attente se décide désormais, l'échange n'est appliqué que si les deux shifts appartiennent encore au demandeur et à la cible, un conflit de jour (deux shifts le même jour) est refusé, et la suppression d'un échange n'écrase plus des shifts réaffectés entre-temps.
- Sécurité — `POST /api/v1/shift-swap-requests` fusionnait le JSON brut du client dans `save()` (upsert dès qu'un `id` est présent) : un `id` écrasait la demande de quelqu'un d'autre en contournant `requireSwap()`, et `status`/`approved_by_id` étaient acceptés tels quels. Seuls `target_user_id`, `requester_shift_id`, `target_shift_id` et `reason` sont lus ; le magasin est obligatoire et `swaps.create` est revérifié dessus, le demandeur est l'appelant, le statut initial est `pending`. `PUT` ne modifie plus que le statut, le motif et le collègue ciblé (une approbation enregistre son auteur et sa date) ; magasin, demandeur et shifts ne changent jamais.

### Changed

- Compatibilité avec la Content-Security-Policy stricte de Kintai (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : les 5 attributs d'événements inline des vues (`onclick=`/`onchange=`/`onsubmit=`/`oninput=`) sont remplacés par des attributs `data-*` (`data-on-click`, `data-submit-on-change`, `data-confirm`… gérés par `csp-actions.js` du Core). Sans ce changement, les boutons, sélecteurs et confirmations de ces vues ne font plus rien sous la nouvelle politique, sans aucune erreur visible. **Nécessite Kintai Core 0.3.0 ou plus** (`kintai_core.min`), version qui introduit `csp-actions.js` et la CSP à nonce. `tests.yml` échoue désormais si un handler inline, un lien `javascript:` ou un `<script>` sans nonce réapparaît dans `Views/` ou `src/`.

## [1.1.0] - 2026-09-29

### Changed

- Aucun changement fonctionnel — bump de version pour aligner ce bundle sur la ligne 1.1.0 commune à tous les bundles officiels.
- Les huit notifications d'échange de shift (demande, acceptation/refus par le collègue, annulation, application/approbation/refus/suppression par un admin) ne disaient rien des shifts concernés et ne menaient nulle part au clic. Le corps précise désormais les dates des deux shifts et le magasin (le collègue nommé pour les notifications entre employés) — `notif_swap_*_body` (Kintai Core) gagnent les placeholders `:colleague`/`:my_date`/`:target_date`/`:req_date`/`:tgt_date`/`:store` selon le cas. Le clic renvoie vers `/employee/swaps`. **Nécessite** la version de Kintai Core introduisant le paramètre `$link` sur `notify()`/`notifyMany()`.
- Le CSS (`.swap-card*`/`.swap-form-row*`/`.swap-actions`) vivait mélangé au fichier Core `features/employee.css` (page espace employé), pas dans ce dépôt. Il vit maintenant dans `public/css/shift-swap.css`, fourni par ce bundle via `Bundle::loadAssetsFrom()`/`bundle_asset()`. **Nécessite** `kintai_core.min: "0.2.0"`.

## [1.0.0] - 2026-09-19

### Added

- Extraction initiale depuis Kintai (`src/Bundles/ShiftSwap`).
