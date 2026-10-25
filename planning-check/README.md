# Kintai — Validation du planning de demain (`planning-check`)

Bundle pour [Kintai](https://github.com/AudricSan/Kintai), demandé par les patrons : **avant midi, une fenêtre demande au
manager si les shifts du lendemain sont corrects**, avec un aperçu des shifts. Le flux reprend celui du rapport journalier.

1. **Soumettre** — le manager répond « Oui, le planning est correct » (le premier manager qui soumet suffit). Sans aucun shift,
   il doit confirmer explicitement « aucun shift demain ». « Non, modifier le planning » ouvre le planning de demain et met la
   fenêtre en retrait (une barre « Revenir à la validation » reste en bas de page).
2. **Valider** — un détenteur de `planning_check.approve` approuve : **le PDF est alors généré et envoyé au patron** (e-mail aux
   destinataires du magasin). Il peut aussi **refuser avec un motif** : retour au soumetteur, qui corrige et soumet de nouveau.
3. **Échéance** — sans soumission à l'heure limite, les valideurs et les Owners sont prévenus (`planning_check_overdue`), sans
   aucun envoi automatique. La fenêtre reste affichée, marquée « en retard ». Sans cron, la première page chargée après l'heure
   limite déclenche l'escalade ; avec la tâche `planning-check-escalate`, elle est faite à l'heure.
4. **Renvoi** — si le planning change après l'envoi, l'historique propose « Renvoyer une version mise à jour » (PDF marqué
   « version mise à jour », historique des envois conservé) ; un envoi échoué se réessaie.

## Réglages par magasin
Activation, début d'affichage de la fenêtre (6 h par défaut), heure limite (12 h), jours concernés (le lendemain doit en faire
partie), destinataires du PDF (10 au plus), inclusion des shifts ouverts non attribués, PDF d'essai envoyé à soi.

## Permissions
`planning_check.view` (historique), `.submit` (soumettre), `.approve` (valider, refuser, renvoyer), `.settings` (réglages).
Toutes à portée magasin ; les accorder aux rôles depuis la page Rôles.

## Notifications
`planning_check_submitted` (aux valideurs), `_validated` et `_rejected` (au soumetteur), `_overdue` (valideurs et Owners),
`_failed` (envoi du PDF échoué). Push par défaut, e-mail désactivé ; lien profond vers l'historique et « lu » automatique.

## Développement
```bash
composer install && vendor/bin/phpunit
```
Kintai doit être cloné à côté (`../../Kintai`, voir `composer.json`). Nécessite le contrat de bundle `^1.27`
(`Bundle::pageOverlays()`). Les fenêtres sont des `PageOverlay` : la validation à faire (priorité 10), le planning à soumettre
(20) et la barre de correction (30, non bloquante).