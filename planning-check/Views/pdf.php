<?php
/**
 * PDF du planning de demain (mPDF, A4 paysage) : en-tête, tableau par employé, total, mention « version mise à jour ».
 *
 * @var array<string, mixed> $store
 * @var array<string, mixed> $check
 * @var array<string, mixed> $day
 * @var array<string, mixed> $context validated_by, updated, generated_at
 * @var string               $locale
 */
$_fmt = static fn(int $min): string => intdiv($min, 60) . 'h' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale) ?>">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; font-size: 10pt; color: #222; }
    h1 { font-size: 16pt; margin: 0 0 4px; color: #2c3e50; }
    .meta { color: #666; margin-bottom: 10px; }
    .updated { color: #b45309; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #eaf2fb; text-align: left; padding: 5px 8px; border-bottom: 2px solid #2c3e50; }
    td { padding: 5px 8px; border-bottom: 1px solid #ddd; }
    .r { text-align: right; }
    tfoot th { border-top: 2px solid #2c3e50; }
    .empty { padding: 20px; text-align: center; color: #666; font-size: 12pt; }
    .foot { margin-top: 14px; font-size: 8pt; color: #999; }
</style>
</head>
<body>
    <h1><?= htmlspecialchars(__('planning_check_pdf_title', ['date' => (string) $check['target_date']])) ?></h1>
    <div class="meta">
        <?= htmlspecialchars((string) $store['name']) ?>
        <?php if (!empty($context['validated_by'])): ?> — <?= htmlspecialchars(__('planning_check_pdf_validated', ['name' => (string) $context['validated_by'], 'time' => (string) ($context['generated_at'] ?? '')])) ?><?php endif; ?>
        <?php if (!empty($context['updated'])): ?> — <span class="updated"><?= htmlspecialchars(__('planning_check_pdf_updated', ['time' => (string) ($context['generated_at'] ?? '')])) ?></span><?php endif; ?>
    </div>

    <?php if ($day['rows'] === []): ?>
        <div class="empty"><?= htmlspecialchars(__('planning_check_pdf_no_shift')) ?></div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= htmlspecialchars(__('planning_check_col_employee')) ?></th>
                    <th><?= htmlspecialchars(__('planning_check_col_type')) ?></th>
                    <th><?= htmlspecialchars(__('planning_check_col_hours')) ?></th>
                    <th class="r"><?= htmlspecialchars(__('planning_check_col_net')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($day['rows'] as $_row): ?>
                <tr>
                    <td><?= $_row['open'] ? htmlspecialchars(__('planning_check_open_shift')) : htmlspecialchars((string) $_row['employee']) ?></td>
                    <td><?= htmlspecialchars((string) $_row['type']) ?></td>
                    <td><?= htmlspecialchars($_row['start']) ?> – <?= htmlspecialchars($_row['end']) ?></td>
                    <td class="r"><?= $_fmt((int) $_row['net_min']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3"><?= htmlspecialchars(__('planning_check_total')) ?></th>
                    <th class="r"><?= $_fmt((int) $day['total_minutes']) ?></th>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
    <div class="foot">Kintai — <?= htmlspecialchars((string) $store['name']) ?></div>
</body>
</html>
