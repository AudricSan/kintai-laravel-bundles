<?php

declare(strict_types=1);


namespace kintai\Bundles\Installed\TimeOff\Repositories;

interface TimeoffRequestRepositoryInterface
{
    /**
     * Trouve une demande de congé par son ID.
     */
    public function findById(int $id): ?array;

    /**
     * Retourne toutes les demandes de congé d'un utilisateur.
     */
    public function findByUser(int $userId): array;

    /**
     * Retourne toutes les demandes de congé d'un store.
     */
    public function findByStore(int $storeId): array;

    /**
     * Retourne les demandes de congé d'un store filtrées par statut.
     * @param string $status pending|approved|refused|cancelled
     */
    public function findByStatus(int $storeId, string $status): array;

    /**
     * Retourne toutes les demandes de congé (sans filtre).
     */
    public function findAll(): array;

    /**
     * Crée ou met à jour une demande de congé.
     */
    public function save(array $data): array;

    /**
     * Supprime une demande de congé par son ID.
     * @return int Nombre de lignes supprimées (0 ou 1).
     */
    public function delete(int $id): int;
}
