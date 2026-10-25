@php
/**
 * @var string                          $title
 * @var list<array<string, mixed>>      $stores magasins consultables (planning_check_enabled)
 */
@endphp
@includeInScope('planning-check::_assets')
<div class="page-header">
    <h2 class="page-header__title">{{ $title }}</h2>
</div>

@if ($stores === [])
    <div class="card"><div class="card-body text-muted">{!! __('planning_check_no_store') !!}</div></div>
@else
    <div class="card">
        <table class="data-table">
            <tbody>
            @foreach ($stores as $_s)
                <tr>
                    <td><a href="{{ route_url('admin.planning_check.history', ['id' => (int) $_s['id']]) }}">{{ (string) $_s['name'] }}</a></td>
                    <td class="text-muted text-sm">{!! $_s['planning_check_enabled'] ? __('planning_check_state_on') : __('planning_check_state_off') !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
