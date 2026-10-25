@php
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
@endphp
@if ($teamDirectoryCss = bundle_asset('team-directory', 'css/team-directory.css'))
<link rel="stylesheet" href="{!! $teamDirectoryCss !!}">
@endif

<div class="page-header">
    <h2 class="page-header__title">{{ $name }}</h2>
    <div class="page-header__actions">
        <a href="{!! route_url('team.index') !!}" class="btn btn--ghost">← {!! __('team_directory_back') !!}</a>
    </div>
</div>

@php
ob_start();
@endphp
<div class="team-profile-header">
    @if (!empty($colleague['avatar_path']))
        <img src="{!! route_url('user.avatar', ['user_id' => (int) $colleague['id']]) !!}" alt="" class="team-profile-avatar">
    @else
        <span class="avatar-chip team-profile-avatar team-profile-avatar--fallback {!! style_class(['--chip-bg' => $colleague['color'] ?? '#3B82F6']) !!}">{!! $initials !!}</span>
    @endif
    <div>
        <div class="team-profile-name">{{ $name }}</div>
        @if (!empty($storeNames))
            <div class="text-muted">{{ implode(' · ', $storeNames) }}</div>
        @endif
    </div>
</div>

@php
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
@endphp
@if (!empty($sharedContact))
<div class="team-profile-section">
    <h4 class="section-title">{!! __('contact') !!}</h4>
    @foreach ($sharedContact as $label => $value)
    <p><strong>{{ $label }} :</strong> {{ $value }}</p>
    @endforeach
</div>
@endif

@if (!empty($colleague['bio']))
<div class="team-profile-section">
    <h4 class="section-title">{!! __('bio') !!}</h4>
    <p>{!! nl2br(htmlspecialchars($colleague['bio'])) !!}</p>
</div>
@endif

@if (!empty($colleague['skills']))
<div class="team-profile-section">
    <h4 class="section-title">{!! __('skills') !!}</h4>
    <p>{{ $colleague['skills'] }}</p>
</div>
@endif

@if (!empty($colleague['languages_spoken']))
<div class="team-profile-section">
    <h4 class="section-title">{!! __('languages_spoken') !!}</h4>
    <p>{{ $colleague['languages_spoken'] }}</p>
</div>
@endif

@if (!empty($colleague['hobbies']))
<div class="team-profile-section">
    <h4 class="section-title">{!! __('hobbies') !!}</h4>
    <p>{{ $colleague['hobbies'] }}</p>
</div>
@endif

@if (empty($sharedContact) && empty($colleague['bio']) && empty($colleague['skills']) && empty($colleague['languages_spoken']) && empty($colleague['hobbies']))
<p class="text-muted">{!! __('team_directory_empty_profile') !!}</p>
@endif
@php
echo Card::make()->body(ob_get_clean())->render();
@endphp
