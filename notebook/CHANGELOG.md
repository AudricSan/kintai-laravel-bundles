# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main) est décrit dans
`.github/workflows/release.yml`.

## [Unreleased]

## [2.0.0] - 2026-10-23

Première version stable de la ligne 2 : elle regroupe, à l'identique, tout ce qui a été publié en alpha et en bêta depuis la ligne 1 (voir les sections ci-dessous), sans changement fonctionnel propre à cette version.

### Changed
- **Traductions conformes au contrat** : 8 clés renommées avec le préfixe du bundle (`notebook_…`), sans changement de texte affiché ; `tests/TranslationContractTest.php` impose désormais parité fr/en/ja, préfixe, collisions et clés connues.

- Notifications (contrat 1.23, `kintai_contract` `^1.23`) : type `notebook_entry_created` déclaré au format enrichi (catégorie « messages », push par défaut, `reference_type` `notebook_entry`) ; le lien ouvre le carnet avec la note publiée en évidence (`?highlight=`) ; ouvrir le carnet marque comme lues les notifications des notes affichées.

### Added

- Contrat de bundle 1.24 : `ContractUsageTest` vérifie aussi que le bundle n'appelle que des fonctions globales déclarées par le contrat (`ContractUsageScanner::functionViolations()`).
- Contrat de bundle 1.11 : le bundle déclare lui-même ce qu'il apporte au Core (`permissions()`, `storeFeatures()`) au lieu que le Core le connaisse à l'avance ; déclarations identiques à ce que le Core affichait pour ce bundle, rien ne change à l'écran. `bundle.json` demande désormais `"kintai_contract": "^1.11"` (Kintai Core avec les points d'extension et fournisseurs du contrat). Nouveau test `ContractDeclarationsTest` : chaque déclaration est comparée au repli historique du Core.

### Changed

- Tests : plus aucun avis PHPUnit (29 auparavant) — les doublures sans attente sont des bouchons (`createStub()`), les mocks ne sont créés que dans les tests qui posent une attente, et les vérifications `->with()` sont conservées (`expects($this->atLeastOnce())`, ou `willReturnCallback()` par argument dans les méthodes utilitaires).
- Extraction du Core (étape 4 du plan du contrat) : le bundle possède désormais tout son code et ses données. Le modèle `NotebookEntry`, le dépôt `NotebookEntryRepositoryInterface` / `DatabaseNotebookEntryRepository` (`src/Models`, `src/Repositories`) quittent le Core ; la table `notebook_entries` garde son nom et ses données. Le widget « Notes d'équipe » des tableaux de bord gérant et employé est fourni par le bundle (`dashboardWidgets()`, même clé `team_notes` pour conserver les préférences, vues `Views/widgets/`, données par `Services/TeamNotesWidget`), ainsi que le type de notification `notebook_entry_created` (`notificationTypes()`) et les libellés (widget, fonctionnalité « notes », catégorie de permissions) dans `lang/`. Même rendu qu'avant. `ContractUsageTest` n'admet plus aucune classe du Core hors contrat, et `ContractDeclarationsTest` vérifie chaque déclaration valeur par valeur.
- Contrat de bundle versionné : le bundle n'utilise plus que les noms du contrat `kintai\Contract\…` du Core (requête, réponse, routes, middlewares, exceptions, droits, données du Core, services, vues et composants) et déclare `"kintai_contract": "^1.0"` dans `bundle.json` (`kintai_core.min` relevé à `0.3.6`, première version du Core qui fournit le contrat). Nouveau test `ContractUsageTest` : toute utilisation du Core hors contrat échoue, hormis 2 classe(s) propre(s) au bundle qui vivent encore dans le Core (extraction prévue).

### Fixed

