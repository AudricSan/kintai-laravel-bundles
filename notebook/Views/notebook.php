<?php

use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;
use kintai\Contract\View\Components\Flash;

/** @var array $entries */
/** @var int   $highlight_id note désignée par une notification (0 = aucune) */
/** @var array $stores_map        id → nom */
/** @var array $store_ids         stores accessibles à l'utilisateur */
/** @var bool  $can_global        peut poster une note "toute l'organisation" */
/** @var bool  $show_expired */
/** @var bool  $can_create */

$entries      ??= [];
$stores_map   ??= [];
$store_ids    ??= [];
$can_global   ??= false;
$show_expired ??= false;
$can_create   ??= false;
?>

<?php if ($notebookCss = bundle_asset('notebook', 'css/notebook.css')): ?>
<link rel="stylesheet" href="<?= $notebookCss ?>">
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('bundle_notebook') ?></h2>
    <a href="<?= route_url('notebook.index') ?>?show_expired=<?= $show_expired ? '0' : '1' ?>" class="btn btn--ghost btn--sm">
        <?= $show_expired ? __('notebook_hide_expired') : __('notebook_show_expired') ?>
    </a>
</div>

<?= Flash::fromQuery('success', [
    'created' => __('notebook_created'),
    'updated' => __('notebook_updated'),
    'deleted' => __('notebook_deleted'),
])->render() ?>
<?= Flash::fromQuery('error', [
    'empty_content' => __('notebook_error_empty_content'),
    'forbidden'     => __('notebook_error_forbidden'),
])->render() ?>

<?php if ($can_create): ?>
<?php
ob_start();
?>
<form method="POST" action="<?= route_url('notebook.store') ?>" class="form-stack">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label"><?= __('notebook_content') ?></label>
        <textarea name="content" class="form-control" rows="3" required></textarea>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= __('store') ?></label>
            <select name="store_id" class="form-control">
                <?php if ($can_global): ?>
                    <option value=""><?= __('notebook_org_wide') ?></option>
                <?php endif; ?>
                <?php foreach ($store_ids as $sid): ?>
                    <option value="<?= (int) $sid ?>"><?= htmlspecialchars($stores_map[(int) $sid] ?? ('#' . $sid)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('notebook_expires_on') ?></label>
            <input type="date" name="expires_at" class="form-control">
        </div>
    </div>
    <div class="form-actions">
        <?= Button::make(__('notebook_publish'))->primary()->submit()->render() ?>
    </div>
</form>
<?php
echo Card::make()
    ->header('<span>' . __('notebook_new_entry') . '</span>')
    ->body(ob_get_clean())
    ->attrs(['class' => 'card--mb'])
    ->render();
?>
<?php endif; ?>

<?php if (empty($entries)): ?>
    <div class="empty-state"><?= __('notebook_no_entries') ?></div>
<?php else: ?>
    <ul class="notebook-widget-list">
        <?php foreach ($entries as $entry): ?>
            <li class="notebook-widget-item<?= !empty($entry['pinned']) ? ' notebook-widget-item--pinned' : '' ?><?= (int) ($highlight_id ?? 0) > 0 && (int) $entry['id'] === (int) $highlight_id ? ' notebook-widget-item--highlight' : '' ?>">
                <?php if (!empty($entry['pinned'])): ?>
                    <span class="notebook-widget-item__pin" title="<?= htmlspecialchars(__('notebook_pinned')) ?>">📌</span>
                <?php endif; ?>
                <p class="notebook-widget-item__content"><?= nl2br(htmlspecialchars($entry['content'] ?? '')) ?></p>
                <div class="notebook-widget-item__meta">
                    <span><?= htmlspecialchars($entry['author_name'] ?? __('notebook_anonymous_author')) ?></span>
                    <span>·</span>
                    <span><?= htmlspecialchars($entry['store_name'] ?? __('notebook_org_wide')) ?></span>
                    <span>·</span>
                    <span><?= htmlspecialchars((string) ($entry['created_at'] ?? '')) ?></span>
                    <?php if (!empty($entry['expires_at'])): ?>
                        <span>· <?= Badge::make(__('notebook_expires_on') . ' ' . substr((string) $entry['expires_at'], 0, 10))->neutral()->render() ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($entry['can_edit'])): ?>
                <div class="notebook-widget-item__actions">
                    <details>
                        <summary class="btn btn--ghost btn--sm"><?= __('edit') ?></summary>
                        <form method="POST" action="<?= route_url('notebook.update', ['id' => (int) $entry['id']]) ?>" class="form-stack mt-sm">
                            <?= csrf_field() ?>
                            <textarea name="content" class="form-control" rows="3" required><?= htmlspecialchars($entry['content'] ?? '') ?></textarea>
                            <input type="date" name="expires_at" class="form-control" value="<?= htmlspecialchars(substr((string) ($entry['expires_at'] ?? ''), 0, 10)) ?>">
                            <div class="form-actions">
                                <?= Button::make(__('save'))->primary()->sm()->submit()->render() ?>
                            </div>
                        </form>
                    </details>
                    <form method="POST" action="<?= route_url('notebook.delete', ['id' => (int) $entry['id']]) ?>" data-confirm="<?= htmlspecialchars(__('notebook_delete_confirm')) ?>">
                        <?= csrf_field() ?>
                        <?= Button::make(__('delete'))->danger()->sm()->submit()->render() ?>
                    </form>
                    <?php if (!empty($entry['can_manage'])): ?>
                    <form method="POST" action="<?= route_url('notebook.pin', ['id' => (int) $entry['id']]) ?>">
                        <?= csrf_field() ?>
                        <?= Button::make(!empty($entry['pinned']) ? __('notebook_unpin') : __('notebook_pin'))->ghost()->sm()->submit()->render() ?>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
