<?php
/**
 * Réglages de la validation du planning pour un magasin.
 *
 * @var string               $title
 * @var array<string, mixed> $store
 * @var array<string, mixed> $settings
 * @var list<string>         $errors clés de traduction
 */
use kintai\Contract\View\Components\Flash;

$_id = (int) $store['id'];
$_days = [1 => 'planning_check_day_1', 2 => 'planning_check_day_2', 3 => 'planning_check_day_3', 4 => 'planning_check_day_4', 5 => 'planning_check_day_5', 6 => 'planning_check_day_6', 7 => 'planning_check_day_7'];
$_error = (string) ($_GET['error'] ?? '');
?>
<?php include __DIR__ . "/_assets.php"; ?>
<div class="page-header">
    <h2 class="page-header__title"><?= htmlspecialchars($title) ?> — <?= htmlspecialchars((string) $store['name']) ?></h2>
    <div class="page-header__actions">
        <a href="<?= htmlspecialchars(route_url('admin.planning_check.history', ['id' => $_id])) ?>" class="btn btn--ghost btn--sm">← <?= __('back') ?></a>
    </div>
</div>

<?= Flash::fromQuery('success', ['saved' => __('planning_check_flash_saved'), 'test_sent' => __('planning_check_flash_test_sent')])->render() ?>
<?php if ($_error !== ''): ?>
    <div class="alert alert--danger mb-sm"><?= htmlspecialchars(__('planning_check_flash_' . preg_replace('/[^a-z_]/', '', $_error))) ?></div>
<?php endif; ?>
<?php foreach ($errors as $_e): ?>
    <div class="alert alert--danger mb-sm"><?= htmlspecialchars(__($_e)) ?></div>
<?php endforeach; ?>

<form method="POST" action="<?= htmlspecialchars(route_url('admin.planning_check.settings.save', ['id' => $_id])) ?>" class="pc-settings">
    <?= csrf_field() ?>

    <div class="card pc-settings__wide">
        <div class="card-body pc-switch-row">
            <div class="pc-switch-row__text">
                <p class="pc-switch-row__title"><?= __('planning_check_setting_enabled') ?></p>
                <p class="form-hint"><?= __('planning_check_setting_enabled_hint') ?></p>
            </div>
            <label class="form-toggle">
                <input type="checkbox" name="enabled" value="1" class="form-toggle__input"<?= !empty($settings['enabled']) ? ' checked' : '' ?> aria-label="<?= htmlspecialchars(__('planning_check_setting_enabled')) ?>">
                <span class="form-toggle__track"></span>
            </label>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><?= __('planning_check_setting_timing') ?></div>
        <div class="card-body form-stack">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="pc-window"><?= __('planning_check_setting_window') ?></label>
                    <input type="time" id="pc-window" name="window_start_time" class="form-control" value="<?= htmlspecialchars((string) $settings['window_start_time']) ?>" required>
                    <p class="form-hint"><?= __('planning_check_setting_window_hint') ?></p>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pc-deadline"><?= __('planning_check_setting_deadline') ?></label>
                    <input type="time" id="pc-deadline" name="deadline_time" class="form-control" value="<?= htmlspecialchars((string) $settings['deadline_time']) ?>" required>
                    <p class="form-hint"><?= __('planning_check_setting_deadline_hint') ?></p>
                </div>
            </div>
            <fieldset class="form-group pc-weekdays">
                <legend class="form-label"><?= __('planning_check_setting_weekdays') ?></legend>
                <div class="pc-weekdays__list">
                    <?php foreach ($_days as $_n => $_key): ?>
                        <label class="pc-day">
                            <input type="checkbox" name="weekdays[]" value="<?= $_n ?>" class="pc-day__input"<?= in_array($_n, (array) $settings['weekdays'], true) ? ' checked' : '' ?>>
                            <span class="pc-day__label"><?= __($_key) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="form-hint"><?= __('planning_check_setting_weekdays_hint') ?></p>
            </fieldset>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><?= __('planning_check_setting_recipients') ?></div>
        <div class="card-body form-stack">
            <div class="form-group">
                <?= \kintai\Contract\View\Components\UserPicker::make('mail_recipient_ids', $candidates ?? [])
                    ->selected((array) ($settings['mail_recipient_ids'] ?? []))
                    ->hint(__('planning_check_setting_recipients_hint'))
                    ->render() ?>
            </div>
            <?php if (!empty($settings['mail_recipients'])): ?>
                <div class="form-group">
                    <fieldset class="user-picker">
                        <legend class="user-picker__legend"><?= __('planning_check_setting_legacy_recipients') ?></legend>
                        <p class="user-picker__hint"><?= __('planning_check_setting_legacy_recipients_hint') ?></p>
                        <ul class="user-picker__list">
                            <?php foreach ((array) $settings['mail_recipients'] as $_address): ?>
                                <li class="user-picker__item">
                                    <label class="user-picker__label">
                                        <input type="checkbox" name="legacy_recipients[]" value="<?= htmlspecialchars((string) $_address, ENT_QUOTES) ?>" checked>
                                        <span class="user-picker__name"><?= htmlspecialchars((string) $_address) ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </fieldset>
                </div>
            <?php endif; ?>
            <div class="pc-switch-row">
                <p class="pc-switch-row__title"><?= __('planning_check_setting_open_shifts') ?></p>
                <label class="form-toggle">
                    <input type="checkbox" name="include_open_shifts" value="1" class="form-toggle__input"<?= !empty($settings['include_open_shifts']) ? ' checked' : '' ?> aria-label="<?= htmlspecialchars(__('planning_check_setting_open_shifts')) ?>">
                    <span class="form-toggle__track"></span>
                </label>
            </div>
        </div>
    </div>

    <div class="form-actions pc-settings__wide">
        <button type="submit" class="btn btn--primary"><?= __('save') ?></button>
    </div>
</form>

<form method="POST" action="<?= htmlspecialchars(route_url('admin.planning_check.test', ['id' => $_id])) ?>" class="card pc-test">
    <?= csrf_field() ?>
    <div class="card-body pc-switch-row">
        <div class="pc-switch-row__text">
            <p class="pc-switch-row__title"><?= __('planning_check_test_send') ?></p>
            <p class="form-hint"><?= __('planning_check_test_hint') ?></p>
        </div>
        <button type="submit" class="btn btn--ghost"><?= __('planning_check_test_send') ?></button>
    </div>
</form>
<?php unset($_e, $_n, $_key); ?>
