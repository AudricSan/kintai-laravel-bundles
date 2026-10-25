<?php
/**
 * Widget « Congés en attente » du tableau de bord gérant (TimeOffBundle::dashboardWidgets(), contrat 1.4), repris du
 * Core : carte pleine largeur comme les autres widgets de bundles (le Core la plaçait à côté des échanges).
 *
 * @var list<array<string, mixed>> $pending_timeoff
 * @var array<int, string>         $users_map
 */
?>
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('timeoff_pending_timeoff') ?></span>
        <a href="<?= route_url('admin.timeoff') ?>" class="card-header-link"><?= __('view_all') ?></a>
    </div>
    <?php if (empty($pending_timeoff)): ?>
        <div class="empty-state"><?= __('timeoff_no_pending_request') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr><th>#</th><th><?= __('user') ?></th><th><?= __('type') ?></th><th><?= __('from') ?></th><th><?= __('to') ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_timeoff as $req): ?>
                        <tr>
                            <td data-label="#"><?= (int) $req['id'] ?></td>
                            <td data-label="<?= htmlspecialchars(__('user')) ?>"><a href="<?= base_url() ?>/admin/users/<?= employee_segment((int)($req['user_id'] ?? 0)) ?>/edit"><?= htmlspecialchars($users_map[(int)($req['user_id'] ?? 0)] ?? ('User #' . (int)($req['user_id'] ?? 0))) ?></a></td>
                            <td data-label="<?= htmlspecialchars(__('type')) ?>"><span class="badge badge--pending"><?= htmlspecialchars((string) ($req['type'] ?? '—')) ?></span></td>
                            <td data-label="<?= htmlspecialchars(__('from')) ?>"><?= htmlspecialchars((string) ($req['start_date'] ?? '—')) ?></td>
                            <td data-label="<?= htmlspecialchars(__('to')) ?>"><?= htmlspecialchars((string) ($req['end_date'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

