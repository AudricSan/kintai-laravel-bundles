<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Messaging\Controllers\Api;

use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Contract\Exceptions\NotFoundException;
use kintai\Bundles\Installed\Messaging\Repositories\MessageRepositoryInterface;
use kintai\Bundles\Installed\Messaging\Services\ThreadVisibility;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;

/**
 * Messagerie API — strictement limitée aux conversations dont le porteur du
 * token est participant (voir .wiki/API-Reference.md : ces routes sont
 * volontairement absentes de config/api-permissions.php, en libre-service
 * comme notifications.*, mais bornées ici par appartenance plutôt que par
 * RBAC — un rôle n'a jamais besoin de porter les messages d'un tiers).
 *
 * Ajouter un participant reste malgré tout borné au scoping magasin, comme
 * partout ailleurs dans l'app : createThread()/addParticipant() vérifient
 * via assertCanAddParticipant() que la cible partage un store avec l'appelant
 * avant de l'ajouter, pour empêcher un employé de découvrir l'appartenance
 * d'un utilisateur d'un store auquel il n'a pas accès.
 *
 * Régression (audit du 03/10/2026) : addMessage() et addParticipant() fusionnaient le JSON brut du
 * client. Un `id` dans le corps (POST /messages/threads/{id}/messages) faisait un upsert sur le message
 * d'un autre fil : il était déplacé dans le fil de l'appelant, réécrit, et sa réponse en exposait le
 * contenu — sans aucune permission au-delà de l'authentification. Seuls `body` (message) et `user_id`
 * (participant) sont lus ; le reste est imposé par le serveur. createThread() exige désormais un store
 * dont l'appelant est membre.
 */
