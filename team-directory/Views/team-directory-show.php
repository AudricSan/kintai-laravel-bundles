<?php
use kintai\Contract\View\Components\Card;

/**
 * @var array $colleague   Utilisateur consulté
 * @var array $store_ids   int[]
 * @var array $stores_map  store_id => nom
 */
$name = trim(($colleague['last_name'] ?? '') . ' ' . ($colleague['first_name'] ?? '')) ?: ($colleague['display_name'] ?? '');
$initials = htmlspecialchars(strtoupper(
    mb_substr((string) ($colleague['last_name'] ?? ''), 0, 1) . mb_substr((string) ($colleague['first_name'] ?? ''), 0, 1)
) ?: '··');
$storeNames = array_map(fn($sid) => $stores_map[$sid] ?? ('#' . $sid), $store_ids);
?>
<?php if ($teamDirectoryCss = bundle_asset('team-directory', 'css/team-directory.css')): ?>
<link rel="stylesheet" href="<?= $teamDirectoryCss ?>">
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= htmlspecialchars($name) ?></h2>
    <div class="page-header__actions">
        <a href="<?= route_url('team.index') ?>" class="btn btn--ghost">← <?= __('team_directory_back') ?></a>
    </div>
</div>

<?php
ob_start();
?>
<div class="team-profile-header">
    <?php if (!empty($colleague['avatar_path'])): ?>
        <img src="<?= route_url('user.avatar', ['user_id' => (int) $colleague['id']]) ?>" alt="" class="team-profile-avatar">
    <?php else: ?>
        <span class="avatar-chip team-profile-avatar team-profile-avatar--fallback <?= style_class(['--chip-bg' => $colleague['color'] ?? '#3B82F6']) ?>"><?= $initials ?></span>
    <?php endif; ?>
    <div>
        <div class="team-profile-name"><?= htmlspecialchars($name) ?></div>
        <?php if (!empty($storeNames)): ?>
            <div class="text-muted"><?= htmlspecialchars(implode(' · ', $storeNames)) ?></div>
        <?php endif; ?>
    </div>
</div>

<?php
$sharedContact = [];
if (!empty($colleague['share_email']) && !empty($colleague['email'])) {
    $sharedContact[__('email')] = $colleague['email'];
}
if (!empty($colleague['share_phone']) && !empty($colleague['phone'])) {
    $sharedContact[__('phone')] = $colleague['phone'];
}
if (!empty($colleague['share_mobile_phone']) && !empty($colleague['mobile_phone'])) {
    $sharedContact[__('mobile_phone')] = $colleague['mobile_phone'];
}
?>
<?php if (!empty($sharedContact)): ?>
<div class="team-profile-section">
    <h4 class="section-title"><?= __('contact') ?></h4>
    <?php foreach ($sharedContact as $label => $value): ?>
    <p><strong><?= htmlspecialchars($label) ?> :</strong> <?= htmlspecialchars($value) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($colleague['bio'])): ?>
<div class="team-profile-section">
    <h4 class="section-title"><?= __('bio') ?></h4>
    <p><?= nl2br(htmlspecialchars($colleague['bio'])) ?></p>
</div>
<?php endif; ?>

<?php if (!empty($colleague['skills'])): ?>
<div class="team-profile-section">
    <h4 class="section-title"><?= __('skills') ?></h4>
    <p><?= htmlspecialchars($colleague['skills']) ?></p>
</div>
<?php endif; ?>

<?php if (!empty($colleague['languages_spoken'])): ?>
<div class="team-profile-section">
    <h4 class="section-title"><?= __('languages_spoken') ?></h4>
    <p><?= htmlspecialchars($colleague['languages_spoken']) ?></p>
</div>
<?php endif; ?>

<?php if (!empty($colleague['hobbies'])): ?>
<div class="team-profile-section">
    <h4 class="section-title"><?= __('hobbies') ?></h4>
    <p><?= htmlspecialchars($colleague['hobbies']) ?></p>
</div>
<?php endif; ?>

<?php if (empty($sharedContact) && empty($colleague['bio']) && empty($colleague['skills']) && empty($colleague['languages_spoken']) && empty($colleague['hobbies'])): ?>
<p class="text-muted"><?= __('team_directory_empty_profile') ?></p>
<?php endif; ?>
<?php
echo Card::make()->body(ob_get_clean())->render();
?>
