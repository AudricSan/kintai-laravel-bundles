<?php
/**
 * Widget « Notes de mes magasins » du tableau de bord employé (NotebookBundle::dashboardWidgets(), contrat 1.4) :
 * notes des magasins dont l'utilisateur est membre et de toute l'organisation. Un gérant peut aussi l'ajouter à son
 * propre tableau de bord, d'où un titre distinct de celui du widget gérant.
 *
 * @var list<array<string, mixed>> $notebook_entries Voir TeamNotesWidget::entries()
 */

$notebookWidgetLabel = 'notebook_my_team_notes';
include __DIR__ . '/_card.php';
