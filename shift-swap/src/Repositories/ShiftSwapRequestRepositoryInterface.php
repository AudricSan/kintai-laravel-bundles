<?php

declare(strict_types=1);


namespace kintai\Bundles\Installed\ShiftSwap\Repositories;

interface ShiftSwapRequestRepositoryInterface
{
    /**
     * Trouve une demande d'échange par son ID.
     */
    public function findById(int $id): ?array;

    /**
     * Retourne toutes les demandes d'échange d'un store.
     */
    public function findByStore(int $storeId): array;

    /**
     * Retourne les demandes d'échange initiées par un utilisateur.
     */
    public function findByRequester(int $requesterId): array;

    /**
     * Retourne les demandes d'échange où l'utilisateur est la cible.
     */
    public function findByTarget(int $targetId): array;

    /**
     * Retourne les demandes d'échange filtrées par statut pour un store.
     * @param string $status pending|accepted|refused|cancelled
     */
    public function findByStatus(int $storeId, string $status): array;

    /**
     * Retourne toutes les demandes d'échange (sans filtre).
     */
    public function findAll(): array;

    /**
     * Crée ou met à jour une demande d'échange.
     */
    public function save(array $data): array;

    /**
     * Supprime une demande d'échange par son ID.
     * @return int Nombre de lignes supprimées (0 ou 1).
     */
    public function delete(int $id): int;
}
