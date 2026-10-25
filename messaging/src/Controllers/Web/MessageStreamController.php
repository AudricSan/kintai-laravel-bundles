<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Messaging\Controllers\Web;

use kintai\Contract\Auth\AuthService;
use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Contract\Exceptions\NotFoundException;
use kintai\Bundles\Installed\Messaging\Repositories\MessageRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;

/**
 * Endpoint SSE : pousse les nouveaux messages d'un thread au client en temps réel.
 * Boucle max 25 s, puis demande au client de reconnecter (EventSource le fait automatiquement).
 */
final class MessageStreamController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly MessageRepositoryInterface $msgRepo,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function stream(Request $request): Response
    {
        $user     = $this->auth->user();
        $userId   = (int) $user['id'];
        $threadId = (int) $request->param('id');

        $thread = $this->msgRepo->findThreadById($threadId);
        if ($thread === null) {
            throw new NotFoundException(__('error_conversation_not_found'));
        }

        $part = $this->msgRepo->findParticipant($threadId, $userId);
        if ($part === null) {
            throw new ForbiddenException(__('error_access_denied'));
        }

        // last-event-id envoyé par le navigateur lors d'une reconnexion automatique
        $lastId = (int) ($request->header('Last-Event-ID') ?? $request->query('since', 0));

        // Map id → nom pour enrichir les messages
        $usersMap = [];
        foreach ($this->users->findAll() as $u) {
            $usersMap[(int) $u['id']] = $u['display_name'] ?? $u['email'] ?? '';
        }

        // ── En-têtes SSE ─────────────────────────────────────────────────────
        // Libérer le verrou de session pour ne pas bloquer les requêtes parallèles
        session_write_close();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');

        set_time_limit(0);

        $start  = time();
        $maxAge = 25; // secondes avant de demander une reconnexion propre

        // ── Boucle de streaming ───────────────────────────────────────────────
        while (time() - $start < $maxAge) {
            $msgs = $this->msgRepo->findMessagesByThread($threadId);
            $new  = array_filter($msgs, fn($m) => (int) $m['id'] > $lastId);

            if (!empty($new)) {
                usort($new, fn($a, $b) => (int) $a['id'] <=> (int) $b['id']);

                foreach ($new as $msg) {
                    $msgId  = (int) $msg['id'];
                    $lastId = max($lastId, $msgId);
                    $payload = json_encode([
                        'id'          => $msgId,
                        'sender_id'   => (int) $msg['sender_id'],
                        'sender_name' => $usersMap[(int) $msg['sender_id']] ?? '?',
                        'body'        => $msg['body'] ?? '',
                        'sent_at'     => substr($msg['sent_at'] ?? '', 0, 16),
                        'is_mine'     => (int) $msg['sender_id'] === $userId,
                    ]);
                    // L'id SSE permet au navigateur de le renvoyer comme Last-Event-ID
                    echo "id: {$msgId}\ndata: {$payload}\n\n";
                }
                flush();
            } else {
                // Heartbeat pour maintenir la connexion
                echo ": ping\n\n";
                flush();
            }

            sleep(1);
        }

        // Demander au client de reconnecter immédiatement avec le lastId mis à jour
        $payload = json_encode(['reconnect' => true]);
        echo "id: {$lastId}\ndata: {$payload}\n\n";
        flush();

        exit(0);
    }
}
