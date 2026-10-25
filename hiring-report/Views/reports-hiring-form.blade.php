@php
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;
use kintai\Contract\View\Components\Flash;

/**
 * @var array  $store
 * @var array  $users
 * @var string $mode   'create'|'edit'
 * @var array  $report
 */
$storeId = (int) $store['id'];
$report  ??= [];
$reportId = (int) ($report['id'] ?? 0);
$base = $BASE_URL . '/admin/stores/' . $storeId . '/reports/hiring';
$action = $mode === 'edit'
    ? $base . '/' . $reportId . '/edit'
    : $base . '/create';
$title = $mode === 'edit' ? __('hiring_report_edit_hiring_report') : __('hiring_report_new_hiring_report');

$v = fn(string $key) => htmlspecialchars((string) ($report[$key] ?? ''));

echo Flash::fromQuery('error', ['already_exists' => __('hiring_report_report_already_exists')])->render();
@endphp
<div class="page-header">
    <h2 class="page-header__title">{{ $title }}</h2>
    <div class="page-header__actions">
        <a href="{{ back_url($base) }}" class="btn btn--ghost">← {!! __('back') !!}</a>
    </div>
</div>

<form method="POST" action="{{ $action }}" class="form-stack">
    {!! csrf_field() !!}
    @php
    // Mêmes 4 sections, dans le même ordre, que reports-hiring-show.php/-pdf.php
    // (section_identity/contact/guarantor/employment) — pour rester cohérent
    // entre le formulaire et la consultation d'un rapport d'embauche.
    ob_start();
    @endphp
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="hr-user_id">{!! __('user') !!}</label>
            <select id="hr-user_id" name="user_id" class="form-control">
                <option value="">— {!! __('select') !!} —</option>
                @foreach ($users as $u)
                    <option value="{!! (int) $u['id'] !!}" {!! (int) ($report['user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' !!}>{{ $u['display_name'] ?? ($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? '') }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-employee_number">{!! __('employee_number') !!}</label>
            <input type="text" id="hr-employee_number" name="employee_number" class="form-control" value="{!! $v('employee_number') !!}">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="hr-employee_name">{!! __('employee_name') !!}</label>
            <input type="text" id="hr-employee_name" name="employee_name" class="form-control" value="{!! $v('employee_name') !!}">
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-birth_date">{!! __('birth_date') !!}</label>
            <input type="date" id="hr-birth_date" name="birth_date" class="form-control" value="{!! $v('birth_date') !!}">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">{!! __('furigana_last_name') !!}</label>
            <input type="text" name="furigana_last_name" class="form-control" value="{!! $v('furigana_last_name') !!}" placeholder="{!! __('furigana_placeholder') !!}">
        </div>
        <div class="form-group">
            <label class="form-label">{!! __('furigana_first_name') !!}</label>
            <input type="text" name="furigana_first_name" class="form-control" value="{!! $v('furigana_first_name') !!}" placeholder="{!! __('furigana_placeholder') !!}">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="hr-gender">{!! __('gender') !!}</label>
            <select id="hr-gender" name="gender" class="form-control">
                <option value="">— {!! __('select') !!} —</option>
                <option value="male" {!! ($report['gender'] ?? '') === 'male' ? 'selected' : '' !!}>{!! __('male') !!}</option>
                <option value="female" {!! ($report['gender'] ?? '') === 'female' ? 'selected' : '' !!}>{!! __('female') !!}</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-tax_classification">{!! __('tax_classification') !!}</label>
            <select id="hr-tax_classification" name="tax_classification" class="form-control">
                <option value="">— {!! __('select') !!} —</option>
                <option value="kou" {!! ($report['tax_classification'] ?? '') === 'kou' ? 'selected' : '' !!}>{!! __('hiring_report_tax_kou') !!}</option>
                <option value="otsu" {!! ($report['tax_classification'] ?? '') === 'otsu' ? 'selected' : '' !!}>{!! __('hiring_report_tax_otsu') !!}</option>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="hr-education">{!! __('education') !!}</label>
            <input type="text" id="hr-education" name="education" class="form-control" value="{!! $v('education') !!}">
        </div>
    </div>
    @php
    $identityBody = ob_get_clean();
    echo Card::make()->header(__('hiring_report_section_identity'))->body($identityBody)->render();

    ob_start();
    @endphp
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="hr-postal_code">{!! __('postal_code') !!}</label>
            <input type="text" id="hr-postal_code" name="postal_code" class="form-control" value="{!! $v('postal_code') !!}">
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-phone">{!! __('phone') !!}</label>
            <input type="text" id="hr-phone" name="phone" class="form-control" value="{!! $v('phone') !!}">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="hr-mobile_phone">{!! __('mobile_phone') !!}</label>
            <input type="text" id="hr-mobile_phone" name="mobile_phone" class="form-control" value="{!! $v('mobile_phone') !!}">
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-email">{!! __('email') !!}</label>
            <input type="email" id="hr-email" name="email" class="form-control" value="{!! $v('email') !!}">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="hr-address">{!! __('address') !!}</label>
        <textarea id="hr-address" name="address" class="form-control" rows="3">{!! $v('address') !!}</textarea>
    </div>
    @php
    $contactBody = ob_get_clean();
    echo Card::make()->header(__('hiring_report_section_contact'))->body($contactBody)->render();

    ob_start();
    @endphp
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="hr-guarantor_name">{!! __('guarantor_name') !!}</label>
            <input type="text" id="hr-guarantor_name" name="guarantor_name" class="form-control" value="{!! $v('guarantor_name') !!}">
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-guarantor_phone">{!! __('guarantor_phone') !!}</label>
            <input type="text" id="hr-guarantor_phone" name="guarantor_phone" class="form-control" value="{!! $v('guarantor_phone') !!}">
        </div>
    </div>
    @php
    $guarantorBody = ob_get_clean();
    echo Card::make()->header(__('hiring_report_section_guarantor'))->body($guarantorBody)->render();

    ob_start();
    @endphp
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="hr-store_name">{!! __('hiring_report_store_name') !!}</label>
            <input type="text" id="hr-store_name" name="store_name" class="form-control" value="{{ $report['store_name'] ?? $store['name'] ?? '' }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="hr-hired_by">{!! __('hiring_report_hired_by') !!}</label>
            <input type="text" id="hr-hired_by" name="hired_by" class="form-control" value="{!! $v('hired_by') !!}">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="hr-hire_date">{!! __('hiring_report_hire_date') !!}</label>
            <input type="date" id="hr-hire_date" name="hire_date" class="form-control" value="{!! $v('hire_date') !!}">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="hr-notes">{!! __('notes') !!}</label>
        <textarea id="hr-notes" name="notes" class="form-control" rows="4">{!! $v('notes') !!}</textarea>
    </div>
    @php
    $employmentBody = ob_get_clean();
    echo Card::make()->header(__('hiring_report_section_employment'))->body($employmentBody)->render();
    @endphp

    <div class="form-actions">
        {!! Button::make($mode === 'edit' ? __('save') : __('hiring_report_new_hiring_report'))->primary()->submit()->render() !!}        <a href="{{ back_url($base) }}" class="btn btn--ghost">{!! __('cancel') !!}</a>
    </div>
</form>
