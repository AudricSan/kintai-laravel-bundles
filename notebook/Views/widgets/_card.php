<?php
/**
 * Carte commune aux widgets « Notes d'équipe » des tableaux de bord gérant et employé : seul le titre change
 * (dashboard-manager.php / dashboard-employee.php), le rendu reste identique d'un tableau de bord à l'autre.
 *
 * @var list<array<string, mixed>> $notebook_entries     Voir TeamNotesWidget::entries()
 * @var string                     $notebookWidgetLabel  Clé de traduction du titre de la carte
 */

use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;
?>
<?php if ($notebookCss = bundle_asset('notebook', 'css/notebook.css')): ?>
<link rel="stylesheet" href="<?= $notebookCss ?>">
<?php endif; ?>
<?php
ob_start();
if (empty($notebook_entries)):
    echo '<div class="empty-state">' . __('notebook_no_entries') . '</div>';
else:
    echo '<ul class="notebook-widget-list">';
    foreach ($notebook_entries as $entry):
        $pinned = !empty($entry['pinned']);
        ?>
        <li class="notebook-widget-item<?= $pinned ? ' notebook-widget-item--pinned' : '' ?>">
            <?php if ($pinned): ?>
                <span class="notebook-widget-item__pin" title="<?= htmlspecialchars(__('notebook_pinned')) ?>">📌</span>
            <?php endif; ?>
            <p class="notebook-widget-item__content"><?= nl2br(htmlspecialchars($entry['content'] ?? '')) ?></p>
            <div class="notebook-widget-item__meta">
                <span><?= htmlspecialchars($entry['author_name'] ?? __('notebook_anonymous_author')) ?></span>
                <span>·</span>
                <span><?= htmlspecialchars($entry['store_name'] ?? __('notebook_org_wide')) ?></span>
            </div>
        </li>
        <?php
    endforeach;
    echo '</ul>';
endif;
echo Card::make()
    ->header('<span>' . __($notebookWidgetLabel) . '</span> ' . Button::make(__('view_all'))->ghost()->sm()->link(route_url('notebook.index'))->render())
    ->body(ob_get_clean())
    ->render();
