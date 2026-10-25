@php
/**
 * Template HTML optimisé mPDF — Rapport journalier
 * Rendu sans layout : soit pour la génération PDF serveur, soit directement
 * comme aperçu navigateur (avec barre d'outils) quand $downloadUrl est
 * fourni — voir DailyReportPdfService::generateHtml() vs generateBytes().
 *
 * @var array       $report
 * @var array       $store
 * @var array       $author       auteur du rapport (first_name, last_name, email, employee_code…)
 * @var string      $locale       'fr' | 'en' | 'ja'
 * @var string|null $downloadUrl
 */

function drPdfNum(mixed $n, int $dec = 0): string {
    return number_format((float) $n, $dec, '.', ',');
}
function drPdfDate(string $d): string {
    $dt = \DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d/m/Y') : $d;
}
function drPdfDt(string $dt): string {
    $d = \DateTime::createFromFormat('Y-m-d H:i:s', $dt);
    return $d ? $d->format('d/m/Y H:i') : $dt;
}
function drPdfH(int $minutes): string {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
}

$storeName   = $store['name'] ?? 'Kintai';
$currency    = currency_symbol($store['currency'] ?? 'JPY', store_currency_style($store));
$reportDate  = $report['report_date'] ?? '';
$authorName  = trim(($author['last_name'] ?? '') . ' ' . ($author['first_name'] ?? ''))
               ?: ($author['display_name'] ?? ($author['email'] ?? '—'));
$st          = $report['status'] ?? 'draft';
$statusLabel = __('daily_report_status_' . $st);
$statusClass = match ($st) {
    'submitted' => 'status-submitted',
    'validated' => 'status-validated',
    default     => 'status-draft',
};

$bodyFont = $locale === 'ja'
    ? 'sun-exta, Arial, sans-serif'
    : 'Arial, sans-serif';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
<meta charset="UTF-8">
<style nonce="{!! csp_nonce() !!}">
@php
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-base.css');
echo file_get_contents(bundle_asset_path('daily-report', 'css/pdf-daily-report.css') ?? '');
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-brand.css');
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-preview.css');
@endphp
@if ($locale === 'ja')
body { font-family: sun-exta, Arial, sans-serif; }
@endif
</style>
</head>
<body>

@includeInScope('_partials._pdf-preview-toolbar')
<div class="pdf-preview-page">

<!-- En-tête -->
<table class="header-table">
  <tr>
    <td class="vt col-left">
      <span class="brand">Kintai<span class="brand-store">{{ $storeName }}</span></span>
    </td>
    <td class="vt col-right doc-title">
      <span class="doc-title-main">{!! __('daily_report_title') !!}</span>
      <span class="doc-ref">{!! drPdfDate($reportDate) !!}</span>
      <span class="doc-ref">{!! __('payslip_issued_on') !!} {!! date('d/m/Y') !!}</span>
    </td>
  </tr>
</table>
<hr class="header-sep">

<!-- Auteur -->
<table class="meta-table">
  <tbody>
    <tr>
      <td class="label">{!! __('daily_report_author') !!}</td>
      <td>{{ $authorName }}</td>
    </tr>
    @if (!empty($author['employee_code']))
    <tr>
      <td class="label">{!! __('employee_code') !!}</td>
      <td>{{ $author['employee_code'] }}</td>
    </tr>
    @endif
    <tr>
      <td class="label">{!! __('created_at') !!}</td>
      <td>{!! drPdfDt($report['created_at'] ?? '') !!}</td>
    </tr>
  </tbody>
</table>

<!-- KPI -->
<div class="section-title section-title--no-mt">{!! __('daily_report_title') !!}</div>
<table class="kpi-table">
  <tbody>
    <tr>
      <td class="label">{!! __('daily_report_sales') !!}</td>
      <td class="value">{!! drPdfNum($report['sales_total'] ?? 0) !!} {!! $currency !!}</td>
    </tr>
    <tr class="kpi-alt">
      <td class="label">{!! __('daily_report_customers') !!}</td>
      <td class="value">{!! drPdfNum($report['customer_count'] ?? 0) !!}</td>
    </tr>
    <tr>
      <td class="label">{!! __('daily_report_labor') !!}</td>
      <td class="value">{!! drPdfNum($report['labor_cost'] ?? 0) !!} {!! $currency !!}</td>
    </tr>
    <tr class="kpi-alt">
      <td class="label">{!! __('daily_report_waste') !!}</td>
      <td class="value">{!! drPdfNum($report['waste_total'] ?? 0) !!} {!! $currency !!}</td>
    </tr>
  </tbody>
</table>

<!-- Shifts du jour -->
<div class="section-title">{!! __('daily_report_shifts_of_day') !!}</div>
@if (empty($shiftRows))
  <p class="dr-no-shift">{!! __('daily_report_no_shifts') !!}</p>
@else
<table class="dr-shifts-table">
  <thead>
    <tr>
      <th>{!! __('employee') !!}</th>
      <th>{!! __('shift_type') !!}</th>
      <th>{!! __('schedule') !!}</th>
      <th class="tr">{!! __('gross_h_col') !!}</th>
      <th class="tr">{!! __('pause') !!}</th>
      <th class="tr">{!! __('net_h_col') !!}</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($shiftRows as $i => $row)
    <tr class="{!! $i % 2 !== 0 ? 'dr-shift-alt' : '' !!}">
      <td>{{ $row['employee'] }}</td>
      <td>{{ $row['type'] }}</td>
      <td class="muted">{{ $row['start'] }}–{{ $row['end'] }}</td>
      <td class="tr">{!! drPdfH($row['gross_min']) !!}</td>
      <td class="tr muted">{!! $row['pause_min'] > 0 ? $row['pause_min'] . ' min' : '—' !!}</td>
      <td class="tr">{!! drPdfH($row['net_min']) !!}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

<!-- Notes -->
@if (!empty($report['notes']))
<div class="notes-section">
  <div class="section-title">{!! __('daily_report_notes') !!}</div>
  <div class="notes-box">
    {!! nl2br(htmlspecialchars($report['notes'])) !!}  </div>
</div>
@endif

<!-- Suivi workflow -->
<div class="section-title">{!! __('status') !!}</div>
<table class="meta-table">
  <tbody>
    <tr>
      <td class="label">{!! __('status') !!}</td>
      <td><span class="status-badge {!! $statusClass !!}">{!! $statusLabel !!}</span></td>
    </tr>
    @if (!empty($report['submitted_at']))
    <tr>
      <td class="label">{!! __('daily_report_submitted_at') !!}</td>
      <td>{!! drPdfDt($report['submitted_at']) !!}</td>
    </tr>
    @endif
    @if (!empty($report['validated_at']))
    <tr>
      <td class="label">{!! __('daily_report_validated_at') !!}</td>
      <td>{!! drPdfDt($report['validated_at']) !!}</td>
    </tr>
    @endif
  </tbody>
</table>

<!-- Signatures -->
<table class="sig-table">
  <tr>
    <td class="sig-col-left">
      <div class="sig-label">{!! __('daily_report_author') !!}</div>
      <div class="sig-area"></div>
      <div class="sig-name">{{ $authorName }}</div>
    </td>
    <td class="sig-col-right">
      <div class="sig-label">{!! __('daily_report_validated_by') !!}</div>
      <div class="sig-area"></div>
      <div class="sig-name">{{ $storeName }}</div>
    </td>
  </tr>
</table>

<!-- Pied de page -->
<div class="footer">
  {!! __('payslip_footer', ['date' => date('d/m/Y H:i')]) !!}</div>

</div>
</body>
</html>
