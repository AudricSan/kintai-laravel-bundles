# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main) est décrit dans
`.github/workflows/release.yml`.

## [Unreleased]

## [2.0.0] - 2026-10-23

Première version stable de la ligne 2 : elle regroupe, à l'identique, tout ce qui a été publié en alpha et en bêta depuis la ligne 1 (voir les sections ci-dessous), sans changement fonctionnel propre à cette version.

### Changed
- **Traductions conformes au contrat** : 34 clés renommées avec le préfixe du bundle (`shift_claim_…`), sans changement de texte affiché ; `tests/TranslationContractTest.php` impose désormais parité fr/en/ja, préfixe, collisions et clés connues.

### Added

- Contrat de bundle 1.24 : `ContractUsageTest` vérifie aussi que le bundle n'appelle que des fonctions globales déclarées par le contrat (`ContractUsageScanner::functionViolations()`).

### Changed

- Notifications : ouvrir la bourse aux shifts ne marque plus comme lues les notifications de planning du Core (shift assigné ou modifié) portant le même identifiant de shift. Les types renvoyés vers la bourse (`open_shift_published`, `shift_claim_submitted`, `shift_claim_rejected`, `shift_claim_withdrawn`) désignent maintenant l'objet `open_shift`, propre au bundle ; `shift_claim_approved` et `shift_claim_handed_over`, renvoyés vers la vue jour du shift, gardent `shift` et sont marquées lues par le Core à l'ouverture de cette vue. Les notifications déjà envoyées gardent l'ancien objet et restent simplement non marquées automatiquement.

- Notifications (contrat 1.23, `kintai_contract` `^1.23`) : les six types sont déclarés au format enrichi (catégories « planning » et « requests », e-mail par défaut pour les décisions et le shift cédé, `reference_type` `shift`). Les liens sont précis : la bourse s'ouvre avec le shift en évidence (`?highlight=`), le candidat retenu et l'ancien titulaire arrivent sur la vue jour du shift, le gestionnaire sur la candidature. Ouvrir la liste des shifts publiés marque comme lues les notifications des shifts affichés.

- Tests : plus aucun avis PHPUnit (28 auparavant) — les doublures sans attente sont des bouchons (`createStub()`), les mocks ne sont créés que dans les tests qui posent une attente, et les vérifications `->with()` sont conservées (`expects($this->atLeastOnce())`, ou `willReturnCallback()` par argument dans les méthodes utilitaires).
- Extraction du Core (étape 4 du plan du contrat) : le bundle possède désormais tout son code et ses données. Le modèle `ShiftClaim` et le dépôt `ShiftClaimRepositoryInterface` / `DatabaseShiftClaimRepository` (`src/Models`, `src/Repositories`) quittent le Core ; la table `shift_claims` garde son nom et ses données, et une migration du bundle la crée si elle manque (installation neuve). Les champs « Shift ouvert » du formulaire de shift et les boutons « Publier / Fermer la bourse » de la page d'édition sont désormais fournis par le bundle (`shiftFormSections()`, contrat 1.17, `"kintai_contract": "^1.17"`) ; les boutons visent leurs routes avec `formaction` (ils étaient des formulaires imbriqués qui enregistraient le shift au lieu d'agir). Un shift que le Core retire de la bourse (formulaire ou API) voit ses candidatures en attente passer à « retirée » (`eventListeners()`, `OpenShiftClosed`). Les types de notification sont déclarés (`notificationTypes()`) et la page « Demandes » date une candidature par `claimed_at` (présent sur toutes les instances, au lieu de `created_at`, absent des plus anciennes). Les libellés de la catégorie de permissions, de la fonctionnalité, des notifications, du journal d'activité et du formulaire passent dans `lang/`. `ContractUsageTest` n'admet plus aucune classe du Core hors contrat ; `ContractDeclarationsTest` vérifie chaque déclaration valeur par valeur. Tests repris du Core (dépôt, affectation de masse de l'API) ; nouveaux tests `ShiftClaimsMigrationTest` et `WithdrawPendingClaimsOnCloseTest`.
- Contrat de bundle versionné : le bundle n'utilise plus que les noms du contrat `kintai\Contract\…` du Core (requête, réponse, routes, middlewares, exceptions, droits, données du Core, services, vues et composants) et déclare `"kintai_contract": "^1.0"` dans `bundle.json` (`kintai_core.min` relevé à `0.3.6`, première version du Core qui fournit le contrat). Nouveau test `ContractUsageTest` : toute utilisation du Core hors contrat échoue, hormis 2 classe(s) propre(s) au bundle qui vivent encore dans le Core (extraction prévue).

### Added

- Contrat de bundle 1.11 : le bundle déclare lui-même ce qu'il apporte au Core (`navigation()`, `permissions()`, `storeFeatures()`, `pendingRequestsProviders()`) au lieu que le Core le connaisse à l'avance ; déclarations identiques à ce que le Core affichait pour ce bundle, rien ne change à l'écran. `bundle.json` demande désormais `"kintai_contract": "^1.11"` (Kintai Core avec les points d'extension et fournisseurs du contrat). Nouveau test `ContractDeclarationsTest` : chaque déclaration est comparée au repli historique du Core.
- Socle de tests PHPUnit (composer.json, phpunit.xml) et CI qui l'exécute contre le vrai Core : les tests que le Core faisait tourner sur une copie figée du bundle (fixtures 1.0.0) vivent désormais ici et testent le code réel.

