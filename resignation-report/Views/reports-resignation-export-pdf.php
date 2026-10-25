<?php
/**
 * Template HTML for mPDF — Resignation reports list export (item 5).
 * Rendered standalone (no layout) : soit pour la génération PDF serveur,
 * soit directement comme aperçu navigateur (avec barre d'outils) quand
 * $downloadUrl est fourni.
 *
 * @var array       $reports
 * @var array       $store_names  Map store_id => nom
 * @var string      $generated_at
 * @var string|null $downloadUrl
 */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style nonce="<?= csp_nonce() ?>">
<?php
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-brand.css');
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-preview.css');
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-export-table.css');
?>
</style>
</head>
<body>

<?php include __DIR__ . '/../../../UI/View/_partials/_pdf-preview-toolbar.php'; ?>
<div class="pdf-preview-page">

<h1><?= __('resignation_report_resignation_reports') ?></h1>
<div class="subtitle"><?= count($reports) ?> — <?= htmlspecialchars($generated_at) ?></div>

<table>
    <tr>
        <th><?= __('store') ?></th>
        <th><?= __('employee_number') ?></th>
        <th><?= __('employee_name') ?></th>
        <th><?= __('resignation_report_resignation_date') ?></th>
        <th><?= __('resignation_report_person_in_charge') ?></th>
    </tr>
    <?php foreach ($reports as $r): ?>
    <tr>
        <td><?= htmlspecialchars($store_names[(int) ($r['store_id'] ?? 0)] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['employee_number'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['employee_name'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['resignation_date'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['person_in_charge'] ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<div class="footer">
    <?= __('pdf_generated_by') ?> Kintai — <?= htmlspecialchars($generated_at) ?>
</div>

</div>
</body>
</html>
