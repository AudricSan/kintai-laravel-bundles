@php
/** @var array[] $all_stores  Stores accessibles */
/** @var array[] $all_users   Utilisateurs accessibles */
/** @var string  $base_path   '/admin/messages' ou '/employee/messages' */
@endphp

<div class="page-header">
    <h2 class="page-header__title">{!! __('messaging_new_message') !!}</h2>
    <div class="page-header__actions">
        <a href="{!! back_url($BASE_URL . $base_path) !!}" class="btn btn--ghost">← {!! __('back') !!}</a>
    </div>
</div>

@if (isset($_GET['error']))
    <div class="alert alert--danger">{!! __('messaging_message_missing_fields') !!}</div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{!! $BASE_URL . $base_path !!}">
            {!! csrf_field() !!}            <div class="form-stack">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label form-label--required">{!! __('store') !!}</label>
                        @if (count($all_stores) === 1)
                            @php $onlyStore = $all_stores[0]; @endphp
                            <input type="hidden" name="store_id" value="{!! (int) $onlyStore['id'] !!}">
                            <div class="form-control form-control--readonly">
                                {{ $onlyStore['name'] ?? '' }}                            </div>
                        @else
                            <select name="store_id" class="form-control" required>
                                <option value="">— {!! __('select') !!} —</option>
                                @foreach ($all_stores as $s)
                                    <option value="{!! (int) $s['id'] !!}">{{ $s['name'] ?? '' }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label--required">{!! __('messaging_recipients') !!}</label>
                        <select name="recipient_ids[]" class="form-control" multiple required size="5">
                            @foreach ($all_users as $u)
                                <option value="{!! (int) $u['id'] !!}">
                                    {{ $u['display_name'] ?? $u['email'] ?? '' }}                                </option>
                            @endforeach
                        </select>
                        <span class="form-hint">{!! __('messaging_select_multiple_hint') !!}</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label form-label--required">{!! __('messaging_subject') !!}</label>
                    <input type="text" name="subject" class="form-control" maxlength="255" required>
                </div>

                <div class="form-group">
                    <label class="form-label form-label--required">{!! __('messaging_message_body') !!}</label>
                    <textarea name="body" class="form-control" rows="6" required></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">{!! __('send') !!}</button>
                    <a href="{!! back_url($BASE_URL . $base_path) !!}" class="btn btn--ghost">{!! __('cancel') !!}</a>
                </div>

            </div>
        </form>
    </div>
</div>
