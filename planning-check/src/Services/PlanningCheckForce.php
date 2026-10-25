<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck\Services;

/**
 * « Revérifier le planning de demain » : l'utilisateur force l'ouverture de la fenêtre de vérification habituelle, même hors
 * fenêtre horaire ou un jour non ouvré. Le choix est mémorisé dans sa session, par magasin et pour un jour précis : il ne
 * concerne que lui, ne survit pas à la session et tombe tout seul dès que le jour visé n'est plus « demain ».
 *
 * Il se lève quand la vérification est soumise ; tant qu'elle ne l'est pas, la fenêtre revient à chaque page, exactement
 * comme la fenêtre normale.
 */
final class PlanningCheckForce
{
    private const KEY = 'planning_check_forced';

    public static function mark(int $storeId, string $date): void
    {
        $_SESSION[self::KEY][$storeId] = $date;
    }

    public static function isForced(int $storeId, string $date): bool
    {
        return (($_SESSION[self::KEY][$storeId] ?? null) === $date);
    }

    public static function clear(int $storeId): void
    {
        unset($_SESSION[self::KEY][$storeId]);
    }
}
