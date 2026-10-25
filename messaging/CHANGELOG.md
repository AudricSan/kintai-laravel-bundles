# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main, tags `messaging-vX.Y.Z`) est décrit dans
`.github/workflows/release.yml`, à la racine du dépôt kintai-laravel-bundles.

## [Unreleased]

## [3.0.0] - 2026-10-25

### Changed

- **Portage vers Kintai sous Laravel** (ligne 3.0, dépôt [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles)) : les vues sont des gabarits Blade (`Views/*.blade.php`), au rendu identique ; le bundle requiert le contrat de bundle `^1.31` (vues Blade dans les bundles, fourni par Kintai sous Laravel) ; ses tests tournent contre `kintai-laravel`.

## [2.0.0] - 2026-10-23

Première version stable de la ligne 2 : elle regroupe, à l'identique, tout ce qui a été publié en alpha et en bêta depuis la ligne 1 (voir les sections ci-dessous), sans changement fonctionnel propre à cette version.

### Changed
- **Traductions conformes au contrat** : 26 clés renommées avec le préfixe du bundle (`messaging_…`), sans changement de texte affiché ; `tests/TranslationContractTest.php` impose désormais parité fr/en/ja, préfixe, collisions et clés connues.

### Added

- Contrat de bundle 1.24 : `ContractUsageTest` vérifie aussi que le bundle n'appelle que des fonctions globales déclarées par le contrat (`ContractUsageScanner::functionViolations()`).

### Changed

- Notifications (contrat 1.23, `kintai_contract` `^1.23`) : type `message_received` déclaré au format enrichi (catégorie « messages », push par défaut, `reference_type` `message_thread`) ; ouvrir une conversation marque comme lues ses notifications de nouveau message.

- Tests : plus aucun avis PHPUnit (12 auparavant) — les doublures sans attente sont des bouchons (`createStub()`), les mocks ne sont créés que dans les tests qui posent une attente, et les vérifications `->with()` sont conservées (`expects($this->atLeastOnce())`, ou `willReturnCallback()` par argument dans les méthodes utilitaires).
- Supprimer une conversation (web ou API) la retire désormais de **sa propre** liste seulement : avant, n'importe quel participant l'effaçait pour tous, messages compris. Elle réapparaît (non lue) si quelqu'un y répond, et n'est effacée de la base que lorsque tous ses participants l'ont retirée. Nouvelle colonne `thread_participants.hidden_at` ; une conversation retirée ne compte plus dans les non-lus. Un message envoyé par l'API marque aussi la conversation non lue chez les autres participants, comme une réponse web. Textes de confirmation mis à jour (fr/en/ja).
- Extraction du Core (étape 4 du plan du contrat) : le bundle possède désormais tout son code et ses données. Les modèles `MessageThread` / `ThreadMessage` / `ThreadParticipant` et le dépôt `MessageRepositoryInterface` / `DatabaseMessageRepository` (`src/Models`, `src/Repositories`) quittent le Core ; les tables `message_threads`, `thread_participants` et `thread_messages` gardent leur nom et leurs données, et une migration du bundle les crée si elles manquent (installation neuve). Le type de notification `message_received` est déclaré par le bundle (`notificationTypes()`) ; les libellés de l'entrée de menu, de la fonctionnalité, de la catégorie de permissions, des notifications, du journal d'activité et trois messages d'erreur passent dans `lang/`. `ContractUsageTest` n'admet plus aucune classe du Core hors contrat ; `ContractDeclarationsTest` vérifie chaque déclaration valeur par valeur. Nouveaux tests `MessagingMigrationTest` et `MessageMassAssignmentTest` (repris du Core : une création par l'API ne peut pas écraser le message ou la participation de quelqu'un d'autre).
- Contrat de bundle versionné : le bundle n'utilise plus que les noms du contrat `kintai\Contract\…` du Core (requête, réponse, routes, middlewares, exceptions, droits, données du Core, services, vues et composants) et déclare `"kintai_contract": "^1.0"` dans `bundle.json` (`kintai_core.min` relevé à `0.3.6`, première version du Core qui fournit le contrat). Nouveau test `ContractUsageTest` : toute utilisation du Core hors contrat échoue, hormis 2 classe(s) propre(s) au bundle qui vivent encore dans le Core (extraction prévue).

### Added

- Contrat de bundle 1.11 : le bundle déclare lui-même ce qu'il apporte au Core (`navigation()`, `permissions()`, `storeFeatures()`, `viewVariables()`) au lieu que le Core le connaisse à l'avance ; déclarations identiques à ce que le Core affichait pour ce bundle, rien ne change à l'écran. `bundle.json` demande désormais `"kintai_contract": "^1.11"` (Kintai Core avec les points d'extension et fournisseurs du contrat). Nouveau test `ContractDeclarationsTest` : chaque déclaration est comparée au repli historique du Core.
- Socle de tests PHPUnit (composer.json, phpunit.xml) et CI qui l'exécute contre le vrai Core : les tests que le Core faisait tourner sur une copie figée du bundle (fixtures 1.0.0) vivent désormais ici et testent le code réel.

