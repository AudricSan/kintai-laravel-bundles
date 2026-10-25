@php
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Flash;

/** @var array[] $employees        employés des stores gérés */
/** @var int     $requester_id */
/** @var int     $target_id */
/** @var array[] $requester_shifts shifts futurs du demandeur (sans conflit) */
/** @var array[] $target_shifts    shifts futurs du collègue (sans conflit) */
/** @var array   $stores_map       id → nom du store */

if (!function_exists('kintai_admin_swap_employee_label')) {
    function kintai_admin_swap_employee_label(array $u): string
    {
        return trim(($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? '')) ?: ($u['display_name'] ?? $u['email'] ?? '#' . $u['id']);
    }
}

if (!function_exists('kintai_admin_swap_shift_label')) {
    function kintai_admin_swap_shift_label(array $shift, array $stores_map): string
    {
        $start = substr($shift['start_time'] ?? '', 0, 5);
        $end   = substr($shift['end_time'] ?? '', 0, 5);
        $store = $stores_map[(int) ($shift['store_id'] ?? 0)] ?? '';
        return htmlspecialchars($shift['shift_date'] ?? '') . ' — ' . $start . '–' . $end . ($store ? ' (' . htmlspecialchars($store) . ')' : '');
    }
}

echo Flash::fromQuery('error', ['swap_conflict' => __('shift_swap_swap_conflict_error')])->render();
@endphp
@if ($shiftSwapCss = bundle_asset('shift-swap', 'css/shift-swap.css'))
<link rel="stylesheet" href="{!! $shiftSwapCss !!}">
@endif

<div class="page-header">
    <h2 class="page-header__title">{!! __('shift_swap_create_swap') !!}</h2>
    <div class="page-header__actions">
        <a href="{!! back_url($BASE_URL . '/admin/swap-requests') !!}" class="btn btn--ghost btn--sm">← {!! __('back') !!}</a>
    </div>
</div>

<div class="card">
    <div class="card-body card-body--narrow">

        <!-- Étape 1 : choisir les deux employés (rechargement GET) -->
        <div class="mb-md">
            <label class="form-label">{!! __('shift_swap_step_1_choose_employees') !!}</label>
            <form method="GET" action="{!! $BASE_URL !!}/admin/swap-requests/create" class="swap-form-row">
                <select name="requester_id" class="form-control" data-submit-on-change>
                    <option value="0">— {!! __('shift_swap_requester') !!} —</option>
                    @foreach ($employees as $u)
                        <option value="{!! (int) $u['id'] !!}" {!! (int) $u['id'] === $requester_id ? 'selected' : '' !!}>
                            {{ kintai_admin_swap_employee_label($u) }}                        </option>
                    @endforeach
                </select>
                <select name="target_id" class="form-control" data-submit-on-change>
                    <option value="0">— {!! __('shift_swap_target') !!} —</option>
                    @foreach ($employees as $u)
                        <option value="{!! (int) $u['id'] !!}" {!! (int) $u['id'] === $target_id ? 'selected' : '' !!}>
                            {{ kintai_admin_swap_employee_label($u) }}                        </option>
                    @endforeach
                </select>
                {!! Button::make('OK')->ghost()->sm()->submit()->render() !!}            </form>
        </div>

        @if ($requester_id > 0 && $target_id > 0 && $requester_id !== $target_id)
        <!-- Étape 2 : appliquer l'échange -->
        <p class="text-muted text-sm mb-sm">{!! __('shift_swap_swap_conflict_hint') !!}</p>
        <form method="POST" action="{!! $BASE_URL !!}/admin/swap-requests/create">
            {!! csrf_field() !!}            <input type="hidden" name="requester_id" value="{!! $requester_id !!}">
            <input type="hidden" name="target_id" value="{!! $target_id !!}">

            <div class="form-group">
                <label class="form-label" for="requester_shift">{!! __('shift_swap_step_2_requester_shift') !!}</label>
                @if (empty($requester_shifts))
                    <p class="text-muted text-sm">{!! __('shift_swap_no_future_shift_available') !!}</p>
                @else
                    <select id="requester_shift" name="requester_shift_id" class="form-control" required>
                        <option value="">— {!! __('select') !!} —</option>
                        @foreach ($requester_shifts as $s)
                            <option value="{!! (int) $s['id'] !!}">{!! kintai_admin_swap_shift_label($s, $stores_map) !!}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="form-group">
                <label class="form-label" for="target_shift">{!! __('shift_swap_step_3_target_shift') !!}</label>
                @if (empty($target_shifts))
                    <p class="text-muted text-sm">{!! __('shift_swap_no_future_shift_available') !!}</p>
                @else
                    <select id="target_shift" name="target_shift_id" class="form-control" required>
                        <option value="">— {!! __('select') !!} —</option>
                        @foreach ($target_shifts as $s)
                            <option value="{!! (int) $s['id'] !!}">{!! kintai_admin_swap_shift_label($s, $stores_map) !!}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="form-group">
                <label class="form-label" for="swap_reason">{!! __('reason') !!}</label>
                <input id="swap_reason" type="text" name="reason" class="form-control" placeholder="{!! __('shift_swap_swap_reason_placeholder') !!}">
            </div>

            @php if (!empty($requester_shifts) && !empty($target_shifts)):
                echo Button::make(__('shift_swap_create_swap'))->primary()->submit()->render();
            endif; @endphp
        </form>
        @else
            <p class="text-muted text-sm">{!! __('shift_swap_select_employees_to_continue') !!}</p>
        @endif

    </div>
</div>
