@php
/**
 * Historique des validations d'un magasin.
 *
 * @var string                     $title
 * @var array<string, mixed>       $store
 * @var list<array<string, mixed>> $checks
 * @var array<int, string>         $names
 * @var int                        $highlight
 * @var bool                       $can_approve
 * @var bool                       $can_settings
 * @var array<string, mixed>       $settings
 */
use kintai\Contract\View\Components\Flash;

$_id = (int) $store['id'];
$_success = ['submitted' => __('planning_check_flash_submitted'), 'validated' => __('planning_check_flash_validated'), 'rejected' => __('planning_check_flash_rejected'), 'resent' => __('planning_check_flash_resent'), 'sent_now' => __('planning_check_flash_sent_now'), 'recheck' => __('planning_check_flash_recheck')];
$_labels = ['pending' => 'planning_check_status_pending', 'submitted' => 'planning_check_status_submitted', 'validated' => 'planning_check_status_validated', 'rejected' => 'planning_check_status_rejected', 'escalated' => 'planning_check_status_escalated'];
$_errorCode = (string) ($_GET['pc_error'] ?? '');
@endphp
@includeInScope('planning-check::_assets')
<div class="page-header">
    <h2 class="page-header__title">{{ $title }} — {{ (string) $store['name'] }}</h2>
    <div class="page-header__actions">
        @if ($can_settings)
            <a href="{{ route_url('admin.planning_check.settings', ['id' => $_id]) }}" class="btn btn--ghost btn--sm">{!! __('planning_check_settings_title') !!}</a>
        @endif
    </div>
</div>

{!! Flash::fromQuery('pc', $_success)->render() !!}@if ($_errorCode !== '')
    <div class="alert alert--danger mb-sm">{{ __('planning_check_err_' . preg_replace('/[^a-z_]/', '', $_errorCode)) }}</div>
@endif
@if (empty($settings['enabled']))
    <div class="alert alert--info mb-sm">{!! __('planning_check_disabled_hint') !!}</div>
@endif

@if ((!empty($can_approve) || !empty($can_submit)) && !empty($settings['enabled']) && !empty($tomorrow))
    @php
    // Planning de demain : revérifier (force la fenêtre habituelle) ou, pour un valideur, envoyer directement.
    $_tDate   = (string) $tomorrow['date'];
    $_tCheck  = $tomorrow['check'];
    $_tStatus = $_tCheck['status'] ?? 'pending';
    $_tCount  = (int) ($tomorrow['count'] ?? 0);
    $_tConfirm = $_tCount === 0 ? __('planning_check_send_now_confirm_empty') : __('planning_check_send_now_confirm');
    @endphp
    <div class="card mb-sm">
        <div class="card-body flex-row gap-sm">
            <div>
                <strong>{!! __('planning_check_tomorrow_title') !!} — {{ $_tDate }}</strong>
                <div class="text-muted text-sm">
                    {!! __($_labels[$_tStatus] ?? 'planning_check_status_pending') !!} · {{ (string) $_tCount }} {!! __('planning_check_tomorrow_shifts') !!}                </div>
            </div>
            @if (!empty($can_submit))
                @php // Rouvre la vérification (même soumise ou validée) et force la fenêtre habituelle : les shifts de demain, à vérifier de nouveau. @endphp
                <form method="POST" action="{{ route_url('admin.planning_check.recheck', ['id' => $_id]) }}" class="inline-form"
                      {!! in_array($_tStatus, ['submitted', 'validated'], true) ? 'data-confirm="' . htmlspecialchars(__('planning_check_recheck_confirm'), ENT_QUOTES) . '"' : '' !!}>
                    {!! csrf_field() !!}                    <input type="hidden" name="date" value="{{ $_tDate }}">
                    <button type="submit" class="btn btn--primary btn--sm">{!! __('planning_check_recheck') !!}</button>
                </form>
            @endif
            @if (!empty($can_approve))
                <form method="POST" action="{{ route_url('admin.planning_check.send_now', ['id' => $_id]) }}" class="inline-form"
                      data-confirm="{{ $_tConfirm }}">
                    {!! csrf_field() !!}                    <input type="hidden" name="date" value="{{ $_tDate }}">
                    @if ($_tCount === 0)<input type="hidden" name="confirm_empty" value="1">@endif
                    <button type="submit" class="btn btn--ghost btn--sm">{!! $_tStatus === 'validated' ? __('planning_check_send_now_again') : __('planning_check_send_now') !!}</button>
                </form>
            @endif
        </div>
        @if ($_tCount === 0)<div class="card-body text-muted text-sm">{!! __('planning_check_tomorrow_empty_hint') !!}</div>@endif
    </div>