### Changed

- Garde-fou contre les handlers inline, en prévision de la Content-Security-Policy stricte de Kintai Core 0.3.0 (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : `tests.yml` échoue désormais si un attribut `onclick=`/`onchange=`/`onsubmit=`/`oninput=`, un lien `javascript:` ou un `<script>` sans nonce apparaît dans `Views/` ou `src/` — le navigateur les bloquerait en silence, sans aucune erreur côté serveur. **Aucun changement fonctionnel** : les vues de ce bundle n'utilisent déjà aucun handler inline ni `<script>` inline exécutable. La règle est documentée dans `CONTRIBUTING.md` et `CLAUDE.md`.

### Fixed

- Schéma des tables de messagerie : il différait selon l'âge de l'instance et ne correspondait pas au code. Sur une installation neuve, `thread_messages` avait `created_at` au lieu de `sent_at` : l'envoi d'un message depuis le web échouait. Sur une instance ancienne (schéma SQL d'origine), il manquait notamment `thread_participants.created_at` : l'ajout d'un participant par l'API échouait. La migration de création du bundle produit désormais directement le schéma qu'utilise le code (`sent_at`, colonnes complètes), et `2026_10_14_000002_align_messaging_schema` ajoute aux tables existantes les colonnes qui leur manquent, en reprenant la date des messages déjà envoyés ; rien n'est supprimé. L'API écrit désormais `sent_at` comme le web. Testé sur les trois états de base (neuve, ancien schéma SQL, ancienne migration du Core).
- Sécurité — `POST /api/v1/messages/threads/{id}/messages` et `.../participants` fusionnaient le JSON brut du client dans `save()` (upsert dès qu'un `id` est présent) : avec l'`id` d'un message d'un autre fil, l'appelant le déplaçait dans son propre fil, le réécrivait, et la réponse en exposait le contenu — sans autre permission que l'authentification. Seuls `body` (message) et `user_id` (participant) sont lus, le reste est imposé par le serveur. `POST /messages/threads` exige désormais un `store_id` dont l'appelant est membre (jusqu'ici une valeur absente donnait une erreur 500).
- `MessageController::createThread()`/`addParticipant()` (API) acceptaient n'importe quel `user_id` valide sans vérifier qu'il partage un store avec l'appelant, ce qui permettait à un employé d'ajouter — et donc de découvrir l'appartenance — un utilisateur d'un autre store à une conversation. Les deux méthodes vérifient désormais un store partagé avant l'ajout.

## [1.1.0] - 2026-09-29

### Changed

- Aucun changement fonctionnel — bump de version pour aligner ce bundle sur la ligne 1.1.0 commune à tous les bundles officiels.
- Les notifications "nouveau message" ne disaient pas qui l'avait envoyé et ne menaient nulle part au clic. Le corps précise désormais l'expéditeur (`notif_message_received_body`/`notif_thread_message_received_body`, côté Kintai Core, gagnent le placeholder `:sender`), et le clic renvoie vers `/employee/messages/{id}` — cette route n'étant gatée par aucune permission RBAC (seule la participation au fil compte, vérifiée par le contrôleur), elle fonctionne pour tout destinataire quel que soit son rôle, évitant d'avoir à déterminer côté serveur si chacun est admin/manager/employé. **Nécessite** la version de Kintai Core introduisant le paramètre `$link` sur `notify()`/`notifyMany()`.
- Le CSS (`.msg-*`/`.link-subject*`) et le JS temps réel (`message-stream.js`, SSE + envoi AJAX) vivaient dans Kintai Core, pas dans ce dépôt. Ils vivent maintenant dans `public/css/messaging.css`/`public/js/message-stream.js`, fournis par le bundle lui-même via `Bundle::loadAssetsFrom()`/`bundle_asset()`. **Nécessite** `kintai_core.min: "0.2.0"`.

## [1.0.0] - 2026-09-19

### Added

- Extraction initiale depuis Kintai (`src/Bundles/Messaging`).
