<?php
/** @var array[]  $threads     Fils de discussion enrichis */
/** @var array    $users_map   id → nom */
/** @var array    $stores_map  id → nom du store */
/** @var string   $tab         'inbox'|'sent'|'unread' */
/** @var int      $user_id     ID de l'utilisateur connecté */
/** @var string   $base_path   '/admin/messages' ou '/employee/messages' */
?>
<?php if ($messagingCss = bundle_asset('messaging', 'css/messaging.css')): ?>
<link rel="stylesheet" href="<?= $messagingCss ?>">
<?php endif; ?>

<?= \kintai\Contract\View\Components\Flash::fromQuery('success', ['deleted' => __('messaging_thread_deleted')])->render() ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('messaging_messages') ?> <span class="page-count">(<?= count($threads) ?>)</span></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL . $base_path ?>/compose" class="btn btn--primary"><?= __('messaging_new_message') ?></a>
    </div>
</div>

<!-- Onglets -->
<div class="tab-bar mb-sm">
    <a href="?tab=inbox"  class="tab-bar__item <?= $tab === 'inbox'  ? 'tab-bar__item--active' : '' ?>"><?= __('messaging_inbox') ?></a>
    <a href="?tab=sent"   class="tab-bar__item <?= $tab === 'sent'   ? 'tab-bar__item--active' : '' ?>"><?= __('messaging_sent') ?></a>
    <a href="?tab=unread" class="tab-bar__item <?= $tab === 'unread' ? 'tab-bar__item--active' : '' ?>"><?= __('messaging_unread') ?></a>
</div>

<div class="card">
    <?php if (empty($threads)): ?>
        <div class="empty-state"><?= __('messaging_no_messages') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr>
                        <th></th>
                        <th><?= __('messaging_subject') ?></th>
                        <th><?= $tab === 'sent' ? __('to') : __('from') ?></th>
                        <th><?= __('store') ?></th>
                        <th><?= __('date') ?></th>
                        <th><?= __('messaging_messages') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($threads as $thread): ?>
                        <?php
                        $isRead    = (int) ($thread['_is_read'] ?? 1);
                        $lastMsg   = $thread['_last_msg'] ?? null;
                        $lastDate  = $lastMsg ? substr($lastMsg['sent_at'] ?? '', 0, 16) : substr($thread['created_at'] ?? '', 0, 16);
                        $creatorId = (int) ($thread['creator_id'] ?? 0);
                        $lastSenderId = (int) ($lastMsg['sender_id'] ?? $creatorId);
                        $counterpart  = $tab === 'sent'
                            ? '—'
                            : htmlspecialchars($users_map[$lastSenderId] ?? '—');
                        if ($tab === 'sent') {
                            $counterpart = '—';
                        }
                        $fromToLabel = $tab === 'sent' ? __('to') : __('from');
                        ?>
                        <tr class="<?= !$isRead ? 'tr--unread' : '' ?>">
                            <td class="td-indicator">
                                <?php if (!$isRead): ?>
                                    <span class="badge badge--primary badge--dot" title="<?= __('messaging_unread') ?>"></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= htmlspecialchars(__('messaging_subject')) ?>">
                                <a href="<?= $BASE_URL . $base_path ?>/<?= (int) $thread['id'] ?>" class="link-subject <?= !$isRead ? 'link-subject--bold' : '' ?>">
                                    <?= htmlspecialchars($thread['subject'] ?? '') ?>
                                </a>
                            </td>
                            <td data-label="<?= htmlspecialchars($fromToLabel) ?>"><?= $counterpart ?></td>
                            <td data-label="<?= htmlspecialchars(__('store')) ?>"><?= htmlspecialchars($stores_map[(int) ($thread['store_id'] ?? 0)] ?? '—') ?></td>
                            <td data-label="<?= htmlspecialchars(__('date')) ?>" class="td-date-muted"><?= $lastDate ?></td>
                            <td data-label="<?= htmlspecialchars(__('messaging_messages')) ?>" class="td-mono"><?= (int) ($thread['_msg_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
