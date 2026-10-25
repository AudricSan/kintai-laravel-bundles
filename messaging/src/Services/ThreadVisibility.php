<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Messaging\Services;

use kintai\Bundles\Installed\Messaging\Repositories\MessageRepositoryInterface;

/**
 * Supprimer une conversation (web ou API) la masque pour soi seul (audit n°8 de Kintai, décision D5). Avant, n'importe
 * quel participant l'effaçait pour tous. Elle réapparaît si quelqu'un y écrit ensuite (reveal()), et n'est effacée de
 * la base que lorsque tous ses participants l'ont masquée.
 */
final class ThreadVisibility
{
    public function __construct(private readonly MessageRepositoryInterface $messages) {}

    /** @return bool vrai si la conversation a été effacée (plus personne ne la voyait) */
    public function hideFor(int $threadId, int $userId): bool
    {
        $part = $this->messages->findParticipant($threadId, $userId);
        if ($part === null) {
            return false;
        }
        $this->messages->saveParticipant(array_merge($part, ['hidden_at' => date('Y-m-d H:i:s'), 'is_read' => 1]));

        foreach ($this->messages->findParticipantsByThread($threadId) as $p) {
            if (empty($p['hidden_at'])) {
                return false;
            }
        }
        $this->messages->deleteMessagesByThread($threadId);
        $this->messages->deleteParticipantsByThread($threadId);
        $this->messages->deleteThread($threadId);
        return true;
    }

    /** Une participation masquée n'apparaît pas dans la liste. */
    public static function isVisible(array $participation): bool
    {
        return empty($participation['hidden_at']);
    }

    /** Nouveau message : la conversation réapparaît, non lue, chez ceux qui l'avaient masquée. */
    public static function reveal(array $participation): array
    {
        return array_merge($participation, ['is_read' => 0, 'hidden_at' => null]);
    }
}
