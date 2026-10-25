<?php
/**
 * Widget « Échanges en attente » du tableau de bord gérant (ShiftSwapBundle::dashboardWidgets(), contrat 1.4), repris
 * du Core.
 *
 * @var list<array<string, mixed>> $pending_swaps
 * @var array<int, string>         $users_map
 * @var string                     $BASE_URL
 */
?>
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('shift_swap_pending_swaps') ?></span>
        <a href="<?= route_url('admin.swap_requests') ?>" class="card-header-link"><?= __('view_all') ?></a>
    </div>
    <?php if (empty($pending_swaps)): ?>
        <div class="empty-state"><?= __('shift_swap_no_pending_swap') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr><th>#</th><th><?= __('shift_swap_requester') ?></th><th><?= __('shift_swap_target') ?></th><th><?= __('shifts') ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_swaps as $swap): ?>
                        <tr>
                            <td data-label="#"><?= (int) $swap['id'] ?></td>
                            <td data-label="<?= htmlspecialchars(__('shift_swap_requester')) ?>"><a href="<?= $BASE_URL ?>/admin/users/<?= employee_segment((int)($swap['requester_id'] ?? 0)) ?>/edit"><?= htmlspecialchars($users_map[(int)($swap['requester_id'] ?? 0)] ?? ('User #' . (int)($swap['requester_id'] ?? 0))) ?></a></td>
                            <td data-label="<?= htmlspecialchars(__('shift_swap_target')) ?>"><a href="<?= $BASE_URL ?>/admin/users/<?= employee_segment((int)($swap['target_user_id'] ?? 0)) ?>/edit"><?= htmlspecialchars($users_map[(int)($swap['target_user_id'] ?? 0)] ?? ('User #' . (int)($swap['target_user_id'] ?? 0))) ?></a></td>
                            <td data-label="<?= htmlspecialchars(__('shifts')) ?>">#<?= (int) ($swap['requester_shift_id'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