@endif

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>{!! __('planning_check_col_date') !!}</th>
                <th>{!! __('planning_check_col_status') !!}</th>
                <th>{!! __('planning_check_col_submitted') !!}</th>
                <th>{!! __('planning_check_col_validated') !!}</th>
                <th>{!! __('planning_check_col_send') !!}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @if ($checks === [])
            <tr><td colspan="6" class="text-muted">{!! __('planning_check_history_empty') !!}</td></tr>
        @endif
        @foreach ($checks as $_c)
            @php $_cid = (int) $_c['id']; @endphp
            <tr{!! $_cid === $highlight ? ' class="tr--highlight"' : '' !!}>
                <td>{{ (string) $_c['target_date'] }}</td>
                <td>
                    {!! __($_labels[$_c['status']] ?? 'planning_check_status_pending') !!}                    @if ($_c['status'] === 'rejected' && !empty($_c['reject_reason']))
                        <div class="text-muted text-sm">{{ (string) $_c['reject_reason'] }}</div>
                    @endif
                </td>
                <td class="text-sm">{!! $_c['submitted_by'] !== null ? htmlspecialchars(($names[$_c['submitted_by']] ?? '—') . ' · ' . substr((string) $_c['submitted_at'], 0, 16)) : '—' !!}</td>
                <td class="text-sm">{!! $_c['validated_by'] !== null ? htmlspecialchars(($names[$_c['validated_by']] ?? '—') . ' · ' . substr((string) $_c['validated_at'], 0, 16)) : '—' !!}</td>
                <td class="text-sm">
                    @if ($_c['send_status'] === 'sent')
                        ✓ {{ substr((string) $_c['sent_at'], 0, 16) }}                        @if ($_c['needs_resend'])<span class="badge badge--warning">{!! __('planning_check_changed_since') !!}</span>@endif
                    @elseif ($_c['send_status'] === 'failed')
                        <span class="badge badge--danger">{!! __('planning_check_send_failed') !!}</span>
                    @else—@endif
                </td>
                <td class="text-right">
                    <a href="{{ route_url('admin.planning_check.pdf', ['id' => $_id, 'cid' => $_cid]) }}" class="btn btn--ghost btn--sm">PDF</a>
                    @if ($can_approve && $_c['status'] === 'validated')
                        @php // Changement détecté (ou envoi en échec) : action principale. Sinon, renvoi à la demande d'une version régénérée, après une modification que la détection n'a pas vue. @endphp
                        <form method="POST" action="{{ route_url('admin.planning_check.resend', ['id' => $_id, 'cid' => $_cid]) }}" class="inline-form"
                              {!! $_c['needs_resend'] ? '' : 'data-confirm="' . htmlspecialchars(__('planning_check_resend_new_confirm'), ENT_QUOTES) . '"' !!}>
                            {!! csrf_field() !!}                            @if ($_c['needs_resend'])
                                <button type="submit" class="btn btn--primary btn--sm">{!! $_c['send_status'] === 'failed' ? __('planning_check_retry') : __('planning_check_resend_updated') !!}</button>
                            @else
                                <button type="submit" class="btn btn--ghost btn--sm">{!! __('planning_check_resend_new') !!}</button>
                            @endif
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@php unset($_c, $_cid); @endphp
