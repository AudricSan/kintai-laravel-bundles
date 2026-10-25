<?php
use kintai\Contract\View\Components\Flash;

/** @var string $retentionDays */
/** @var string $cleanupDelay */
/** @var string $BASE_URL */

echo Flash::fromQuery('success', ['saved' => __('save_success')])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('store_photos_photo_settings') ?></h2>
    <div class="page-header__actions">
        <a href="<?= back_url($BASE_URL . '/admin/photos') ?>" class="btn btn--ghost btn--sm">← <?= __('back') ?></a>
    </div>
</div>

<form method="POST" action="<?= $BASE_URL ?>/admin/photos/settings">
<?= csrf_field() ?>

    <div class="card card--mb">
        <div class="card-header"><?= __('store_photos_photo_retention_title') ?></div>
        <div class="card-body form-stack">
            <div class="form-group">
                <label class="form-label"><?= __('store_photos_photo_retention_days') ?></label>
                <input type="number" name="photo_retention_days" class="form-control form-control-sm"
                       value="<?= (int) $retentionDays ?>" min="1" max="365" required>
                <span class="form-hint"><?= __('store_photos_photo_retention_days_hint') ?></span>
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('store_photos_photo_cleanup_delay') ?></label>
                <input type="number" name="photo_cleanup_delay" class="form-control form-control-sm"
                       value="<?= (int) $cleanupDelay ?>" min="1" max="365" required>
                <span class="form-hint"><?= __('store_photos_photo_cleanup_delay_hint') ?></span>
            </div>
            <div class="form-group">
                <p class="text-sm text-muted"><?= __('store_photos_photo_retention_explain') ?></p>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary"><?= __('save') ?></button>
        <a href="<?= back_url($BASE_URL . '/admin/photos') ?>" class="btn btn--ghost"><?= __('cancel') ?></a>
    </div>
</form>