### Fixed

- API alignée sur le web (audit n°8 de Kintai) : `POST /api/v1/shift-claims` exige un shift publié du magasin indiqué, qui n'est pas celui du candidat et sans candidature déjà en cours (avant : n'importe quel `shift_id` était accepté) ; `PUT /api/v1/shift-claims/{id}` avec `status=approved` attribue désormais le shift, ferme la bourse, refuse et prévient les autres candidats et l'ancien titulaire, comme l'approbation web (avant : seul le statut changeait, le shift restait ouvert). Un shift déjà fermé répond 409. La logique d'approbation est partagée (`ShiftClaimApproval`). Nouveau test `ApiClaimConsistencyTest`.
- API en libre-service (audit n°8 de Kintai) : un employé sans permission lit enfin ses propres candidatures aux shifts ouverts avec `GET /api/v1/shift-claims?user_id=<son id>`. La liste lui était toujours renvoyée vide : le filtrage par permission retirait aussi ses propres éléments. Il ne voit jamais ceux des autres ; un gérant garde exactement sa vue. Nouveau test `ApiSelfServiceListTest`.
- Un shift publié depuis « Publier un shift » ne pouvait jamais être pris : la page ne propose que des shifts attribués, mais la candidature refusait tout shift qui avait un titulaire (« introuvable »). Publier un shift attribué, c'est désormais le céder : ses collègues du magasin peuvent postuler, son titulaire le garde jusqu'à l'approbation d'un candidat et reçoit alors une notification « Shift repris » (nouveau type `shift_claim_handed_over`). Le titulaire voit son shift dans la liste avec la mention « Votre shift (proposé) » au lieu du bouton « Postuler », et une candidature refusée affiche enfin son message.
- Sécurité — `POST /api/v1/shift-claims` fusionnait le JSON brut du client dans `save()` (upsert dès qu'un `id` est présent). La route étant en libre-service (`self => user_id`), un employé pouvait poster `status: approved`, `resolved_by`, un `store_id` qui n'est pas le sien ou un `id` pour écraser la candidature d'un collègue. Seuls `shift_id` et `note` sont lus ; le candidat est l'appelant (ou un tiers si `open_shifts.approve` sur le magasin), qui doit être membre du magasin, et le statut initial est `pending`. `PUT` ne modifie plus que le statut et la note (une résolution enregistre son auteur et sa date).

### Changed

- Compatibilité avec la Content-Security-Policy stricte de Kintai (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : les 2 attributs d'événements inline des vues (`onclick=`/`onchange=`/`onsubmit=`/`oninput=`) sont remplacés par des attributs `data-*` (`data-on-click`, `data-submit-on-change`, `data-confirm`… gérés par `csp-actions.js` du Core). Sans ce changement, les boutons, sélecteurs et confirmations de ces vues ne font plus rien sous la nouvelle politique, sans aucune erreur visible. **Nécessite Kintai Core 0.3.0 ou plus** (`kintai_core.min`), version qui introduit `csp-actions.js` et la CSP à nonce. `tests.yml` échoue désormais si un handler inline, un lien `javascript:` ou un `<script>` sans nonce réapparaît dans `Views/` ou `src/`.

## [1.1.0] - 2026-09-29

### Fixed

- `publishShift()` passait une phrase française codée en dur comme clé de traduction du corps de la notification `open_shift_published`, au lieu d'une vraie clé — jamais traduite en/ja. Utilise désormais `notif_open_shift_published_body`, ajoutée côté Kintai Core.

### Changed

- Aucun changement fonctionnel — bump de version pour aligner ce bundle sur la ligne 1.1.0 commune à tous les bundles officiels.
- Les six notifications de la bourse aux shifts (publication, retrait, candidature soumise/approuvée/rejetée/retirée) ne disaient rien du shift concerné et ne menaient nulle part au clic. Le corps précise désormais la date, l'horaire et le magasin (candidature soumise ajoute aussi le nom du candidat) — `notif_open_shift_published_body`/`notif_shift_claim_*_body` (Kintai Core) gagnent les placeholders `:date`/`:start`/`:end`/`:store`/`:author`. Le clic renvoie vers `/employee/open-shifts` (candidats) ou `/admin/open-shifts` (managers, nouvelle candidature à traiter). **Nécessite** la version de Kintai Core introduisant le paramètre `$link` sur `notify()`/`notifyMany()`.

## [1.0.0] - 2026-09-19

### Added

- Extraction initiale depuis Kintai (`src/Bundles/ShiftClaim`).