final class MessageController
{
    public function __construct(
        private readonly MessageRepositoryInterface $messages,
        private readonly StoreUserRepositoryInterface $storeUsers,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Threads
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/messages/threads */
    public function listThreads(Request $request): Response
    {
        $userId = $this->authUserId($request);

        $participations = $this->messages->findParticipationsByUser($userId);
        $threads        = [];
        foreach ($participations as $p) {
            if (!ThreadVisibility::isVisible($p)) {
                continue;
            }
            $thread = $this->messages->findThreadById((int) $p['thread_id']);
            if ($thread !== null) {
                $threads[] = $thread;
            }
        }

        return Response::json($threads);
    }

    /** POST /api/v1/messages/threads */
    public function createThread(Request $request): Response
    {
        $userId = $this->authUserId($request);
        $data   = $request->json() ?? [];

        $storeId = (int) ($data['store_id'] ?? 0);
        if ($storeId <= 0 || $this->storeUsers->findMembership($storeId, $userId) === null) {
            throw new ForbiddenException(__('error_access_denied'));
        }

        $thread = $this->messages->saveThread([
            'store_id'   => $storeId,
            'subject'    => $data['subject'] ?? null,
            'creator_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $threadId = (int) $thread['id'];

        $this->messages->saveParticipant([
            'thread_id'  => $threadId,
            'user_id'    => $userId,
            'is_read'    => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $participantIds = array_unique(array_filter(
            array_map('intval', (array) ($data['participant_ids'] ?? [])),
            fn($uid) => $uid > 0 && $uid !== $userId
        ));
        foreach ($participantIds as $uid) {
            $this->assertCanAddParticipant($userId, $uid);
            $this->messages->saveParticipant([
                'thread_id'  => $threadId,
                'user_id'    => $uid,
                'is_read'    => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return Response::json($thread, 201);
    }

    /** GET /api/v1/messages/threads/{id} */
    public function getThread(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $thread   = $this->requireThreadParticipant($request, $threadId);
        return Response::json($thread);
    }

    /** DELETE /api/v1/messages/threads/{id} */
    public function deleteThread(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $userId = $this->requireThreadParticipantId($request, $threadId);

        // Masquée pour l'appelant seulement ; effacée quand plus personne ne la voit (même règle que le web).
        (new ThreadVisibility($this->messages))->hideFor($threadId, $userId);

        return Response::empty();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Messages d'un thread
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/messages/threads/{id}/messages */
    public function listMessages(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $this->requireThreadParticipant($request, $threadId);
        return Response::json($this->messages->findMessagesByThread($threadId));
    }

    /** POST /api/v1/messages/threads/{id}/messages */
    public function addMessage(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $userId   = $this->requireThreadParticipantId($request, $threadId);

        // Seul le texte vient du client : un `id` ici écraserait le message d'un autre fil.
        $data = [
            'thread_id'  => $threadId,
            'sender_id'  => $userId,
            'body'       => (string) ($request->json('body') ?? ''),
            'sent_at'    => date('Y-m-d H:i:s'),
        ];

        $saved = $this->messages->saveMessage($data);
        // Comme une réponse web : non lue, et de retour dans la liste de ceux qui l'avaient masquée.
        foreach ($this->messages->findParticipantsByThread($threadId) as $p) {
            if ((int) $p['user_id'] !== $userId) {
                $this->messages->saveParticipant(ThreadVisibility::reveal($p));
            }
        }

        return Response::json($saved, 201);
    }

    /** DELETE /api/v1/messages/{id} */
    public function deleteMessage(Request $request): Response
    {
        $userId = $this->authUserId($request);
        $id     = (int) $request->param('id');

        $message = $this->messages->findMessageById($id);
        if ($message === null) {
            throw new NotFoundException(__('messaging_error_message_not_found'));
        }
        if ((int) $message['sender_id'] !== $userId) {
            throw new ForbiddenException(__('messaging_error_delete_own_messages_only'));
        }

        $this->messages->deleteMessage($id);
        return Response::empty();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Participants
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/messages/threads/{id}/participants */
    public function listParticipants(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $this->requireThreadParticipant($request, $threadId);
        return Response::json($this->messages->findParticipantsByThread($threadId));
    }

    /** POST /api/v1/messages/threads/{id}/participants */
    public function addParticipant(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $actingUserId = $this->requireThreadParticipantId($request, $threadId);

        // Seul user_id vient du client : un `id` ici réécrirait la ligne de participation d'un autre fil.
        $data = [
            'thread_id'  => $threadId,
            'user_id'    => (int) ($request->json('user_id') ?? 0),
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->assertCanAddParticipant($actingUserId, (int) ($data['user_id'] ?? 0));

        return Response::json($this->messages->saveParticipant($data), 201);
    }

    /** GET /api/v1/messages/threads/{id}/participants/{user_id} */
    public function getParticipant(Request $request): Response
    {
        $threadId = (int) $request->param('id');
        $this->requireThreadParticipant($request, $threadId);

        $userId      = (int) $request->param('user_id');
        $participant = $this->messages->findParticipant($threadId, $userId);
        if ($participant === null) {
            throw new NotFoundException(__('error_participant_not_found'));
        }

        return Response::json($participant);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function authUserId(Request $request): int
    {
        $authUser = $request->getAttribute('auth_user');
        return (int) ($authUser['id'] ?? 0);
    }

    /**
     * Charge le thread et vérifie que le porteur du token en est participant.
     * Un thread introuvable et un thread dont on n'est pas participant
     * renvoient tous deux 404, pour ne pas révéler l'existence d'une
     * conversation à laquelle l'appelant est étranger.
     */
    private function requireThreadParticipant(Request $request, int $threadId): array
    {
        $thread = $this->messages->findThreadById($threadId);
        if ($thread === null) {
            throw new NotFoundException(__('messaging_error_thread_not_found'));
        }
        $userId = $this->authUserId($request);
        if ($this->messages->findParticipant($threadId, $userId) === null) {
            throw new NotFoundException(__('messaging_error_thread_not_found'));
        }
        return $thread;
    }

    private function requireThreadParticipantId(Request $request, int $threadId): int
    {
        $this->requireThreadParticipant($request, $threadId);
        return $this->authUserId($request);
    }

    /**
     * Vérifie que la cible partage au moins un store avec l'appelant avant
     * de l'ajouter à un thread. Sans ce garde, n'importe quel user_id valide
     * pouvait être ajouté à une conversation, quel que soit son store.
     */
    private function assertCanAddParticipant(int $actingUserId, int $targetUserId): void
    {
        if ($targetUserId <= 0 || $targetUserId === $actingUserId) {
            return;
        }
        $actingStoreIds = array_map(fn($su) => (int) $su['store_id'], $this->storeUsers->findByUser($actingUserId));
        $targetStoreIds = array_map(fn($su) => (int) $su['store_id'], $this->storeUsers->findByUser($targetUserId));
        if (array_intersect($actingStoreIds, $targetStoreIds) === []) {
            throw new NotFoundException(__('error_participant_not_found'));
        }
    }
}
