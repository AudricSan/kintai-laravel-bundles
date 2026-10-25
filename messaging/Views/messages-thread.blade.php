@php
/** @var array    $thread        Le fil de discussion */
/** @var array[]  $messages      Messages triés chronologiquement */
/** @var array[]  $participants  Participants du fil */
/** @var array    $users_map     id → nom */
/** @var array    $stores_map    id → nom du store */
/** @var int      $user_id       ID de l'utilisateur connecté */
/** @var string   $base_path     '/admin/messages' ou '/employee/messages' */
/** @var bool     $can_manage    true en contexte admin/manager (lien vers la fiche utilisateur) */

$participantNames = array_map(
    fn($p) => htmlspecialchars($users_map[(int) $p['user_id']] ?? '?'),
    $participants
);
$_canManage = $can_manage ?? true;
@endphp
@if ($messagingCss = bundle_asset('messaging', 'css/messaging.css'))
<link rel="stylesheet" href="{!! $messagingCss !!}">
@endif

{!! \kintai\Contract\View\Components\Flash::fromQuery('success', ['sent' => __('messaging_message_sent'), 'replied' => __('messaging_message_replied')])->render() !!}
<div class="page-header">
    <h2 class="page-header__title">{{ $thread['subject'] ?? '' }}</h2>
    <div class="page-header__actions">
        <a href="{!! back_url($BASE_URL . $base_path) !!}" class="btn btn--ghost">← {!! __('back') !!}</a>
        <form method="POST" action="{!! $BASE_URL . $base_path !!}/{!! (int) $thread['id'] !!}/delete"
              data-confirm="{{ __('messaging_confirm_delete_thread') }}">
            {!! csrf_field() !!}            <button type="submit" class="btn btn--danger btn--sm">{!! __('messaging_delete_thread') !!}</button>
        </form>
    </div>
</div>

<div class="text-sm-muted mb-sm">
    {!! __('store') !!}: <strong>{{ $stores_map[(int) ($thread['store_id'] ?? 0)] ?? '—' }}</strong>
    &nbsp;·&nbsp;
    {!! __('messaging_participants') !!}: {!! implode(', ', $participantNames) !!}</div>

<div id="msg-meta"
     data-thread-id="{!! (int) $thread['id'] !!}"
     data-user-id="{!! (int) $user_id !!}"
     data-base-url="{{ $BASE_URL }}"
     data-reply-url="{{ $BASE_URL . $base_path }}/{!! (int) $thread['id'] !!}"
     hidden></div>

<!-- Messages -->
<div class="msg-thread" id="msg-thread">
    @foreach ($messages as $msg)
        @php $isMine = (int) $msg['sender_id'] === $user_id; @endphp
        <div class="msg-bubble {!! $isMine ? 'msg-bubble--mine' : 'msg-bubble--theirs' !!}" data-msg-id="{!! (int) $msg['id'] !!}">
            <div class="msg-bubble__meta">
                <span class="msg-bubble__author">@if ($_canManage)<a href="{!! $BASE_URL !!}/admin/users/{!! (int) $msg['sender_id'] !!}/edit">{{ $users_map[(int) $msg['sender_id']] ?? '?' }}</a>@else{{ $users_map[(int) $msg['sender_id']] ?? '?' }}@endif</span>
                <span class="msg-bubble__time">{{ substr($msg['sent_at'] ?? '', 0, 16) }}</span>
                @if ($isMine)
                    <form method="POST"
                          action="{!! $BASE_URL . $base_path !!}/{!! (int) $thread['id'] !!}/message/{!! (int) $msg['id'] !!}/delete"
                          class="msg-delete-form"
                          data-confirm="{{ __('messaging_confirm_delete_message') }}">
                        {!! csrf_field() !!}                        <button type="submit" class="btn-msg-delete" title="{!! __('messaging_delete_message') !!}">✕</button>
                    </form>
                @endif
            </div>
            <div class="msg-bubble__body">{!! nl2br(htmlspecialchars($msg['body'] ?? '')) !!}</div>
        </div>
    @endforeach
</div>

<!-- Réponse -->
<div class="card mt-sm">
    <div class="card-body">
        <form id="reply-form" method="POST" action="{!! $BASE_URL . $base_path !!}/{!! (int) $thread['id'] !!}">
            {!! csrf_field() !!}            <div class="form-stack">
                <div class="form-group">
                    <label class="form-label form-label--required">{!! __('messaging_reply') !!}</label>
                    <textarea name="body" class="form-control" rows="4" required placeholder="{!! __('messaging_message_reply_placeholder') !!}"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">{!! __('send') !!}</button>
                </div>
            </div>
        </form>
    </div>
</div>

@if ($messageStreamJs = bundle_asset('messaging', 'js/message-stream.js'))
<script src="{!! $messageStreamJs !!}"></script>
@endif
