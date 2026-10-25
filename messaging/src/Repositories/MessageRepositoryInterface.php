<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Messaging\Repositories;

interface MessageRepositoryInterface
{
    // ── Threads ──────────────────────────────────────────────────────────────

    public function findThreadById(int $id): ?array;

    public function saveThread(array $data): array;

    public function deleteThread(int $id): int;

    // ── Messages (corps d'un thread) ─────────────────────────────────────────

    public function findMessageById(int $id): ?array;

    public function findMessagesByThread(int $threadId): array;

    public function saveMessage(array $data): array;

    /** Supprime un seul message. Renvoie le nombre de lignes supprimées. */
    public function deleteMessage(int $id): int;

    /** Supprime tous les messages d'un thread (nettoyage avant suppression du thread). */
    public function deleteMessagesByThread(int $threadId): int;

    /** Supprime tous les participants d'un thread. */
    public function deleteParticipantsByThread(int $threadId): int;

    // ── Participants ──────────────────────────────────────────────────────────

    /** Toutes les participations pour un thread donné. */
    public function findParticipantsByThread(int $threadId): array;

    /** Une participation précise (thread + user). */
    public function findParticipant(int $threadId, int $userId): ?array;

    /** Toutes les participations d'un utilisateur (pour construire la liste de ses threads). */
    public function findParticipationsByUser(int $userId): array;

    public function saveParticipant(array $data): array;

    /** Nombre de threads non-lus pour un utilisateur. */
    public function countUnread(int $userId): int;
}
