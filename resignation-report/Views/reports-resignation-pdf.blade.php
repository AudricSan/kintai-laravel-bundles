@php
/**
 * Template HTML optimisé mPDF — 退職報告書 (Resignation Report)
 * Rendu sans layout : soit pour la génération PDF serveur, soit directement
 * comme aperçu navigateur (avec barre d'outils) quand $downloadUrl est
 * fourni — voir HasStaffReportCrud::reportPdf() vs reportPdfDownload().
 *
 * @var array       $report
 * @var array       $store
 * @var string|null $downloadUrl
 */
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style nonce="{!! csp_nonce() !!}">
@php
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-base.css');
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-brand.css');
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-preview.css');
echo file_get_contents(bundle_asset_path('resignation-report', 'css/pdf-resignation-report.css') ?? '');
@endphp
</style>
</head>
<body>

@includeInScope('_partials._pdf-preview-toolbar')
<div class="pdf-preview-page">

<h1>{!! __('resignation_report_pdf_title') !!}</h1>

<p class="pdf-right pdf-date-line">
    {!! __('date') !!}: {!! date('Y-m-d') !!}</p>

<table>
    <tr>
        <th>{!! __('employee_number') !!}</th>
        <td>{{ $report['employee_number'] ?? '—' }}</td>
    </tr>
    <tr>
        <th>{!! __('employee_name') !!}</th>
        <td>{{ $report['employee_name'] ?? '—' }}</td>
    </tr>
    <tr>
        <th>{!! __('resignation_report_resignation_date') !!}</th>
        <td>{{ $report['resignation_date'] ?? '—' }}</td>
    </tr>
    <tr>
        <th>{!! __('reason') !!}</th>
        <td>{!! nl2br(htmlspecialchars($report['reason'] ?? '—')) !!}</td>
    </tr>
    <tr>
        <th>{!! __('resignation_report_resignation_notice') !!}</th>
        <td>{!! nl2br(htmlspecialchars($report['resignation_notice'] ?? '—')) !!}</td>
    </tr>
    <tr>
        <th>{!! __('notes') !!}</th>
        <td>{!! nl2br(htmlspecialchars($report['notes'] ?? '—')) !!}</td>
    </tr>
    <tr>
        <th>{!! __('resignation_report_person_in_charge') !!}</th>
        <td>{{ $report['person_in_charge'] ?? '—' }}</td>
    </tr>
</table>

<div class="footer">
    {{ $store['name'] ?? '' }} — {!! date('Y-m-d H:i') !!}</div>

</div>
</body>
</html>