- Tableau de bord — un gérant qui ajoutait le widget de son espace employé à côté du sien voyait deux cartes de notes au titre trompeur (« Carnet de notes » et « Notes d'équipe ») sans savoir laquelle portait sur quoi. Le widget gérant s'appelle désormais « Notes d'équipe » (magasins gérés + organisation) et le widget employé « Notes de mes magasins » (magasins dont on est membre + organisation), en fr, en et ja ; un test vérifie que les deux titres restent distincts. Les deux vues partagent la même carte (`Views/widgets/_card.php`, composant `Card` du contrat) au lieu de deux rendus différents.
- i18n — la clé `notebook_expires_on` (étiquette du champ d'expiration et badge « expire le … ») n'existait dans aucune langue : le bloc-notes affichait la clé brute. Ajoutée en fr, en et ja.
- Sécurité — une note « toute l'organisation » pouvait être modifiée, supprimée et épinglée par le titulaire de `notebook.manage` dans un seul magasin (`can(..., null)` ne veut dire que « quelque part »). Elle exige désormais la permission sans restriction de magasin ; l'auteur garde la main sur sa note. L'épinglage vérifie aussi le magasin de la note. Nécessite Kintai Core 0.3.4 (`PermissionService::hasUnrestrictedGrant`).
- Sécurité — `POST /api/v1/notebook-entries` ne contrôlait pas `store_id` : n'importe quel porteur de `notebook.create` pouvait créer une note visible de toute l'organisation (réservée à la portée globale sur le web) ou dans un magasin qu'il ne gère pas, et un `id` dans le corps écrasait la note de quelqu'un d'autre (upsert). Seuls `content`, `store_id` et `expires_at` sont lus ; une note d'organisation exige la portée globale, une note de magasin le droit sur ce magasin. `PUT` ne modifie plus que `content` et `expires_at` : `store_id`, `pinned` (réservé à `notebook.manage`), `created_at` et `author_id` ne changent pas.

### Changed

- Garde-fou contre les handlers inline, en prévision de la Content-Security-Policy stricte de Kintai Core 0.3.0 (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : `tests.yml` échoue désormais si un attribut `onclick=`/`onchange=`/`onsubmit=`/`oninput=`, un lien `javascript:` ou un `<script>` sans nonce apparaît dans `Views/` ou `src/` — le navigateur les bloquerait en silence, sans aucune erreur côté serveur. **Aucun changement fonctionnel** : les vues de ce bundle n'utilisent déjà aucun handler inline ni `<script>` inline exécutable. La règle est documentée dans `CONTRIBUTING.md` et `CLAUDE.md`.

### Changed

- Le CSS du widget (`.notebook-widget-list`/`.notebook-widget-item*`) vivait physiquement dans Kintai Core (`public/assets/css/src/components/notebook.css`), pas dans ce dépôt — le bundle en dépendait silencieusement sans jamais le fournir lui-même, cassant l'isolation que la distribution en dépôt séparé est censée garantir. Il vit maintenant dans `public/css/notebook.css`, servi via le nouveau mécanisme `Bundle::loadAssetsFrom()`/`bundle_asset()` de Kintai Core, chargé par `Views/notebook.php` **et** par le widget dashboard natif du Core (qui s'aligne désormais sur ce fichier au lieu d'en garder sa propre copie). **Nécessite** `kintai_core.min: "0.2.0"` (voir `docs/creating-a-bundle.md`'s "Assets" côté Core).

## [1.1.0] - 2026-09-29

### Fixed

- `routes.php` : la route d'index (`GET /notebook`) et la route de création (`POST /notebook`) étaient déclarées avec le motif `/` à l'intérieur du groupe `/notebook`, ce qui compilait en `/notebook/` — un chemin que `Router::dispatch()` ne peut jamais atteindre puisqu'il normalise systématiquement l'URI entrante en retirant le `/` final. Résultat : `GET /notebook` renvoyait toujours `No route matches`, même bundle activé. Aucun autre bundle Kintai n'utilise ce motif pour sa route d'index (voir par ex. `timeoff`/`store-photos`, qui nomment le segment complet) — corrigé en utilisant le motif vide `''` pour hériter du préfixe du groupe tel quel.

### Changed

- La notification envoyée à la publication d'une note ne disait rien de son contenu et ne menait nulle part au clic. Utilise le nouveau `$link` de `NotificationService::notify()` (Kintai Core, PR séparée) pour renvoyer vers `/notebook`, et enrichit le corps du message avec l'auteur et un extrait de la note (`notif_notebook_entry_created_body` gagne les placeholders `:author`/`:excerpt`, fr/en/ja). **Nécessite** la version de Kintai Core qui introduit le paramètre `$link` sur `notify()`/`notifyMany()` — un appel à 6 arguments contre une version de Core encore sur l'ancienne signature à 5 lèverait une erreur PHP.

## [1.0.0] - 2026-09-26

### Added

- Version initiale : carnet de notes d'équipe (messages visibles sur les dashboards admin et employé), portée par note (store précis ou toute l'organisation), épinglage, expiration automatique, notification des membres concernés. Première utilisation du mécanisme `database/migrations/` (voir CLAUDE.md) pour créer sa propre table `notebook_entries` plutôt que de dépendre d'une table déjà fournie par Kintai Core.
- Contrairement aux autres bundles Kintai, ce dépôt embarque une vraie suite PHPUnit (`tests/`, 24 tests) : `composer.json` mappe `kintai\` directement vers les vraies classes du dépôt Kintai principal (chemin relatif `../../Kintai/src/`, même disposition en sibling que `KintaiBundleDev/` en local) plutôt que de dupliquer des fakes. Couvre les contrôleurs Web/API (autorisations auteur-vs-`notebook.manage`, portée organisation vs store, notifications, tri/filtre épinglées-expirées) et la migration `notebook_entries` elle-même, exécutée via le vrai `BundleMigrationRunner` du Core. La CI (`tests.yml`) checkout les deux dépôts côte à côte pour reproduire cette disposition et lance la suite à chaque push/PR.
