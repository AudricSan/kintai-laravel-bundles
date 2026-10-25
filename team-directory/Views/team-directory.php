<?php
use kintai\Contract\View\Components\EmptyState;

/**
 * @var array $colleagues  Liste de ['user' => array, 'store_ids' => int[]]
 * @var array $stores_map  store_id => nom
 */
?>
<?php if ($teamDirectoryCss = bundle_asset('team-directory', 'css/team-directory.css')): ?>
<link rel="stylesheet" href="<?= $teamDirectoryCss ?>">
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('team_directory') ?></h2>
</div>

<?php if (empty($colleagues)): ?>
    <?= EmptyState::make(__('team_directory_empty'))->icon('👥')->render() ?>
<?php else: ?>
<div class="team-directory-grid">
    <?php foreach ($colleagues as $entry):
        $u = $entry['user'];
        $storeNames = array_map(fn($sid) => $stores_map[$sid] ?? ('#' . $sid), $entry['store_ids']);
        $name = trim(($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? '')) ?: ($u['display_name'] ?? '');
        $initials = htmlspecialchars(strtoupper(
            mb_substr((string) ($u['last_name'] ?? ''), 0, 1) . mb_substr((string) ($u['first_name'] ?? ''), 0, 1)
        ) ?: '··');
    ?>
    <a href="<?= route_url('team.show', ['id' => (int) $u['id']]) ?>" class="team-card">
        <div class="team-card__body">
            <?php if (!empty($u['avatar_path'])): ?>
                <img src="<?= route_url('user.avatar', ['user_id' => (int) $u['id']]) ?>" alt="" class="team-card__avatar">
            <?php else: ?>
                <span class="avatar-chip team-card__avatar team-card__avatar--fallback <?= style_class(['--chip-bg' => $u['color'] ?? '#3B82F6']) ?>"><?= $initials ?></span>
            <?php endif; ?>
            <div class="team-card__name"><?= htmlspecialchars($name) ?></div>
            <?php if (!empty($storeNames)): ?>
                <div class="team-card__stores"><?= htmlspecialchars(implode(' · ', $storeNames)) ?></div>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
