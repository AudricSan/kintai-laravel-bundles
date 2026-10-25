@php
/** @var array  $submission */
/** @var array  $images */
/** @var array|null $store */
/** @var string $BASE_URL */
/** @var array|null $auth_user */
/** @var int    $backStoreId */

$storeName = $store['name'] ?? ('#' . ($submission['store_id'] ?? '?'));
$sid       = (int) $submission['id'];
$isOwner   = !empty($auth_user['is_admin']);
$backUrl   = back_url($BASE_URL . '/admin/photos' . ($backStoreId > 0 ? '?store_id=' . $backStoreId : ''));
@endphp
@if ($storePhotosCss = bundle_asset('store-photos', 'css/photos.css'))
<link rel="stylesheet" href="{!! $storePhotosCss !!}">
@endif
<div class="page-header">
    <h2 class="page-header__title">
        {{ $storeName }} — {{ $submission['week_label'] ?? '' }}    </h2>
    <div class="page-header__actions">
        <a href="{!! $backUrl !!}" class="btn btn--ghost btn--sm">← {!! __('back') !!}</a>
        @if ($isOwner)
            <form method="POST" action="{!! $BASE_URL !!}/admin/photos/{!! (int) ($submission['store_id'] ?? 0) !!}/{!! $sid !!}/delete?origin_store_id={!! $backStoreId !!}"
                  class="form-inline" data-confirm="{{ __('store_photos_photo_confirm_delete') }}">
                {!! csrf_field() !!}                <button type="submit" class="btn btn--danger btn--sm">{!! __('delete') !!}</button>
            </form>
        @endif
    </div>
</div>

@if (!empty($submission['notes']))
    <div class="card card--mb">
        <div class="card-body">
            <strong>{!! __('store_photos_photo_notes') !!} :</strong>
            <p class="text-sm mt-xs">{!! nl2br(htmlspecialchars($submission['notes'])) !!}</p>
        </div>
    </div>
@endif

@if (empty($images))
    <div class="card">
        <div class="empty-state">{!! __('store_photos_photo_no_images') !!}</div>
    </div>
@else
    <div class="photo-detail-grid">
        @foreach (array_values($images) as $i => $img)
            <div class="card photo-detail-card">
                <button type="button" class="photo-detail-card__trigger" data-on-click="photoCarouselOpen" data-args="[{!! (int) $i !!}]">
                    <img src="{!! $BASE_URL !!}/{{ $img['filepath'] }}?v={{ $img['version'] ?? '' }}"
                         alt="{{ $img['filename'] }}"
                         loading="lazy"
                         class="photo-detail-card__img">
                </button>
                <div class="card-body photo-detail-card__body">
                    <div class="text-xs text-muted">{{ $img['filename'] }}</div>
                    @if (!empty($img['filesize']))
                        <div class="text-xs text-muted">{!! number_format((int) $img['filesize'] / 1024, 1) !!} KB</div>
                    @endif
                    @if ($isOwner)
                        <div class="photo-detail-card__rotate">
                            <form method="POST" action="{!! $BASE_URL !!}/admin/photos/image/{!! (int) $img['id'] !!}/rotate?origin_store_id={!! $backStoreId !!}" class="form-inline">
                                {!! csrf_field() !!}                                <input type="hidden" name="direction" value="left">
                                <button type="submit" class="btn btn--ghost btn--xs" title="{{ __('store_photos_photo_rotate_left') }}">⟲</button>
                            </form>
                            <form method="POST" action="{!! $BASE_URL !!}/admin/photos/image/{!! (int) $img['id'] !!}/rotate?origin_store_id={!! $backStoreId !!}" class="form-inline">
                                {!! csrf_field() !!}                                <input type="hidden" name="direction" value="right">
                                <button type="submit" class="btn btn--ghost btn--xs" title="{{ __('store_photos_photo_rotate_right') }}">⟳</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- ── Carousel photo (visionneuse plein écran) ─────────────────────── -->
    <div id="photo-carousel-overlay" class="modal photo-carousel" data-on-click="photoCarouselClose">
        <button type="button" class="photo-carousel__close" data-on-click="photoCarouselClose">&times;</button>
        <button type="button" class="photo-carousel__nav photo-carousel__nav--prev" data-on-click="photoCarouselPrev" data-stop-propagation>&lsaquo;</button>
        <div class="photo-carousel__stage" data-stop-propagation>
            <img id="photo-carousel-img" class="photo-carousel__img" src="" alt="">
            <div class="photo-carousel__caption">
                <span id="photo-carousel-filename"></span>
                <span id="photo-carousel-counter" class="photo-carousel__counter"></span>
            </div>
        </div>
        <button type="button" class="photo-carousel__nav photo-carousel__nav--next" data-on-click="photoCarouselNext" data-stop-propagation>&rsaquo;</button>
    </div>

    <script type="application/json" id="photo-carousel-data">{!! json_encode(array_map(static function ($img) use ($BASE_URL) {
        return [
            'src'      => $BASE_URL . '/' . $img['filepath'] . '?v=' . ($img['version'] ?? ''),
            'filename' => $img['filename'],
        ];
    }, array_values($images)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @if ($photoCarouselJs = bundle_asset('store-photos', 'js/photo-carousel.js'))
    <script src="{!! $photoCarouselJs !!}"></script>
    @endif
@endif
