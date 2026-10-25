@php
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;

/** @var array[] $my_shifts      shifts futurs de l'employé */
/** @var array[] $colleagues     utilisateurs du même store */
/** @var int     $target_id      collègue sélectionné (0 = aucun) */
/** @var array[] $target_shifts  shifts futurs du collègue sélectionné */
/** @var array   $types_map      id → shift_type */

function swapLabel(array $shift, array $types_map): string {
    $tid   = (int)($shift['shift_type_id'] ?? 0);
    $type  = $types_map[$tid] ?? null;
    $name  = $type['name'] ?? 'Shift';
    $start = substr($shift['start_time'] ?? '', 0, 5);
    $end   = substr($shift['end_time'] ?? '', 0, 5);
    return htmlspecialchars($shift['shift_date'] ?? '') . ' — ' . htmlspecialchars($name) . ' ' . $start . '–' . $end;
}
@endphp
@if ($shiftSwapCss = bundle_asset('shift-swap', 'css/shift-swap.css'))
<link rel="stylesheet" href="{!! $shiftSwapCss !!}">
@endif

<div class="page-header">
    <h2 class="page-header__title">{!! __('shift_swap_request_swap') !!}</h2>
    <div class="page-header__actions">
        <a href="{!! back_url(route_url('employee.swaps')) !!}" class="btn btn--ghost btn--sm">← {!! __('back') !!}</a>
    </div>
</div>

<div class="card">
    <div class="card-body card-body--narrow">

        <!-- Étape 1 : choisir un collègue (rechargement GET) -->
        <div class="mb-md">
            <label class="form-label">{!! __('shift_swap_step_1_choose_colleague') !!}</label>
            <form method="GET" action="{!! route_url('employee.swaps.create') !!}" class="swap-form-row">
                <select name="target_id" class="form-control" data-submit-on-change>
                    <option value="0">— {!! __('select') !!} —</option>
                    @foreach ($colleagues as $c)
                        <option value="{!! (int)$c['id'] !!}" {!! (int)$c['id'] === $target_id ? 'selected' : '' !!}>
                            {{ trim(($c['last_name'] ?? '') . ' ' . ($c['first_name'] ?? '')) ?: ($c['email'] ?? '#' . $c['id']) }}                        </option>
                    @endforeach
                </select>
                {!! Button::make('OK')->ghost()->sm()->submit()->render() !!}            </form>
        </div>

        @if ($target_id > 0)
        <!-- Étape 2 : soumettre l'échange -->
        <form method="POST" action="{!! route_url('employee.swaps.create') !!}">
            {!! csrf_field() !!}            <input type="hidden" name="target_id" value="{!! $target_id !!}">

            <div class="form-group">
                <label class="form-label" for="my_shift">{!! __('shift_swap_step_2_my_shift') !!}</label>
                @if (empty($my_shifts))
                    <p class="text-muted text-sm">{!! __('shift_swap_no_future_shift_available') !!}</p>
                @else
                    <select id="my_shift" name="requester_shift_id" class="form-control" required>
                        <option value="">— {!! __('select') !!} —</option>
                        @foreach ($my_shifts as $s)
                            <option value="{!! (int)$s['id'] !!}">{!! swapLabel($s, $types_map) !!}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="form-group">
                <label class="form-label" for="target_shift">{!! __('shift_swap_step_3_his_shift') !!}</label>
                @if (empty($target_shifts))
                    <p class="text-muted text-sm">{!! __('shift_swap_no_future_shift_colleague') !!}</p>
                @else
                    <select id="target_shift" name="target_shift_id" class="form-control" required>
                        <option value="">— {!! __('select') !!} —</option>
                        @foreach ($target_shifts as $s)
                            <option value="{!! (int)$s['id'] !!}">{!! swapLabel($s, $types_map) !!}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="form-group">
                <label class="form-label" for="swap_reason">{!! __('reason') !!} ({!! __('shift_swap_optional') ?? 'optionnel' !!})</label>
                <input id="swap_reason" type="text" name="reason" class="form-control" placeholder="{!! __('shift_swap_swap_reason_placeholder') !!}">
            </div>

            @php if (!empty($my_shifts) && !empty($target_shifts)):
                echo Button::make(__('send') ?? 'Envoyer')->primary()->submit()->render();
            endif; @endphp
        </form>
        @else
            <p class="text-muted text-sm">{!! __('shift_swap_select_colleague_to_continue') !!}</p>
        @endif

    </div>
</div>
