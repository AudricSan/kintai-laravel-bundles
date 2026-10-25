@php
/**
 * Fenêtre du manager : « Les shifts de demain sont-ils corrects ? » (PlanningCheckOverlayResolver, genre « submit »).
 *
 * @var array<string, mixed> $store
 * @var string               $date
 * @var bool                 $late
 * @var array<string, mixed> $day
 * @var array<string, mixed> $check
 * @var string               $overlay_title_id
 */
$_id = (int) $store['id'];
$_post = static fn(string $route): string => htmlspecialchars(route_url($route, ['id' => $_id]));
$_empty = $day['rows'] === [];
@endphp
@includeInScope('planning-check::_assets')
<div class="pc-overlay">
    <div class="pc-overlay__header">
        <h2 id="{{ $overlay_title_id }}" class="pc-overlay__title">{{ __('planning_check_ask_title', ['date' => $date]) }}</h2>
        <p class="text-muted text-sm">{{ (string) $store['name'] }}{!! $late ? ' — <strong class="pc-late">' . htmlspecialchars(__('planning_check_late')) . '</strong>' : '' !!}</p>
    </div>
    @if ($check['status'] === 'rejected')
        <div class="alert alert--warning mb-sm"><strong>{!! __('planning_check_rejected_notice') !!}</strong> {{ (string) ($check['reject_reason'] ?? '') }}</div>
    @endif
    <div class="pc-overlay__body">@includeInScope('planning-check::_shifts-table')</div>
    <div class="pc-overlay__actions">
        <form method="POST" action="{!! $_post('admin.planning_check.submit') !!}">
            {!! csrf_field() !!}            <input type="hidden" name="date" value="{{ $date }}">
            @if ($_empty)
                <label class="check-label"><input type="checkbox" name="confirm_empty" value="1" required> <span>{!! __('planning_check_confirm_empty') !!}</span></label>
            @endif
            <button type="submit" class="btn btn--primary">{!! __('planning_check_yes') !!}</button>
        </form>
        <form method="POST" action="{!! $_post('admin.planning_check.edit') !!}">
            {!! csrf_field() !!}            <input type="hidden" name="date" value="{{ $date }}">
            <button type="submit" class="btn btn--ghost">{!! __('planning_check_no_edit') !!}</button>
        </form>
        <button type="button" class="btn btn--ghost" data-overlay-close>{!! __('planning_check_later') !!}</button>
    </div>
</div>
