<?php
/**
 * @var array  $store
 * @var array  $settings
 * @var array  $errors
 * @var string $BASE_URL
 */
$storeId = (int) $store['id'];
$action  = $BASE_URL . '/admin/stores/' . $storeId . '/daily-reports/settings';

// Employés choisis (comptes) et adresses saisies avant la sélection, encore envoyées mais plus ajoutables.
$recipientIds      = array_map('intval', (array) ($settings['mail_recipient_ids'] ?? []));
$legacyRecipients  = array_values(array_filter((array) ($settings['mail_recipients'] ?? []), 'is_string'));
$autoValidateTime = $settings['auto_validate_time'] ?? '';
$reminderTime      = $settings['reminder_time']      ?? '15:00';
$noReportTime      = $settings['no_report_time']     ?? '18:00';
?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('daily_report_settings') ?> — <?= htmlspecialchars($store['name']) ?>
    </h2>
    <div class="page-header__actions">
        <a href="<?= back_url($BASE_URL . '/admin/stores/' . $storeId . '/daily-reports') ?>"
           class="btn btn--ghost btn--sm">← <?= __('back') ?></a>
    </div>
</div>

<?= \kintai\Contract\View\Components\Flash::fromQuery('success', [])->render() ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert--danger mb-sm">
        <?php foreach ($errors as $msg): ?>
            <div><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= htmlspecialchars($action) ?>">
