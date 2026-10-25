@php
/**
 * Template HTML for mPDF — Salary reports list export (item 5).
 * Rendered standalone (no layout) : soit pour la génération PDF serveur,
 * soit directement comme aperçu navigateur (avec barre d'outils) quand
 * $downloadUrl est fourni.
 *
 * @var array       $reports
 * @var array       $store_names  Map store_id => nom
 * @var string      $generated_at
 * @var string|null $downloadUrl
 */
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style nonce="{!! csp_nonce() !!}">
@php
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-brand.css');
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-preview.css');
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-export-table.css');
@endphp
</style>
</head>
<body>

@includeInScope('_partials._pdf-preview-toolbar')
<div class="pdf-preview-page">

<h1>{!! __('salary_report_sr_title') !!}</h1>
<div class="subtitle">{!! count($reports) !!} — {{ $generated_at }}</div>

<table>
    <tr>
        <th>{!! __('store') !!}</th>
        <th>{!! __('salary_report_sr_target_month') !!}</th>
        <th>{!! __('salary_report_sr_person_in_charge') !!}</th>
        <th class="td-right">{!! __('salary_report_sr_total_payment') !!}</th>
        <th class="td-right">{!! __('salary_report_sr_net_payment') !!}</th>
        <th class="td-right">{!! __('salary_report_sr_active_employees') !!}</th>
    </tr>
    @foreach ($reports as $r)
    <tr>
        <td>{{ $store_names[(int) ($r['store_id'] ?? 0)] ?? '—' }}</td>
        <td>{{ $r['target_month'] ?? '—' }}</td>
        <td>{{ $r['person_in_charge'] ?? '—' }}</td>
        <td class="td-right">{!! number_format((float) ($r['total_payment'] ?? 0), 0) !!}</td>
        <td class="td-right">{!! number_format((float) ($r['net_payment'] ?? 0), 0) !!}</td>
        <td class="td-right">{!! (int) ($r['active_employees'] ?? 0) !!}</td>
    </tr>
    @endforeach
</table>

<div class="footer">
    {!! __('pdf_generated_by') !!} Kintai — {{ $generated_at }}</div>

</div>
</body>
</html>
