<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\StorePhoto\Repositories;

interface StorePhotoRepositoryInterface
{
    public function findSubmissionById(int $id): ?array;
    public function findSubmissionsByStore(int $storeId): array;
    public function findTodaySubmission(int $storeId, string $date): ?array;
    public function findAllSubmissions(?array $storeIds = null, int $limit = 50): array;
    public function findImagesBySubmission(int $submissionId): array;
    public function findImageById(int $id): ?array;
    public function saveSubmission(array $data): array;
    public function saveImage(array $data): array;
    public function deleteSubmission(int $id): int;
    public function deleteImage(int $id): int;
    public function getExpiredSubmissions(int $retentionDays): array;
    public function getPendingCompression(int $retentionDays): array;
}
