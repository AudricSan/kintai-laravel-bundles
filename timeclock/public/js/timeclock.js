/**
 * Timeclock : clock-in / clock-out + horloge live + offline queue (IndexedDB).
 */
(function () {
    'use strict';

    var meta    = document.getElementById('timeclock-meta');
    if (!meta) return;

    var clockInUrl   = meta.dataset.clockInUrl;
    var clockOutUrl  = meta.dataset.clockOutUrl;
    var csrfToken    = meta.dataset.csrfToken || '';
    var userId       = parseInt(meta.dataset.userId,   10) || 0;
    var storeId      = parseInt(meta.dataset.storeId,  10) || 0;
    var clockedIn    = meta.dataset.clockedIn === '1';
    var clockInTs    = meta.dataset.clockInTs || '';

    /* ── Horloge live ──────────────────────────── */
    var clockEl   = document.getElementById('timeclock-clock');
    var elapsedEl = document.getElementById('timeclock-elapsed');

    function pad(n) { return String(n).padStart(2, '0'); }

    function formatDuration(minutes) {
        var h = Math.floor(minutes / 60);
        var m = minutes % 60;
        return h + 'h' + pad(m);
    }

    function tick() {
        var now = new Date();
        if (clockEl) {
            clockEl.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
        }
        if (elapsedEl && clockedIn && clockInTs) {
            var elapsed = Math.floor((now.getTime() - new Date(clockInTs).getTime()) / 60000);
            if (elapsed >= 0) {
                elapsedEl.textContent = formatDuration(elapsed);
            }
        }
    }

    tick();
    setInterval(tick, 1000);

    /* ── IndexedDB offline queue ──────────────── */
    var DB_NAME = 'kintai-offline';
    var DB_VER  = 1;

    function openDb() {
        return new Promise(function (resolve, reject) {
            var req = indexedDB.open(DB_NAME, DB_VER);
            req.onupgradeneeded = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains('pending')) {
                    db.createObjectStore('pending', { keyPath: 'id', autoIncrement: true });
                }
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror   = function () { reject(req.error); };
        });
    }

    function queueOperation(op) {
        openDb().then(function (db) {
            var tx = db.transaction('pending', 'readwrite');
            tx.objectStore('pending').add(op);
            tx.oncomplete = function () { db.close(); };
        });
    }

    function countPending(callback) {
        openDb().then(function (db) {
            var tx  = db.transaction('pending', 'readonly');
            var req = tx.objectStore('pending').count();
            req.onsuccess = function () { db.close(); callback(req.result || 0); };
            req.onerror   = function () { db.close(); callback(0); };
        });
    }

    function updatePendingBadge() {
        var badge = document.getElementById('timeclock-pending');
        if (!badge) return;
        countPending(function (n) {
            if (n > 0) {
                badge.textContent = n === 1
                    ? meta.dataset.msgPendingOne
                    : meta.dataset.msgPendingMany.replace('%d', n);
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    var flushing = false;

    // Rejoue la file dans l'ordre d'entrée (entrée avant sortie) : deux fetch simultanés
    // pourraient arriver au serveur dans le désordre et faire échouer une sortie dont
    // l'entrée correspondante n'a pas encore été enregistrée.
    function flushQueue() {
        if (flushing) return;
        flushing = true;

        openDb().then(function (db) {
            var tx  = db.transaction('pending', 'readonly');
            var req = tx.objectStore('pending').getAll();
            req.onsuccess = function () {
                var ops = req.result || [];
                db.close();
                if (ops.length === 0) { flushing = false; return; }
                replayNext(ops, 0, false);
            };
            req.onerror = function () { db.close(); flushing = false; };
        });
    }

    function replayNext(ops, index, hadFailure) {
        if (index >= ops.length) {
            flushing = false;
            updatePendingBadge();
            if (hadFailure) {
                showMessage(meta.dataset.msgOfflineSyncFailed, 'danger');
            } else {
                location.reload();
            }
            return;
        }

        var op = ops[index];
        fetch(op.url, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body:    JSON.stringify(op.body),
        }).then(function (r) {
            if (r.ok) {
                openDb().then(function (db) {
                    var delTx = db.transaction('pending', 'readwrite');
                    delTx.objectStore('pending').delete(op.id);
                    delTx.oncomplete = function () {
                        db.close();
                        replayNext(ops, index + 1, hadFailure);
                    };
                });
            } else {
                // r.ok=false (ex. token CSRF expiré, shift supprimé entre-temps) : on NE
                // supprime PAS l'opération, sinon l'action hors ligne serait perdue en
                // silence sans jamais avoir été réellement enregistrée côté serveur. On
                // arrête la chaîne ici pour ne pas rejouer une suite hors ordre.
                replayNext(ops, ops.length, true);
            }
        }).catch(function () {
            // Réseau à nouveau indisponible en cours de synchro : le reste reste en file.
            replayNext(ops, ops.length, true);
        });
    }

    window.addEventListener('online', flushQueue);
    if (navigator.onLine) { flushQueue(); }
    updatePendingBadge();

    /* ── Helpers ───────────────────────────────── */
    function showMessage(text, type) {
        var el = document.getElementById('timeclock-message');
        if (!el) return;
        el.textContent = text;
        el.className   = 'alert alert--' + type + ' mt-sm';
        el.style.display = 'block';
        setTimeout(function () { el.style.display = 'none'; }, 4000);
    }

    function apiCall(url, body, onSuccess, onErrorMsg) {
        fetch(url, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body:    JSON.stringify(body),
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            if (res.ok) {
                onSuccess(res);
            } else {
                showMessage(res.data.error || onErrorMsg, 'danger');
            }
        })
        .catch(function () {
            queueOperation({ url: url, body: body, created_at: new Date().toISOString() });
            updatePendingBadge();
            showMessage(meta.dataset.msgOfflineQueued, 'warning');
        });
    }

    /* ── Clock-in ──────────────────────────────── */
    var btnIn = document.getElementById('btn-clockin');
    if (btnIn) {
        btnIn.addEventListener('click', function () {
            btnIn.disabled = true;
            // Employé multi-magasins : le magasin choisi dans la liste, sinon le magasin par défaut.
            var storeSelect = document.getElementById('timeclock-store');
            var chosenStore = storeSelect ? (parseInt(storeSelect.value, 10) || storeId) : storeId;
            apiCall(clockInUrl, { user_id: userId, store_id: chosenStore, client_time: new Date().toISOString() },
                function () {
                    showMessage(meta.dataset.msgClockInOk, 'success');
                    setTimeout(function () { location.reload(); }, 800);
                },
                meta.dataset.msgError
            );
        });
    }

    /* ── Clock-out ─────────────────────────────── */
    var btnOut = document.getElementById('btn-clockout');
    if (btnOut) {
        btnOut.addEventListener('click', function () {
            btnOut.disabled = true;
            apiCall(clockOutUrl, { user_id: userId, client_time: new Date().toISOString() },
                function (res) {
                    var dur = res.data.duration_minutes !== null
                        ? ' (' + formatDuration(res.data.duration_minutes) + ')'
                        : '';
                    showMessage(meta.dataset.msgClockOutOk + dur, 'success');
                    setTimeout(function () { location.reload(); }, 800);
                },
                meta.dataset.msgError
            );
        });
    }
})();
