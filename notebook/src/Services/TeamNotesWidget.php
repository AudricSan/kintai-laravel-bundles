<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Notebook\Services;

use kintai\Bundles\Installed\Notebook\Repositories\NotebookEntryRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;

/**
 * Données du widget « Notes d'équipe » des tableaux de bord gérant et employé (point d'extension dashboardWidgets,
 * contrat 1.4) : notes non expirées visibles pour les magasins donnés (plus celles de toute l'organisation),
 * épinglées d'abord puis les plus récentes, avec le nom de l'auteur et du magasin.
 */
final class TeamNotesWidget
{
    public const LIMIT = 5;

    public function __construct(
        private readonly NotebookEntryRepositoryInterface $notes,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @param list<int>          $storeIds  Magasins visibles (gérés, ou ceux de l'employé)
     * @param array<int, string> $storesMap id => nom
     * @return list<array<string, mixed>>
     */
    public function entries(array $storeIds, array $storesMap, ?string $now = null): array
    {
        $now ??= date('Y-m-d H:i:s');
        $entries = array_values(array_filter(
            $this->notes->findVisibleForStores($storeIds),
            fn($n) => empty($n['expires_at']) || $n['expires_at'] > $now
        ));
        usort($entries, function ($a, $b) {
            $pinCmp = (int) ($b['pinned'] ?? 0) <=> (int) ($a['pinned'] ?? 0);
            return $pinCmp !== 0 ? $pinCmp : strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
        $entries = array_slice($entries, 0, self::LIMIT);

        return array_map(function (array $n) use ($storesMap): array {
            $author = $n['author_id'] !== null ? $this->users->findById((int) $n['author_id']) : null;
            $n['author_name'] = $author !== null
                ? trim(($author['last_name'] ?? '') . ' ' . ($author['first_name'] ?? '')) ?: ($author['email'] ?? '#' . $n['author_id'])
                : null;
            $n['store_name'] = $n['store_id'] !== null ? ($storesMap[(int) $n['store_id']] ?? null) : null;
            return $n;
        }, $entries);
    }
}
