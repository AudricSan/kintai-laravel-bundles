/**
 * Messagerie temps réel : SSE (Server-Sent Events) + envoi AJAX sans rechargement.
 */
(function () {
    'use strict';

    const meta     = document.getElementById('msg-meta');
    if (!meta) return;

    const threadId = meta.dataset.threadId;
    const userId   = parseInt(meta.dataset.userId, 10);
    const baseUrl  = meta.dataset.baseUrl;
    const replyUrl = meta.dataset.replyUrl;

    const thread   = document.getElementById('msg-thread');
    const form     = document.getElementById('reply-form');
    const textarea = form ? form.querySelector('textarea[name="body"]') : null;
    const btn      = form ? form.querySelector('button[type="submit"]') : null;

    // ── Utilitaires ─────────────────────────────────────────────────────────

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function isNearBottom() {
        return thread.scrollHeight - thread.scrollTop - thread.clientHeight < 120;
    }

    function scrollToBottom() {
        thread.scrollTop = thread.scrollHeight;
    }

    function appendBubble(msg) {
        const isMine = msg.is_mine;
        const div    = document.createElement('div');
        div.className = 'msg-bubble ' + (isMine ? 'msg-bubble--mine' : 'msg-bubble--theirs');
        div.dataset.msgId = msg.id;
        div.innerHTML =
            '<div class="msg-bubble__meta">' +
                '<span class="msg-bubble__author">' + escapeHtml(msg.sender_name) + '</span>' +
                '<span class="msg-bubble__time">'   + escapeHtml(msg.sent_at)     + '</span>' +
            '</div>' +
            '<div class="msg-bubble__body">' + escapeHtml(msg.body).replace(/\n/g, '<br>') + '</div>';

        const wasNear = isNearBottom();
        thread.appendChild(div);
        if (wasNear) {
            scrollToBottom();
        }
    }

    function messageAlreadyShown(id) {
        return thread.querySelector('[data-msg-id="' + id + '"]') !== null;
    }

    // ── SSE ──────────────────────────────────────────────────────────────────

    let lastId = 0;
    // Initialiser lastId depuis le dernier message déjà affiché
    const bubbles = thread ? thread.querySelectorAll('[data-msg-id]') : [];
    bubbles.forEach(function (b) {
        const id = parseInt(b.dataset.msgId, 10);
        if (id > lastId) lastId = id;
    });

    let eventSource = null;

    function connect() {
        if (eventSource) {
            eventSource.close();
        }
        const url = baseUrl + '/messages/' + threadId + '/stream?since=' + lastId;
        eventSource = new EventSource(url);

        eventSource.onmessage = function (e) {
            let data;
            try { data = JSON.parse(e.data); } catch { return; }

            if (data.reconnect) {
                // Le serveur a fermé proprement, reconnexion avec lastId mis à jour
                connect();
                return;
            }

            if (!messageAlreadyShown(data.id)) {
                appendBubble(data);
                lastId = Math.max(lastId, data.id);
            }
        };

        eventSource.onerror = function () {
            // EventSource reconnecter automatiquement après 3s
            eventSource.close();
            eventSource = null;
            setTimeout(connect, 3000);
        };
    }

    // ── Envoi AJAX ───────────────────────────────────────────────────────────

    if (form && textarea && btn) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const body = textarea.value.trim();
            if (!body) return;

            btn.disabled = true;

            fetch(replyUrl, {
                method:  'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type':     'application/x-www-form-urlencoded',
                },
                body: 'body=' + encodeURIComponent(body),
            })
            .then(function (resp) {
                if (resp.ok) {
                    textarea.value = '';
                    textarea.focus();
                    // Le message apparaîtra via SSE sous ~1s
                }
            })
            .catch(function () {
                // Fallback : soumission classique
                form.submit();
            })
            .finally(function () {
                btn.disabled = false;
            });
        });

        // Envoyer avec Ctrl+Entrée ou Cmd+Entrée
        textarea.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        });
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    if (thread) {
        scrollToBottom();
        // Attribuer data-msg-id aux bulles existantes (rendues côté serveur)
        document.querySelectorAll('.msg-bubble').forEach(function (b, i) {
            if (!b.dataset.msgId && bubbles[i]) {
                b.dataset.msgId = bubbles[i].dataset.msgId;
            }
        });
    }

    connect();
})();
