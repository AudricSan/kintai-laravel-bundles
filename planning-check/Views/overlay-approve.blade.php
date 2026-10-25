@php
/**
 * Fenêtre du valideur : « N planning(s) à valider » avec aperçu et boutons Valider / Refuser (avec motif).
 *
 * @var array<string, mixed> $store
 * @var array<string, mixed> $check
 * @var string               $date
 * @var array<string, mixed> $day
 * @var int                  $count
 * @var string               $overlay_title_id
 */
$_id = (int) $store['id'];
$_cid = (int) $check['id'];
$_route = static fn(string $route): string => htmlspecialchars(route_url($route, ['id' => $_id, 'cid' => $_cid]));
@endphp
@includeInScope('planning-check::_assets')
<div class="pc-overlay">
    <div class="pc-overlay__header">
        <h2 id="{{ $overlay_title_id }}" class="pc-overlay__title">{{ __('planning_check_approve_title', ['date' => $date]) }}</h2>
        <p class="text-muted text-sm">
            {{ (string) $store['name'] }}            @if ($count > 1) — {{ __('planning_check_more_to_validate', ['n' => $count - 1]) }}@endif
        </p>
    </div>
    <div class="pc-overlay__body">@includeInScope('planning-check::_shifts-table')</div>
    <div class="pc-overlay__actions">
        <form method="POST" action="{!! $_route('admin.planning_check.approve') !!}">
            {!! csrf_field() !!}            <button type="submit" class="btn btn--primary">{!! __('planning_check_approve_send') !!}</button>
        </form>
        <form method="POST" action="{!! $_route('admin.planning_check.reject') !!}" class="pc-reject">
            {!! csrf_field() !!}            <input type="text" name="reason" class="form-control" maxlength="500" required placeholder="{{ __('planning_check_reject_reason') }}"
                   aria-label="{{ __('planning_check_reject_reason') }}">
            <button type="submit" class="btn btn--ghost">{!! __('planning_check_reject') !!}</button>
        </form>
        <button type="button" class="btn btn--ghost" data-overlay-close>{!! __('planning_check_later') !!}</button>
    </div>
</div>
