<?php
use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Button;

/**
 * Boutons « Publier / Fermer la bourse » de la page d'édition d'un shift (section déclarée au Core, contrat 1.17). Ils
 * sont dans le formulaire du shift : formaction vers les routes du bundle (jamais de <form> imbriqué).
 *
 * @var array  $shift
 * @var string $BASE_URL
 */
$_shiftUrl = $BASE_URL . '/admin/shifts/' . (int) $shift['id'];
?>
<?php if (!(int) ($shift['is_open'] ?? 0)): ?>
    <?= Button::make(__('shift_claim_publish_to_bourse'))->warning()->submit()->attrs(['formaction' => $_shiftUrl . '/publish', 'formmethod' => 'post', 'formnovalidate' => 'formnovalidate'])->render() ?>
<?php else: ?>
    <?= Button::make(__('shift_claim_close_bourse'))->ghost()->submit()->attrs(['formaction' => $_shiftUrl . '/unpublish', 'formmethod' => 'post', 'formnovalidate' => 'formnovalidate'])->render() ?>
    <?= Badge::make(__('shift_claim_open_bourse'))->success()->render() ?>
<?php endif; ?>
