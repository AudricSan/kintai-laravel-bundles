@php
use kintai\Contract\View\Components\Button;

/** @var array[] $users */
/** @var string  $today */

$today = $today ?? date('Y-m-d');

$typeLabels = [
    'vacation' => __('vacation'), 'sick' => __('sick'), 'personal' => __('personal'),
    'unpaid' => __('unpaid'), 'other' => __('other'),
];
@endphp
@if ($timeoffCss = bundle_asset('timeoff', 'css/timeoff.css'))
<link rel="stylesheet" href="{!! $timeoffCss !!}">
@endif
<div class="page-header">
    <h2 class="page-header__title">{!! __('timeoff_create_timeoff') !!}</h2>
    <div class="page-header__actions">
        <a href="{!! back_url($BASE_URL . '/admin/timeoff') !!}" class="btn btn--ghost">← {!! __('back') !!}</a>
    </div>
</div>

<div class="card card--filters mb-sm">
    <form method="POST" action="{!! $BASE_URL !!}/admin/timeoff/create" class="timeoff-form">
        {!! csrf_field() !!}        <div class="timeoff-form__grow">
            <label class="form-label" for="to_user">{!! __('user') !!}</label>
            <select id="to_user" name="user_id" class="form-control" required>
                <option value=""></option>
                @foreach ($users as $u)
                    @php $name = trim(($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? '')) ?: ($u['display_name'] ?? $u['email'] ?? ('#' . $u['id'])); @endphp
                    <option value="{!! (int) $u['id'] !!}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="timeoff-form__date">
            <label class="form-label" for="to_type">{!! __('type') !!}</label>
            <select id="to_type" name="type" class="form-control" required>
                @foreach ($typeLabels as $val => $label)
                    <option value="{!! $val !!}">{!! $label !!}</option>
                @endforeach
            </select>
        </div>
        <div class="timeoff-form__short">
            <label class="form-label" for="to_start">{!! __('start') !!}</label>
            <input id="to_start" type="date" name="start_date" class="form-control" required value="{!! $today !!}">
        </div>
        <div class="timeoff-form__short">
            <label class="form-label" for="to_end">{!! __('end') !!}</label>
            <input id="to_end" type="date" name="end_date" class="form-control" value="{!! $today !!}">
        </div>
        <div class="timeoff-form__grow">
            <label class="form-label" for="to_reason">{!! __('reason') !!}</label>
            <input id="to_reason" type="text" name="reason" class="form-control" placeholder="{!! __('timeoff_reason_placeholder') !!}">
        </div>
        <div class="form-actions">
            {!! Button::make(__('timeoff_create_timeoff'))->primary()->submit()->render() !!}        </div>
    </form>
</div>
