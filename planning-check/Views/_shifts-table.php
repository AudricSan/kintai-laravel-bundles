<?php
/**
 * Aperçu des shifts du jour (fenêtres et historique) : un tableau par employé avec type, horaires et durée nette.
 *
 * @var array{rows: list<array<string, mixed>>, total_minutes: int, assigned: int, open: int} $day
 */
$_fmt = static fn(int $min): string => intdiv($min, 60) . 'h' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT);
?>
<?php if ($day['rows'] === []): ?>
    <div class="alert alert--danger pc-empty"><strong><?= __('planning_check_no_shifts') ?></strong></div>
<?php else: ?>
    <table class="data-table pc-table">
        <thead>
            <tr>
                <th><?= __('planning_check_col_employee') ?></th>
                <th><?= __('planning_check_col_type') ?></th>
                <th><?= __('planning_check_col_hours') ?></th>
                <th class="text-right"><?= __('planning_check_col_net') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($day['rows'] as $_row): ?>
            <tr>
                <td><?= $_row['open'] ? '<em>' . htmlspecialchars(__('planning_check_open_shift')) . '</em>' : htmlspecialchars((string) $_row['employee']) ?></td>
                <td><?= htmlspecialchars((string) $_row['type']) ?></td>
                <td><?= htmlspecialchars($_row['start']) ?>–<?= htmlspecialchars($_row['end']) ?></td>
                <td class="text-right"><?= $_fmt((int) $_row['net_min']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3"><?= __('planning_check_total') ?></th>
                <th class="text-right"><?= $_fmt((int) $day['total_minutes']) ?></th>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>
<?php unset($_fmt, $_row); ?>
