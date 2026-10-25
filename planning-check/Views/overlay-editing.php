<?php
/**
 * Barre de rappel pendant la correction du planning de demain (placement « bar ») : revenir à la validation.
 *
 * @var array<string, mixed> $store
 * @var string               $date
 * @var string               $overlay_title_id
 */
$_action = htmlspecialchars(route_url('admin.planning_check.resume', ['id' => (int) $store['id']]));
?>
<?php include __DIR__ . "/_assets.php"; ?>
<form method="POST" action="<?= $_action ?>" class="pc-bar">
    <?= csrf_field() ?>
    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
    <span id="<?= htmlspecialchars($overlay_title_id) ?>" class="pc-bar__text"><?= htmlspecialchars(__('planning_check_editing_notice', ['date' => $date])) ?></span>
    <button type="submit" class="btn btn--primary btn--sm"><?= __('planning_check_back_to_validation') ?></button>
</form>
