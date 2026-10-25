<?php
/**
 * Widget « Pointages actifs » du tableau de bord gérant (TimeclockBundle::dashboardWidgets(), contrat 1.4), repris du
 * Core.
 *
 * @var list<array<string, mixed>> $active_clocks_now pointages du jour sans sortie (user_name, store_name, clock_in_time)
 */
?>
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('timeclock_widget_timeclocks_today') ?></span>
        <a href="<?= route_url('admin.timeclocks') ?>" class="card-header-link"><?= __('view_all') ?></a>
    </div>
    <?php if (empty($active_clocks_now)): ?>
        <div class="empty-state"><?= __('timeclock_no_active_timeclock') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr>
                        <th><?= __('user') ?></th>
                        <th><?= __('store') ?></th>
                        <th><?= __('timeclock_clock_in') ?></th>
                        <th><?= __('timeclock_duration') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_clocks_now as $tc): ?>
                        <?php
                        $clockInRaw = $tc['clock_in_time'] ?? '';
                        $clockInFmt = strlen($clockInRaw) >= 16 ? substr($clockInRaw, 11, 5) : $clockInRaw;
                        $durationMin = 0;
                        if ($clockInRaw !== '') {
                            try {
                                $dtIn = new DateTimeImmutable($clockInRaw);
                                $durationMin = (int) floor((time() - $dtIn->getTimestamp()) / 60);
                            } catch (\Exception) {}
                        }
                        $dh = intdiv($durationMin, 60);
                        $dm = $durationMin % 60;
                        ?>
                        <tr>
                            <td data-label="<?= htmlspecialchars(__('user')) ?>"><?= htmlspecialchars($tc['user_name']) ?></td>
                            <td data-label="<?= htmlspecialchars(__('store')) ?>"><?= htmlspecialchars($tc['store_name']) ?></td>
                            <td data-label="<?= htmlspecialchars(__('timeclock_clock_in')) ?>" class="td-mono"><?= htmlspecialchars($clockInFmt) ?></td>
                            <td data-label="<?= htmlspecialchars(__('timeclock_duration')) ?>" class="td-nowrap"><?= $dh ?>h<?= str_pad((string)$dm, 2, '0', STR_PAD_LEFT) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
