@php
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;
use kintai\Contract\View\Components\Flash;

/**
 * @var array  $store
 * @var array  $users
 * @var string $mode
 * @var array  $report
 * @var array  $managers
 */

$storeId = (int) $store['id'];
$reportId = (int) ($report['id'] ?? 0);
$base = $BASE_URL . '/admin/stores/' . $storeId . '/reports/resignation';
$action = $mode === 'edit'
    ? $base . '/' . $reportId . '/edit'
    : $base . '/create';

echo Flash::fromQuery('success', [
    'created' => __('operation_success'),
    'updated' => __('operation_success'),
])->render();
@endphp
<div class="page-header">
    <h2 class="page-header__title">{!! $mode === 'edit' ? __('resignation_report_edit_resignation_report') : __('resignation_report_new_resignation_report') !!}</h2>
    <div class="page-header__actions">
        <a href="{!! back_url($base) !!}" class="btn btn--ghost">← {!! __('back') !!}</a>
    </div>
</div>

@php
ob_start();
echo '<form method="POST" action="' . htmlspecialchars($action) . '">';
echo csrf_field();

echo '<div class="form-group">';
echo '<label class="form-label" for="f-user_id">' . __('user') . '</label>';
echo '<select id="f-user_id" name="user_id" class="form-control" data-on-change="rrFillFromUser" data-args="[&quot;@this&quot;]">';
echo '<option value="">— ' . __('select') . ' —</option>';
$selectedUserId = (int) ($report['user_id'] ?? 0);
foreach ($users as $u) {
    $uid = (int) $u['id'];
    $uName = trim(($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? '')) ?: ($u['display_name'] ?? $u['email'] ?? '#' . $uid);
    $sel = $uid === $selectedUserId ? ' selected' : '';
    echo '<option value="' . $uid . '"' . $sel
        . ' data-number="' . htmlspecialchars($u['employee_code'] ?? '') . '"'
        . ' data-name="' . htmlspecialchars($uName) . '"'
        . '>' . htmlspecialchars($uName) . '</option>';
}
echo '</select></div>';

echo '<div class="form-group">';
echo '<label class="form-label" for="f-employee_number">' . __('employee_number') . '</label>';
echo '<input id="f-employee_number" type="text" name="employee_number" class="form-control" value="' . htmlspecialchars($report['employee_number'] ?? '') . '">';
echo '</div>';

echo '<div class="form-group">';
echo '<label class="form-label" for="f-employee_name">' . __('employee_name') . '</label>';
echo '<input id="f-employee_name" type="text" name="employee_name" class="form-control" value="' . htmlspecialchars($report['employee_name'] ?? '') . '">';
echo '</div>';

echo '<div class="form-group">';
echo '<label class="form-label" for="f-resignation_date">' . __('resignation_report_resignation_date') . '</label>';
echo '<input id="f-resignation_date" type="date" name="resignation_date" class="form-control" value="' . htmlspecialchars($report['resignation_date'] ?? '') . '">';
echo '<p class="form-hint">' . __('resignation_report_resignation_deactivation_hint') . '</p>';
echo '</div>';

echo '<div class="form-group">';
echo '<label class="form-label" for="f-reason">' . __('reason') . '</label>';
echo '<textarea id="f-reason" name="reason" class="form-control" rows="4">' . htmlspecialchars($report['reason'] ?? '') . '</textarea>';
echo '</div>';

echo '<div class="form-group">';
echo '<label class="form-label" for="f-resignation_notice">' . __('resignation_report_resignation_notice') . '</label>';
echo '<textarea id="f-resignation_notice" name="resignation_notice" class="form-control" rows="4">' . htmlspecialchars($report['resignation_notice'] ?? '') . '</textarea>';
echo '</div>';

echo '<div class="form-group">';
echo '<label class="form-label" for="f-notes">' . __('notes') . '</label>';
echo '<textarea id="f-notes" name="notes" class="form-control" rows="4">' . htmlspecialchars($report['notes'] ?? '') . '</textarea>';
echo '</div>';

echo '<div class="form-group">';
echo '<label class="form-label" for="f-person_in_charge">' . __('resignation_report_person_in_charge') . '</label>';
echo '<select id="f-person_in_charge" name="person_in_charge" class="form-control">';
echo '<option value="">— ' . __('select') . ' —</option>';
$selectedPic = $report['person_in_charge'] ?? '';
foreach ($managers as $m) {
    $mName = trim(($m['last_name'] ?? '') . ' ' . ($m['first_name'] ?? '')) ?: ($m['display_name'] ?? $m['email'] ?? '');
    $sel = $mName === $selectedPic ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($mName) . '"' . $sel . '>' . htmlspecialchars($mName) . '</option>';
}
echo '</select></div>';

echo '<div class="form-actions">';
echo Button::make($mode === 'edit' ? __('save') : __('create'))->primary()->submit()->render();
echo ' <a href="' . back_url($base) . '" class="btn btn--ghost">' . __('cancel') . '</a>';
echo '</div></form>';

echo Card::make()->body(ob_get_clean())->render();
@endphp
<script nonce="{!! function_exists('csp_nonce') ? csp_nonce() : '' !!}">
function rrFillFromUser(select) {
    var opt = select.options[select.selectedIndex];
    document.getElementById('f-employee_number').value = opt.dataset.number || '';
    document.getElementById('f-employee_name').value = opt.dataset.name || '';
}
</script>
