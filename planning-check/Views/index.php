<?php
/**
 * @var string                          $title
 * @var list<array<string, mixed>>      $stores magasins consultables (planning_check_enabled)
 */
?>
<?php include __DIR__ . "/_assets.php"; ?>
<div class="page-header">
    <h2 class="page-header__title"><?= htmlspecialchars($title) ?></h2>
</div>

<?php if ($stores === []): ?>
    <div class="card"><div class="card-body text-muted"><?= __('planning_check_no_store') ?></div></div>
<?php else: ?>
    <div class="card">
        <table class="data-table">
            <tbody>
            <?php foreach ($stores as $_s): ?>
                <tr>
                    <td><a href="<?= htmlspecialchars(route_url('admin.planning_check.history', ['id' => (int) $_s['id']])) ?>"><?= htmlspecialchars((string) $_s['name']) ?></a></td>
                    <td class="text-muted text-sm"><?= $_s['planning_check_enabled'] ? __('planning_check_state_on') : __('planning_check_state_off') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
