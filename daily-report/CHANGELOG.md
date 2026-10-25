# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main, tags `daily-report-vX.Y.Z`) est décrit dans
`.github/workflows/release.yml`, à la racine du dépôt kintai-laravel-bundles.

## [Unreleased]

### Changed

- **Portage vers Kintai sous Laravel** (ligne 3.0, dépôt [kintai-laravel-bundles](https://github.com/AudricSan/kintai-laravel-bundles)) : les vues sont des gabarits Blade (`Views/*.blade.php`), au rendu identique ; le bundle requiert le contrat de bundle `^1.31` (vues Blade dans les bundles, fourni par Kintai sous Laravel) ; ses tests tournent contre `kintai-laravel`.

### Fixed

- **PDF : feuilles de style et barre d'aperçu à nouveau incluses.** Leurs chemins étaient calculés depuis l'ancien emplacement du bundle dans le Core (`src/Bundles/…`) : une fois le bundle installé dans `storage/bundles/`, l'aperçu et le PDF sortaient sans mise en forme ni bouton de téléchargement. Ils passent désormais par la racine de Kintai et le partiel du Core.

## [2.1.1] - 2026-10-23

### Fixed
- **La page des réglages d'un magasin répondait 500** (2.1.0) : la vue lisait `$recipientIds` et `$legacyRecipients` sans les définir, donc `UserPicker::selected()` recevait `null`. Les deux variables sont maintenant calculées en tête de vue depuis `mail_recipient_ids` et `mail_recipients`.
- Le lien vers la page des rôles (réservé à l'Owner) lisait `$auth_user` au lieu de `$authUser` : l'Owner ne le voyait jamais.
- Test `testEveryVariableReadByTheViewIsDefined` : échoue si la vue des réglages lit une variable ni fournie par le contrôleur ni définie en tête de vue.

## [2.1.0] - 2026-10-23

### Changed
- **Destinataires du rapport : on coche des employés du magasin** au lieu de saisir des adresses dans un champ texte. La liste vient du composant partagé du Core (`UserPicker`, contrat 1.29) ; on enregistre le compte de l'employé (`mail_recipient_ids`), donc un changement d'adresse est suivi et un compte désactivé ou retiré du magasin ne reçoit plus rien.
- **Un e-mail par personne**, dans la langue de son compte : les destinataires ne voient plus les adresses des autres.
- Les adresses saisies auparavant (`mail_recipients`) restent utilisées et sont listées à part dans les réglages ; on peut les retirer mais plus en ajouter.
- Le bundle exige le contrat `^1.29`.
- Tests : `DailyReportRecipientsTest` (envoi individuel, langue, comptes inactifs, adresses héritées, doublons).

## [2.0.0] - 2026-10-23

Première version stable de la ligne 2 : elle regroupe, à l'identique, tout ce qui a été publié en alpha et en bêta depuis la ligne 1 (voir les sections ci-dessous), sans changement fonctionnel propre à cette version.

### Changed
- **Traductions conformes au contrat** : 91 clés renommées avec le préfixe du bundle (`daily_report_…`), sans changement de texte affiché ; `tests/TranslationContractTest.php` impose désormais parité fr/en/ja, préfixe, collisions et clés connues.

### Fixed

- **Réglages : l'heure de validation automatique et les heures de rappel ne pouvaient pas être réglées.** Le formulaire n'avait aucun champ pour elles alors que `saveSettings()` les lit dans la requête : chaque enregistrement des réglages effaçait la validation automatique (la tâche `auto-validate` ne validait donc jamais rien) et remettait le rappel à 15:00 et l'heure « sans rapport » à 18:00. Une carte « Horaires » propose maintenant les trois heures (validation automatique, rappel, heure « sans rapport »), en fr/en/ja. `DailyReportSettingsFormTest` vérifie que chaque réglage enregistré a son champ.

### Added

- Contrat de bundle 1.24 : `ContractUsageTest` vérifie aussi que le bundle n'appelle que des fonctions globales déclarées par le contrat (`ContractUsageScanner::functionViolations()`).

### Changed

- Notifications (contrat 1.23, `kintai_contract` `^1.23`) : types `daily_report_submitted` et `daily_report_validated` déclarés au format enrichi (catégorie « reports », push par défaut, `reference_type` `daily_report`) ; le lien ouvrait déjà le rapport précis, l'ouvrir marque maintenant ses notifications comme lues.

- Tests : plus aucun avis PHPUnit (55 auparavant) — les doublures sans attente sont des bouchons (`createStub()`), les mocks ne sont créés que dans les tests qui posent une attente, et les vérifications `->with()` sont conservées (`expects($this->atLeastOnce())`, ou `willReturnCallback()` par argument dans les méthodes utilitaires).
- Aperçu PDF du rapport journalier : le bloc `<style>` porte le nonce CSP de la requête, en vue du retrait de `'unsafe-inline'` des styles dans la CSP de Kintai. Contrôle CI ajouté contre les styles en ligne dans les vues.
- Test `DailyReportMassAssignmentTest` repris du Core (affectation de masse à la création par l'API : identifiant ignoré, auteur et brouillon imposés, validation refusée), retiré du Core avec l'extraction. Aucun changement fonctionnel.
- Extraction du Core (étape 4 du plan du contrat) : le bundle possède désormais tout son code et ses données. Le modèle `DailyReport`, le dépôt `DailyReportRepositoryInterface` / `DatabaseDailyReportRepository` et les services (`DailyReportPermissionService`, `DailyReportPdfService`, `DailyReportMailService`, `DailyReportAutoValidateService`, `DailyReportDataNormalizer`) quittent le Core et sont liés par le bundle ; la table `daily_reports` garde son nom et ses données, et une migration du bundle la crée si elle manque (installation neuve), au schéma actuel. Nouvelles déclarations : tâche planifiée `auto-validate` (`cronJobs()`, lancée par `/cron/run/auto-validate` ou `php scripts/cron.php auto-validate`), types de notification `daily_report_submitted` / `daily_report_validated` (`notificationTypes()`) et ventes journalières (`salesProviders()`, `Providers/DailyReportSales`), lues par la rentabilité du Core et par salary-report. `"kintai_contract": "^1.16"` (envoi d'e-mails et police japonaise des PDF par le contrat). Les libellés de la catégorie de permissions, de la fonctionnalité, des notifications, du journal d'activité, du courriel et des erreurs passent dans `lang/`. `ContractUsageTest` n'admet plus aucune classe du Core hors contrat ; `ContractDeclarationsTest` vérifie chaque déclaration valeur par valeur. Tests repris du Core (dépôt, services, tâche auto-validate) ; nouveaux tests `DailyReportSalesTest` et `DailyReportsMigrationTest`.
- Contrat de bundle versionné : le bundle n'utilise plus que les noms du contrat `kintai\Contract\…` du Core (requête, réponse, routes, middlewares, exceptions, droits, données du Core, services, vues et composants) et déclare `"kintai_contract": "^1.0"` dans `bundle.json` (`kintai_core.min` relevé à `0.3.6`, première version du Core qui fournit le contrat). Nouveau test `ContractUsageTest` : toute utilisation du Core hors contrat échoue, hormis 4 classe(s) propre(s) au bundle qui vivent encore dans le Core (extraction prévue).

### Added

- Libellé de la tâche planifiée `auto-validate` (`cron_job_auto_validate`, fr/en/ja), affiché par `php scripts/cron.php` depuis le contrat 1.20 (convention de traduction ; sans la clé, le nom de la tâche s'affiche, aucune contrainte de version à relever).
- Contrat de bundle 1.11 : le bundle déclare lui-même ce qu'il apporte au Core (`navigation()`, `permissions()`, `storeFeatures()`, `viewVariables()`) au lieu que le Core le connaisse à l'avance ; déclarations identiques à ce que le Core affichait pour ce bundle, rien ne change à l'écran. `bundle.json` demande désormais `"kintai_contract": "^1.11"` (Kintai Core avec les points d'extension et fournisseurs du contrat). Nouveau test `ContractDeclarationsTest` : chaque déclaration est comparée au repli historique du Core.
- Socle de tests PHPUnit (composer.json, phpunit.xml) et CI qui l'exécute contre le vrai Core : les tests que le Core faisait tourner sur une copie figée du bundle (fixtures 1.0.0) vivent désormais ici et testent le code réel.

### Fixed

- Réglages des rapports journaliers : le lien vers la page des rôles n'est plus proposé qu'à l'Owner (elle lui est réservée, le lien menait les gérants à une page 403).
- Sécurité — des formulaires de filtre envoyés en GET contenaient le jeton CSRF de la session, qui se retrouvait dans l'URL (historique du navigateur, journaux d'accès du serveur, liens copiés). Les formulaires GET ne le portent plus ; nouveau test `NoCsrfTokenInGetFormsTest` qui échoue si l'un d'eux le réintroduit.
- Sécurité — `GET /api/v1/daily-reports` renvoyait, sans `store_id` ou avec `author_id`, les rapports de **tous** les magasins (ventes, clients, coût salarial, notes) à tout compte ayant `daily_reports.view` dans un seul magasin, et un simple membre y voyait les rapports de ses collègues. La liste ne garde plus que les rapports que l'appelant peut ouvrir un par un (même règle que `GET /api/v1/daily-reports/{id}`). Nouveau test `DailyReportIndexScopeTest`.
- Sécurité — `POST`/`PUT /api/v1/daily-reports` fusionnaient le JSON brut du client dans `save()` (upsert dès qu'un `id` est présent) : un `id` écrasait le rapport de quelqu'un d'autre en contournant `canEdit()`, `author_id` était pris du client (un rapport pouvait être attribué à un tiers) et `PUT` laissait modifier `store_id`, `status`, la validation ou le PDF. Seuls `report_date`, `sales_total`, `customer_count`, `labor_cost`, `waste_total`, `notes` et `data` sont lus ; l'auteur est l'appelant, le statut initial est `draft`, et les transitions passent par `submit`/`validate`.

### Changed

- Liste des rapports journaliers (`/admin/daily-reports`) : la vue par défaut est désormais **par jour** au lieu de par magasin. Le choix fait avec le bouton de bascule reste mémorisé dans le navigateur. La lecture de ce choix est protégée : en navigation privée ou avec un stockage bloqué, `localStorage` levait une erreur et les deux vues restaient affichées l'une sous l'autre.

### Changed

- Compatibilité avec la Content-Security-Policy stricte de Kintai (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : les 23 attributs d'événements inline des vues (`onclick=`/`onchange=`/`onsubmit=`/`oninput=`) sont remplacés par des attributs `data-*` (`data-on-click`, `data-submit-on-change`, `data-confirm`… gérés par `csp-actions.js` du Core) et les 2 `<script>` inline portent désormais le nonce de la requête (`csp_nonce()`, gardé par `function_exists` pour ne pas planter sur un Core plus ancien). Sans ce changement, les boutons, sélecteurs et confirmations de ces vues ne font plus rien sous la nouvelle politique, sans aucune erreur visible. **Nécessite Kintai Core 0.3.0 ou plus** (`kintai_core.min`), version qui introduit `csp-actions.js` et la CSP à nonce. `tests.yml` échoue désormais si un handler inline, un lien `javascript:` ou un `<script>` sans nonce réapparaît dans `Views/` ou `src/`.

### Changed

- Le CSS de la modale de détail (`.dr-v2-*`, `.dr-show-grid`/`.dr-kpi-table`/`.dr-meta-table`), le JS de formatage numérique (`numeric-input.js`) et le CSS du PDF (`pdf-daily-report.css`) vivaient dans Kintai Core — le premier éclaté entre le composant partagé `modals.css` et `responsive.css`, sans fichier dédié. Tout vit maintenant dans `public/css/daily-report.css`/`public/js/numeric-input.js`/`public/css/pdf-daily-report.css`, fournis par ce bundle via `Bundle::loadAssetsFrom()`/`bundle_asset()`/`bundle_asset_path()`. Corrige au passage un bug latent : `daily-report-pdf.php` résolvait le chemin de son propre CSS PDF via `dirname(__DIR__, 4)`, correct pour l'ancien emplacement monorepo mais pas pour un bundle installé dynamiquement (`storage/bundles/{slug}/{version}/`, un niveau de plus) — le PDF perdait donc son style spécifique en production. **Nécessite** `kintai_core.min: "0.2.0"`.

## [1.1.0] - 2026-09-29

### Fixed

- `submit()` passait une phrase française codée en dur (`'Un rapport journalier attend votre validation.'`) comme clé de traduction du corps de la notification `daily_report_submitted`, au lieu d'une vraie clé — `notif_daily_report_submitted_body` n'existait nulle part, donc cette notification s'affichait toujours en français, quelle que soit la langue du destinataire. Utilise désormais cette clé, ajoutée côté Kintai Core. Au passage, `submit()`/`validate()` enrichissent aussi le corps (date du rapport, magasin) et renvoient au clic vers la page du rapport concerné (`/admin/stores/{id}/daily-reports/{rid}`) — nécessite la version de Kintai Core introduisant le paramètre `$link` sur `notify()`/`notifyMany()`.
- `DailyReportController::indexAll()` — un utilisateur non-Owner dont le rôle accorde `daily_reports.view` en portée globale (case "Toutes les boutiques", `managed_store_ids === null`) voyait le sélecteur de magasin vide et se faisait refuser tout filtre `?store_id=` précis, alors que la liste de rapports elle-même restait visible par coïncidence (convention "tableau vide = pas de filtre" de `findAllActive()`). La méthode traitait `managed_store_ids === null` comme équivalent à `[]` (`?? []`) au lieu de le traiter comme Owner/is_admin — c'est-à-dire "aucune restriction". Traité maintenant de façon uniforme avec `is_admin`.

## [1.0.0] - 2026-09-19

### Added

- Extraction initiale depuis Kintai (`src/Bundles/DailyReport`).