<?= csrf_field() ?>

    <!-- ── Activation ── -->
    <div class="card card--mb">
        <div class="card-header"><?= __('daily_report_setting_enabled') ?></div>
        <div class="card-body form-stack">
            <label class="check-label">
                <input type="checkbox" name="enabled" value="1"
                       <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
                <span><?= __('daily_report_setting_enabled_hint') ?></span>
            </label>
            <div class="form-group">
                <label class="form-label"><?= __('daily_report_setting_cumulative') ?></label>
                <select name="cumulative_mode" class="form-control form-control-sm">
                    <option value="per_day" <?= $settings['cumulative_mode'] === 'per_day' ? 'selected' : '' ?>><?= __('daily_report_mode_per_day') ?></option>
                    <option value="cumulative_system" <?= $settings['cumulative_mode'] === 'cumulative_system' ? 'selected' : '' ?>><?= __('daily_report_mode_cumulative_system') ?></option>
                    <option value="cumulative_input" <?= $settings['cumulative_mode'] === 'cumulative_input' ? 'selected' : '' ?>><?= __('daily_report_mode_cumulative_input') ?></option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── Permissions ── -->
    <div class="card card--mb">
        <div class="card-header"><?= __('daily_report_setting_permissions') ?></div>
        <div class="card-body">
            <p class="form-hint">
                <?= __('daily_report_setting_permissions_hint') ?>
                <?php // La page des rôles est réservée à l'Owner : un lien y menait les gérants vers une page 403. ?>
                <?php if (!empty($authUser['is_admin'])): ?>
                    <a href="<?= route_url('admin.roles') ?>"><?= __('roles') ?></a>
                <?php else: ?>
                    <?= __('roles') ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- ── Notifications e-mail ── -->
    <div class="card card--mb">
        <div class="card-header"><?= __('daily_report_setting_notifications') ?></div>
        <div class="card-body form-stack">

            <div class="form-group">
                <?= \kintai\Contract\View\Components\UserPicker::make('mail_recipient_ids', $candidates ?? [])
                    ->selected($recipientIds)
                    ->legend(__('daily_report_setting_recipients'))
                    ->hint(__('daily_report_setting_recipients_hint'))
                    ->render() ?>
                <?php if (!empty($errors['mail_recipient_ids'])): ?>
                    <span class="form-error"><?= htmlspecialchars((string) $errors['mail_recipient_ids']) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($legacyRecipients !== []): ?>
                <div class="form-group">
                    <fieldset class="user-picker">
                        <legend class="user-picker__legend"><?= __('daily_report_setting_legacy_recipients') ?></legend>
                        <p class="user-picker__hint"><?= __('daily_report_setting_legacy_recipients_hint') ?></p>
                        <ul class="user-picker__list">
                            <?php foreach ($legacyRecipients as $address): ?>
                                <li class="user-picker__item">
                                    <label class="user-picker__label">
                                        <input type="checkbox" name="legacy_recipients[]" value="<?= htmlspecialchars((string) $address, ENT_QUOTES) ?>" checked>
                                        <span class="user-picker__name"><?= htmlspecialchars((string) $address) ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </fieldset>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label"><?= __('daily_report_setting_auto_send') ?></label>
                <label class="check-label">
                    <input type="checkbox" name="auto_send_on_validate" value="1"
                           <?= !empty($settings['auto_send_on_validate']) ? 'checked' : '' ?>>
                    <span><?= __('daily_report_setting_auto_send_hint') ?></span>
                </label>
            </div>

        </div>
    </div>

    <!-- ── Horaires : validation automatique et rappels ── -->
    <div class="card card--mb">
        <div class="card-header"><?= __('daily_report_setting_times') ?></div>
        <div class="card-body form-stack">

            <div class="form-group">
                <label class="form-label" for="dr-auto-validate-time"><?= __('daily_report_setting_auto_validate_time') ?></label>
                <input type="time" id="dr-auto-validate-time" name="auto_validate_time" class="form-control form-control-sm"
                       value="<?= htmlspecialchars((string) $autoValidateTime) ?>">
                <span class="form-hint"><?= __('daily_report_setting_auto_validate_time_hint') ?></span>
            </div>

            <div class="form-group">
                <label class="form-label" for="dr-reminder-time"><?= __('daily_report_setting_reminder_time') ?></label>
                <input type="time" id="dr-reminder-time" name="reminder_time" class="form-control form-control-sm"
                       value="<?= htmlspecialchars((string) $reminderTime) ?>">
                <span class="form-hint"><?= __('daily_report_setting_reminder_time_hint') ?></span>
            </div>

            <div class="form-group">
                <label class="form-label" for="dr-no-report-time"><?= __('daily_report_setting_no_report_time') ?></label>
                <input type="time" id="dr-no-report-time" name="no_report_time" class="form-control form-control-sm"
                       value="<?= htmlspecialchars((string) $noReportTime) ?>">
                <span class="form-hint"><?= __('daily_report_setting_no_report_time_hint') ?></span>
            </div>

        </div>
    </div>

    <!-- ── Colonnes du planning ── -->
    <div class="card card--mb">
        <div class="card-header"><?= __('daily_report_setting_columns') ?></div>
        <div class="card-body form-stack">
            <p class="form-hint mb-sm"><?= __('daily_report_setting_columns_hint') ?></p>
            <div class="check-group">
                <?php
                $allCols = [
                    'employee'    => __('employee'),
                    'type'        => __('shift_type'),
                    'schedule'    => __('schedule'),
                    'gross_hours' => __('gross_h_col'),
                    'pause'       => __('pause'),
                    'net_hours'   => __('net_h_col'),
                ];
                $showCols = $settings['show_columns'] ?? array_keys($allCols);
                foreach ($allCols as $colKey => $colLabel):
                ?>
                    <label class="check-label">
                        <input type="checkbox"
                               name="show_columns[]"
                               value="<?= $colKey ?>"
                               class="dr-col-toggle"
                               data-col="<?= $colKey ?>"
                               <?= in_array($colKey, $showCols, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($colLabel) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Aperçu -->
            <div class="mt-sm">
                <label class="form-label"><?= __('daily_report_setting_columns_preview') ?></label>
                <div class="table-responsive">
                    <table class="data-table" id="drColumnPreview">
                        <thead>
                            <tr>
                                <?php if (in_array('employee', $showCols, true)): ?><th data-col="employee"><?= __('employee') ?></th><?php endif; ?>
                                <?php if (in_array('type', $showCols, true)): ?><th data-col="type"><?= __('shift_type') ?></th><?php endif; ?>
                                <?php if (in_array('schedule', $showCols, true)): ?><th data-col="schedule"><?= __('schedule') ?></th><?php endif; ?>
                                <?php if (in_array('gross_hours', $showCols, true)): ?><th data-col="gross_hours" class="td-right"><?= __('gross_h_col') ?></th><?php endif; ?>
                                <?php if (in_array('pause', $showCols, true)): ?><th data-col="pause" class="td-right"><?= __('pause') ?></th><?php endif; ?>
                                <?php if (in_array('net_hours', $showCols, true)): ?><th data-col="net_hours" class="td-right"><?= __('net_h_col') ?></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php if (in_array('employee', $showCols, true)): ?><td data-col="employee"><?= __('daily_report_col_preview_employee') ?></td><?php endif; ?>
                                <?php if (in_array('type', $showCols, true)): ?><td data-col="type" class="td-muted"><?= __('daily_report_col_preview_type') ?></td><?php endif; ?>
                                <?php if (in_array('schedule', $showCols, true)): ?><td data-col="schedule" class="td-muted td-nowrap"><?= __('daily_report_col_preview_schedule') ?></td><?php endif; ?>
                                <?php if (in_array('gross_hours', $showCols, true)): ?><td data-col="gross_hours" class="td-right td-mono"><?= __('daily_report_col_preview_gross') ?></td><?php endif; ?>
                                <?php if (in_array('pause', $showCols, true)): ?><td data-col="pause" class="td-right td-muted td-mono"><?= __('daily_report_col_preview_pause') ?></td><?php endif; ?>
                                <?php if (in_array('net_hours', $showCols, true)): ?><td data-col="net_hours" class="td-right td-mono"><?= __('daily_report_col_preview_net') ?></td><?php endif; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary"><?= __('save') ?></button>
        <a href="<?= back_url($BASE_URL . '/admin/stores/' . $storeId . '/daily-reports') ?>"
           class="btn btn--ghost"><?= __('cancel') ?></a>
    </div>

</form>

<script nonce="<?= function_exists('csp_nonce') ? csp_nonce() : '' ?>">
(function() {
    var toggles = document.querySelectorAll('.dr-col-toggle');
    toggles.forEach(function(cb) {
        cb.addEventListener('change', function() {
            var col = this.dataset.col;
            var previewCells = document.querySelectorAll('#drColumnPreview [data-col="' + col + '"]');
            previewCells.forEach(function(el) {
                if (el.tagName === 'TH' || el.tagName === 'TD') {
                    el.style.display = cb.checked ? '' : 'none';
                }
            });
        });
    });
})();
</script>
