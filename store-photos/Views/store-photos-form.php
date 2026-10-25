<?php
/** @var array  $myStores */
/** @var array  $storeNames */
/** @var int    $prefillStoreId */
/** @var string $BASE_URL */

$thisWeek = date('Y-m-d', strtotime('tuesday this week'));
?>
<?php if ($storePhotosCss = bundle_asset('store-photos', 'css/photos.css')): ?>
<link rel="stylesheet" href="<?= $storePhotosCss ?>">
<?php endif; ?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('store_photos_photo_new_submission') ?></h2>
    <div class="page-header__actions">
        <a href="<?= back_url($BASE_URL . '/admin/photos') ?>" class="btn btn--ghost btn--sm">← <?= __('back') ?></a>
    </div>
</div>

<div class="card card--mb" id="uploadCard">
    <div class="card-header"><?= __('store_photos_photo_submission_info') ?></div>
    <div class="card-body form-stack">
        <div class="form-group">
            <label class="form-label"><?= __('store') ?></label>
            <select name="store_id" class="form-control form-control-sm" required id="f_store_id">
                <option value="">— <?= __('select') ?> —</option>
                <?php foreach ($myStores as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $prefillStoreId === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['name'] ?? $storeNames[(int) $s['id']] ?? '#' . $s['id']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('store_photos_photo_week') ?></label>
            <input type="date" name="week_label" class="form-control form-control-sm" id="f_week_label"
                   value="<?= htmlspecialchars($thisWeek) ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('store_photos_photo_notes') ?></label>
            <textarea name="notes" class="form-control" rows="3" id="f_notes" placeholder="<?= __('store_photos_photo_notes_placeholder') ?>"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('store_photos_photo_upload') ?></label>
            <input type="file" name="photos[]" class="form-control" multiple
                   accept="image/jpeg,image/png,image/webp" id="photoInput">
            <div class="photo-preview" id="photoPreview"></div>
            <span class="text-xs text-muted" id="photoCount"></span>
            <span class="form-error hidden" id="photoSizeError"><?= __('store_photos_photo_max_size_error') ?></span>
        </div>
    </div>
</div>

<div class="card card--mb hidden" id="progressCard">
    <div class="card-header"><?= __('store_photos_photo_upload_progress') ?></div>
    <div class="card-body" id="progressList"></div>
</div>

<div class="form-actions">
    <button type="button" class="btn btn--primary" id="uploadBtn" disabled><?= __('store_photos_photo_submit') ?></button>
    <a href="<?= back_url($BASE_URL . '/admin/photos') ?>" class="btn btn--ghost"><?= __('cancel') ?></a>
</div>

<script nonce="<?= function_exists('csp_nonce') ? csp_nonce() : '' ?>">
var selectedFiles = [];
var input = document.getElementById('photoInput');
var preview = document.getElementById('photoPreview');
var uploadBtn = document.getElementById('uploadBtn');
var photoCount = document.getElementById('photoCount');
var sizeError = document.getElementById('photoSizeError');
var progressCard = document.getElementById('progressCard');
var progressList = document.getElementById('progressList');
var uploadCard = document.getElementById('uploadCard');

input.addEventListener('change', function() {
    for (var i = 0; i < this.files.length; i++) {
        selectedFiles.push(this.files[i]);
    }
    this.value = '';
    renderPreview();
});

function renderPreview() {
    preview.innerHTML = '';
    var total = 0;
    for (var i = 0; i < selectedFiles.length; i++) {
        var f = selectedFiles[i];
        total += f.size;
        var div = document.createElement('div');
        div.className = 'photo-preview-item';
        var img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:4px';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'photo-preview-remove';
        btn.innerHTML = '&times;';
        btn.title = '<?= __('store_photos_photo_remove') ?>';
        btn.onclick = (function(idx) { return function() {
            selectedFiles.splice(idx, 1);
            renderPreview();
        };})(i);
        var name = document.createElement('span');
        name.className = 'text-xs text-muted';
        name.textContent = (f.size / 1024 / 1024).toFixed(1) + ' Mo';
        div.appendChild(btn);
        div.appendChild(img);
        div.appendChild(name);
        preview.appendChild(div);
    }
    photoCount.textContent = selectedFiles.length ? selectedFiles.length + ' <?= __('store_photos_photos_count') ?>' : '';
    var maxBytes = 40 * 1024 * 1024;
    if (total > maxBytes) {
        sizeError.classList.remove('hidden');
        uploadBtn.disabled = true;
    } else {
        sizeError.classList.add('hidden');
        uploadBtn.disabled = selectedFiles.length === 0;
    }
}

uploadBtn.addEventListener('click', function() {
    var storeId = document.getElementById('f_store_id').value;
    var weekLabel = document.getElementById('f_week_label').value;
    var notes = document.getElementById('f_notes').value;
    if (!storeId) { alert('<?= __('select') ?>'); return; }
    if (selectedFiles.length === 0) return;

    uploadBtn.disabled = true;
    uploadBtn.textContent = '<?= __('store_photos_photo_uploading') ?>...';
    uploadCard.style.display = 'none';
    progressCard.classList.remove('hidden');
    progressList.innerHTML = '';

    var files = selectedFiles.slice();
    var token = '<?= csrf_token() ?>';

    var fd = new FormData();
    fd.append('_token', token);
    fd.append('store_id', storeId);
    fd.append('week_label', weekLabel);
    fd.append('notes', notes);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?= $BASE_URL ?>/admin/photos/create');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        if (xhr.status !== 200) {
            var msg = 'Erreur cr&eacute;ation soumission (code ' + xhr.status + ').';
            try {
                var body = JSON.parse(xhr.responseText);
                if (body && body.error) { msg = htmlEscape(body.error); }
            } catch (e) {}
            progressList.innerHTML = '<p class="text-danger">' + msg + '</p>';
            uploadBtn.textContent = '<?= __('store_photos_photo_submit') ?>';
            return;
        }
        var submissionId;
        try { submissionId = JSON.parse(xhr.responseText).id; } catch(e) {
            progressList.innerHTML = '<p class="text-danger">R&eacute;ponse invalide.</p>';
            uploadBtn.textContent = '<?= __('store_photos_photo_submit') ?>';
            return;
        }
        uploadNext(submissionId, files, 0, token);
    };
    xhr.onerror = function() {
        progressList.innerHTML = '<p class="text-danger">Erreur r&eacute;seau.</p>';
        uploadBtn.textContent = '<?= __('store_photos_photo_submit') ?>';
    };
    xhr.send(fd);

    function uploadNext(sid, files, idx, token) {
        if (idx >= files.length) {
            var p = document.createElement('p');
            p.className = 'text-success';
            p.textContent = '<?= __('store_photos_photo_upload_done') ?>';
            progressList.appendChild(p);
            setTimeout(function() { window.location.href = '<?= $BASE_URL ?>/admin/photos?store_id=' + storeId; }, 1500);
            return;
        }
        var file = files[idx];
        var row = document.createElement('div');
        row.className = 'photo-upload-row';
        row.innerHTML = '<span class="text-sm">' + htmlEscape(file.name) + '</span>' +
            '<div class="progress-bar"><div class="progress-bar__fill" id="pbar_' + idx + '"></div></div>' +
            '<span class="text-xs" id="ptxt_' + idx + '">0%</span>';
        progressList.appendChild(row);

        var fd = new FormData();
        fd.append('_token', token);
        fd.append('photo', file);

        var x = new XMLHttpRequest();
        x.open('POST', '<?= $BASE_URL ?>/admin/photos/' + sid + '/upload');
        x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        x.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                var pct = Math.round(e.loaded / e.total * 100);
                document.getElementById('pbar_' + idx).style.width = pct + '%';
                document.getElementById('ptxt_' + idx).textContent = pct + '%';
            }
        };
        x.onload = function() {
            if (x.status === 200) {
                document.getElementById('pbar_' + idx).style.width = '100%';
                document.getElementById('ptxt_' + idx).textContent = 'OK';
            } else {
                document.getElementById('ptxt_' + idx).textContent = 'Erreur ' + x.status;
            }
            uploadNext(sid, files, idx + 1, token);
        };
        x.onerror = function() {
            document.getElementById('ptxt_' + idx).textContent = 'Erreur r&eacute;seau';
            uploadNext(sid, files, idx + 1, token);
        };
        x.send(fd);
    }

    function htmlEscape(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
});
</script>
