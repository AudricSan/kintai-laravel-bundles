@php
use kintai\Contract\View\Components\Flash;

/** @var array  $submissions */
/** @var array  $submissionImages */
/** @var array  $storeNames */
/** @var array  $availableStores */
/** @var int    $filterStoreId */
/** @var array  $availableDates */
/** @var string $filterDate */
/** @var string $BASE_URL */
if ($storePhotosCss = bundle_asset('store-photos', 'css/photos.css')): @endphp
<link rel="stylesheet" href="{!! $storePhotosCss !!}">
@php endif;

echo Flash::fromQuery('success', [
    'created' => __('store_photos_photo_created'),
    'deleted' => __('store_photos_photo_deleted'),
])->render();
@endphp
<div class="page-header">
    <h2 class="page-header__title">{!! __('store_photos_photos_report') !!} <span class="page-count">({!! count($submissions) !!})</span></h2>
    <div class="page-header__actions">
        <a href="{!! $BASE_URL !!}/admin/photos/create" class="btn btn--primary">+ {!! __('store_photos_photo_new_submission') !!}</a>
    </div>
</div>

<div class="card mb-sm">
    <div class="card-body">
        <form method="GET" action="" class="form-flex">
            <div class="form-group">
                <label class="form-label">{!! __('store') !!}</label>
                <select name="store_id" class="form-control form-control-sm" data-submit-on-change>
                    <option value="0">{!! __('all') !!}</option>
                    @foreach ($availableStores as $s)
                        <option value="{!! (int) $s['id'] !!}" {!! $filterStoreId === (int) $s['id'] ? 'selected' : '' !!}>
                            {{ $s['name'] ?? $storeNames[(int) $s['id']] ?? '#' . $s['id'] }}                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{!! __('store_photos_photo_filter_day') !!}</label>
                <select name="date" class="form-control form-control-sm" data-submit-on-change>
                    <option value="">{!! __('store_photos_photo_filter_all_days') !!}</option>
                    @foreach ($availableDates as $day => $dayCount)
                        <option value="{{ $day }}" {!! $filterDate === $day ? 'selected' : '' !!}>
                            {!! date('d/m/Y', strtotime($day)) !!} ({!! $dayCount !!})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <a href="?" class="btn btn--ghost btn--sm">{!! __('reset') !!}</a>
            </div>
        </form>
    </div>
</div>

@if (empty($submissions))
    <div class="card">
        <div class="empty-state">{!! __('store_photos_photo_empty') !!}</div>
    </div>
@else
    @php $currentDay = null; @endphp
    @foreach ($submissions as $sub)
        @php
        $sid     = (int) $sub['id'];
        $images  = $submissionImages[$sid] ?? [];
        $storeId = (int) ($sub['store_id'] ?? 0);
        $storeName = $storeNames[$storeId] ?? '#' . $storeId;
        $preview = !empty($images) ? $images[0] : null;
        $count   = count($images);
        $day     = date('Y-m-d', strtotime($sub['created_at'] ?? 'now'));
        @endphp
        @if ($day !== $currentDay)
            @if ($currentDay !== null)</div>@endif
            @php $currentDay = $day; @endphp
            <div class="photo-day-group__header">
                <h3 class="photo-day-group__title">{!! date('d/m/Y', strtotime($day)) !!}</h3>
                <span class="badge badge--secondary badge--sm">{!! $availableDates[$day] ?? 0 !!} {!! __('store_photos_photos_count') !!}</span>
            </div>
            <div class="photo-grid">
        @endif
            <a href="{!! $BASE_URL !!}/admin/photos/{!! $storeId !!}/{!! $sid !!}?origin_store_id={!! $filterStoreId !!}" class="card photo-card">
                <div class="photo-card__preview">
                    @if ($preview)
                        <img src="{!! $BASE_URL !!}/{{ $preview['filepath'] }}?v={{ $preview['version'] ?? '' }}"
                             alt="{{ $preview['filename'] }}"
                             loading="lazy"
                             class="photo-card__preview-img">
                    @else
                        <div class="empty-state photo-card__preview-empty">{!! __('store_photos_photo_no_images') !!}</div>
                    @endif
                </div>
                <div class="card-body photo-card__body">
                    <div class="photo-card__meta">
                        <strong>{{ $storeName }}</strong>
                        <span class="badge badge--secondary badge--sm">{!! $count !!} {!! __('store_photos_photos_count') !!}</span>
                    </div>
                    <div class="text-muted text-sm">{{ $sub['week_label'] ?? '' }}</div>
                    @if (!empty($sub['notes']))
                        <div class="text-muted text-xs photo-card__notes">{{ mb_substr($sub['notes'], 0, 80) }}</div>
                    @endif
                    <div class="text-xs text-muted photo-card__date">
                        {!! date('d/m/Y', strtotime($sub['created_at'] ?? 'now')) !!}                    </div>
                </div>
            </a>
    @endforeach
    </div>
@endif
