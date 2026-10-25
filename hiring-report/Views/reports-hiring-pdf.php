<?php
/**
 * Template HTML for mPDF — Hiring Report (採用報告書)
 * Rendered standalone (no layout) for PDF generation, or directly as an
 * HTML preview (with a toolbar) when $downloadUrl is set — see
 * HasStaffReportCrud::reportPdf() vs reportPdfDownload().
 *
 * @var array       $report
 * @var array       $store
 * @var string|null $downloadUrl
 */
$genderLabel = match ($report['gender'] ?? null) {
    'male'   => __('male'),
    'female' => __('female'),
    default  => '—',
};
$taxLabel = match ($report['tax_classification'] ?? null) {
    'kou'  => __('hiring_report_tax_kou'),
    'otsu' => __('hiring_report_tax_otsu'),
    default => '—',
};
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style nonce="<?= csp_nonce() ?>">
<?php
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-brand.css');
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-preview.css');
echo file_get_contents(bundle_asset_path('hiring-report', 'css/pdf-hiring-report.css') ?? '');
?>
</style>
</head>
<body>

<?php include __DIR__ . '/../../../UI/View/_partials/_pdf-preview-toolbar.php'; ?>
<div class="pdf-preview-page">

<h1><?= __('hiring_report') ?></h1>
<div class="subtitle"><?= htmlspecialchars($store['name'] ?? '') ?></div>

<table>
    <tr><th colspan="2"><?= __('hiring_report_section_identity') ?></th></tr>
    <tr><td class="label-cell"><?= __('employee_number') ?></td><td><?= htmlspecialchars($report['employee_number'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('employee_name') ?></td><td><?= htmlspecialchars($report['employee_name'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('furigana_last_name') ?></td><td><?= htmlspecialchars($report['furigana_last_name'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('furigana_first_name') ?></td><td><?= htmlspecialchars($report['furigana_first_name'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('gender') ?></td><td><?= htmlspecialchars($genderLabel) ?></td></tr>
    <tr><td class="label-cell"><?= __('tax_classification') ?></td><td><?= htmlspecialchars($taxLabel) ?></td></tr>
    <tr><td class="label-cell"><?= __('birth_date') ?></td><td><?= htmlspecialchars($report['birth_date'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('education') ?></td><td><?= htmlspecialchars($report['education'] ?? '') ?></td></tr>
</table>

<table>
    <tr><th colspan="2"><?= __('hiring_report_section_contact') ?></th></tr>
    <tr><td class="label-cell"><?= __('postal_code') ?></td><td><?= htmlspecialchars($report['postal_code'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('address') ?></td><td><?= nl2br(htmlspecialchars($report['address'] ?? '')) ?></td></tr>
    <tr><td class="label-cell"><?= __('phone') ?></td><td><?= htmlspecialchars($report['phone'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('mobile_phone') ?></td><td><?= htmlspecialchars($report['mobile_phone'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('email') ?></td><td><?= htmlspecialchars($report['email'] ?? '') ?></td></tr>
</table>

<table>
    <tr><th colspan="2"><?= __('hiring_report_section_guarantor') ?></th></tr>
    <tr><td class="label-cell"><?= __('guarantor_name') ?></td><td><?= htmlspecialchars($report['guarantor_name'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('guarantor_phone') ?></td><td><?= htmlspecialchars($report['guarantor_phone'] ?? '') ?></td></tr>
</table>

<table>
    <tr><th colspan="2"><?= __('hiring_report_section_employment') ?></th></tr>
    <tr><td class="label-cell"><?= __('hiring_report_store_name') ?></td><td><?= htmlspecialchars($report['store_name'] ?? $store['name'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('hiring_report_hired_by') ?></td><td><?= htmlspecialchars($report['hired_by'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('hiring_report_hire_date') ?></td><td><?= htmlspecialchars($report['hire_date'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('notes') ?></td><td><?= nl2br(htmlspecialchars($report['notes'] ?? '')) ?></td></tr>
</table>

<div class="footer">
    <?= __('pdf_generated_by') ?> Kintai — <?= date('Y-m-d H:i') ?>
</div>

</div>
</body>
</html>
