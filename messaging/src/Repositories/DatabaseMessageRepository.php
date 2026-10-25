<?php
declare(strict_types=1);

namespace kintai\Bundles\Installed\Messaging\Repositories;

use kintai\Bundles\Installed\Messaging\Models\MessageThread as EloquentMessageThread;
use kintai\Bundles\Installed\Messaging\Models\ThreadMessage as EloquentThreadMessage;
use kintai\Bundles\Installed\Messaging\Models\ThreadParticipant as EloquentThreadParticipant;

final class DatabaseMessageRepository implements MessageRepositoryInterface
{
    public function __construct() {}

    // ── Threads ──

    public function findThreadById(int $id): ?array
    {
        $record = EloquentMessageThread::find($id);
        return $record ? $record->toArray() : null;
    }

    public function saveThread(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentMessageThread::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentMessageThread::create($data);
        }
        return $record->toArray();
    }

    public function deleteThread(int $id): int
    {
        $record = EloquentMessageThread::find($id);
        return $record ? ($record->delete() ? 1 : 0) : 0;
    }

    // ── Messages ──

    public function findMessageById(int $id): ?array
    {
        $record = EloquentThreadMessage::find($id);
        return $record ? $record->toArray() : null;
    }

    public function findMessagesByThread(int $threadId): array
    {
        return EloquentThreadMessage::where('thread_id', $threadId)->get()->toArray();
    }

    public function saveMessage(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentThreadMessage::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentThreadMessage::create($data);
        }
        return $record->toArray();
    }

    public function deleteMessage(int $id): int
    {
        $record = EloquentThreadMessage::find($id);
        return $record ? ($record->delete() ? 1 : 0) : 0;
    }

    public function deleteMessagesByThread(int $threadId): int
    {
        return EloquentThreadMessage::where('thread_id', $threadId)->delete();
    }

    public function deleteParticipantsByThread(int $threadId): int
    {
        return EloquentThreadParticipant::where('thread_id', $threadId)->delete();
    }

    // ── Participants ──

    public function findParticipantsByThread(int $threadId): array
    {
        return EloquentThreadParticipant::where('thread_id', $threadId)->get()->toArray();
    }

    public function findParticipant(int $threadId, int $userId): ?array
    {
        $record = EloquentThreadParticipant::where('thread_id', $threadId)
            ->where('user_id', $userId)
            ->first();
        return $record ? $record->toArray() : null;
    }

    public function findParticipationsByUser(int $userId): array
    {
        return EloquentThreadParticipant::where('user_id', $userId)->get()->toArray();
    }

    public function saveParticipant(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentThreadParticipant::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentThreadParticipant::create($data);
        }
        return $record->toArray();
    }

    public function countUnread(int $userId): int
    {
        return EloquentThreadParticipant::where('user_id', $userId)
            ->where('is_read', 0)
            ->whereNull('hidden_at')
            ->count();
    }
}
