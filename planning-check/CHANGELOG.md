# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).

## [Unreleased]

### Fixed

- **Fuseau horaire : celui de l'application, plus celui du magasin.** Le jour visé (« demain »), l'ouverture de la fenêtre et l'heure limite étaient évalués dans le fuseau du magasin, qui vaut « UTC » par défaut : le contrôle s'ouvrait et passait « en retard » avec plusieurs heures de décalage, et pouvait viser le mauvais jour. Ils suivent désormais le fuseau de l'instance (`APP_TIMEZONE`), comme la synchronisation de calendrier du Core.

## [2.2.0] - 2026-10-23

### Changed

- **Destinataires du PDF : on coche des employés du magasin** au lieu de saisir des adresses dans un champ texte. La liste vient du composant partagé du Core (`UserPicker`, contrat 1.29) ; on enregistre le compte de l'employé (`planning_check_settings.mail_recipient_ids`, migration `2026_10_24_000001`), donc un changement d'adresse est suivi et un compte désactivé ou retiré du magasin ne reçoit plus rien. 10 destinataires au plus, au moins un pour activer le contrôle.
- **Un e-mail par personne**, avec le PDF joint : les destinataires ne voient plus les adresses des autres. L'historique des envois garde les adresses réellement utilisées.
- Les adresses saisies auparavant (`mail_recipients`) restent utilisées et sont listées à part dans les réglages ; on peut les retirer mais plus en ajouter.
- Le bundle exige le contrat `^1.29`.

## [2.1.0] - 2026-10-23

### Added

- **Revérifier le planning de demain** (carte « Planning de demain » de l'historique du magasin) : un bouton rouvre la vérification de demain, même déjà soumise ou validée, et **force l'ouverture de la fenêtre habituelle** — les shifts de demain, à vérifier de nouveau (oui / non, modifier / plus tard) — même hors fenêtre horaire ou un jour non ouvré. La soumettre recrée un rapport, que le valideur valide comme d'habitude ; le PDF part alors comme version mise à jour. Le mode forcé est propre à l'utilisateur et à sa session, et se lève à la soumission. `POST .../planning-check/recheck` (droit `planning_check.submit`), audit `planning_check.recheck_forced`.

### Changed

- Le bouton « Refaire le signalement » (`.../reopen`) est remplacé par « Revérifier le planning de demain », qui rouvre déjà la vérification et affiche la fenêtre. « Envoyer maintenant » reste en action secondaire pour les valideurs.

## [2.0.0] - 2026-10-23

Première version stable de la ligne 2 : elle regroupe, à l'identique, tout ce qui a été publié en alpha et en bêta depuis la ligne 1 (voir les sections ci-dessous), sans changement fonctionnel propre à cette version.

### Added

- **Envoyer ou refaire le planning de demain à tout moment** (historique du magasin) : une carte « Planning de demain » est proposée aux détenteurs de `planning_check.approve`, même quand la fenêtre de soumission n'est pas ouverte, que le jour n'est pas ouvré ou que rien n'a été soumis. **Envoyer maintenant** soumet puis valide si besoin, génère un PDF neuf à partir des shifts actuels et l'envoie (version mise à jour si un envoi a déjà eu lieu) ; sans shift demain, une confirmation renforcée est demandée. **Refaire le signalement** remet la vérification soumise ou validée en attente, l'historique des envois est conservé. `POST .../planning-check/send-now` et `.../reopen`, audit `planning_check.sent_now` et `planning_check.reopened`.

### Changed

- Une vérification rouverte puis revalidée après un premier envoi part comme version mise à jour.

## [1.1.0] - 2026-10-23

### Added

- **Renvoi à la demande d'un PDF régénéré** (historique du magasin) : un bouton « Générer un nouveau PDF et l'envoyer » est proposé pour tout planning validé, avec confirmation, sans attendre que le bundle détecte un changement. Il génère le PDF à partir des shifts actuels et l'envoie comme version mise à jour, ce qui permet de renvoyer une modification d'horaire faite après la validation. Le bouton principal « Renvoyer une version mise à jour » reste affiché quand un changement est détecté. Même droit d'approbation qu'avant ; `tests/Views/HistoryResendButtonTest.php`.

### Changed

- Page de réglages refaite sur le modèle des réglages Owner : grille à deux colonnes, interrupteurs pour l'activation et les shifts ouverts, horaires côte à côte, jours de la semaine en pastilles, PDF d'essai dans sa propre carte.

## [1.0.0] - 2026-10-22

### Added

- Première version : fenêtre « Les shifts de demain sont-ils corrects ? » avant l'heure limite (aperçu, soumettre / modifier / plus tard), validation par un détenteur de `planning_check.approve` avec envoi du PDF au patron, refus avec motif, renvoi d'une version mise à jour, escalade à l'échéance (tâche `planning-check-escalate` ou repli à la première page), historique, réglages par magasin, notifications enrichies. Contrat de bundle `^1.27`.
