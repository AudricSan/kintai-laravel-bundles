<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck\Services;

use kintai\Bundles\Installed\PlanningCheck\Repositories\PlanningCheckRepositoryInterface as Repo;
use kintai\Contract\Data\StoreRepositoryInterface;

/**
 * Décide, pour un utilisateur et un instant, quelle fenêtre de page proposer : la validation à effectuer (valideur), le
 * planning de demain à soumettre (manager), ou la barre « vous modifiez le planning de demain » pendant la correction. Le
 * résultat est mémorisé le temps de la requête : `visibleWhen` et `data` des fenêtres ne recalculent pas.
 *
 * Repli sans cron : la première page chargée après l'heure limite d'un magasin déclenche l'escalade (idempotente), au plus
 * une fois par requête.
 */
final class PlanningCheckOverlayResolver
{
    public const KIND_APPROVE = 'approve';
    public const KIND_SUBMIT = 'submit';
    public const KIND_EDITING = 'editing';

    /** @var array<string, array<string, mixed>|null> */
    private array $memo = [];
    private bool $escalationTried = false;

    public function __construct(
        private readonly Repo $repo,
        private readonly StoreRepositoryInterface $stores,
        private readonly PlanningCheckService $service,
        private readonly PlanningShiftRows $rows,
    ) {}

    /**
     * @param array<string, mixed> $context Contexte d'une fenêtre de page : user, now, store_ids, can()
     * @return array<string, mixed>|null kind, store, check, date, late, day, rejected_reason, others…
     */
    public function resolve(array $context): ?array
    {
        $user = (array) $context['user'];
        $now = $context['now'] instanceof \DateTimeImmutable ? $context['now'] : new \DateTimeImmutable('now');
        $key = (int) ($user['id'] ?? 0) . '@' . $now->getTimestamp();
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }
        return $this->memo[$key] = $this->compute($context, $now);
    }

    /** @param array<string, mixed> $context @return array<string, mixed>|null */
    private function compute(array $context, \DateTimeImmutable $now): ?array
    {
        $can = $context['can'];
        $userId = (int) ($context['user']['id'] ?? 0);
        $storeIds = array_map('intval', (array) $context['store_ids']);
        // Un valideur peut ne pas être membre du magasin (Owner) : on regarde aussi les magasins activés.
        $candidates = array_values(array_unique(array_merge($storeIds, $this->repo->enabledStoreIds())));

        // 1) Une validation à effectuer.
        $approvable = array_values(array_filter($candidates, static fn(int $id): bool => (bool) $can('planning_check.approve', $id)));
        foreach ($this->repo->submittedForStores($approvable) as $check) {
            $store = $this->stores->findById($check['store_id']);
            if ($store === null) {
                continue;
            }
            $settings = $this->repo->settings($check['store_id']);
            return [
                'kind'     => self::KIND_APPROVE,
                'store'    => $store,
                'check'    => $check,
                'date'     => $check['target_date'],
                'day'      => $this->rows->forDay($check['store_id'], (string) $check['target_date'], $settings),
                'late'     => false,
                'count'    => count($approvable) > 0 ? count($this->repo->submittedForStores($approvable)) : 1,
                'settings' => $settings,
            ];
        }

        // 2) Le planning de demain à soumettre, ou la barre pendant la correction.
        $submittable = array_values(array_filter($storeIds, static fn(int $id): bool => (bool) $can('planning_check.submit', $id)));
        foreach ($submittable as $storeId) {
            $store = $this->stores->findById($storeId);
            if ($store === null) {
                continue;
            }
            $state = $this->state($store, $now);
            // « Revérifier le planning de demain » : la fenêtre est forcée pour cet utilisateur, même hors fenêtre horaire ou un jour non ouvré.
            if (!$state['due'] && !empty($state['settings']['enabled']) && PlanningCheckForce::isForced($storeId, (string) $state['date'])) {
                $state['check'] = $this->repo->ensure($storeId, (string) $state['date']);
                $state['due'] = true;
            }
            $check = $state['check'];
            if (!$state['due'] || $check === null || !in_array($check['status'], [Repo::STATUS_PENDING, Repo::STATUS_REJECTED, Repo::STATUS_ESCALATED], true)) {
                continue;
            }

            $editing = $check['editing_until'] !== null && strtotime((string) $check['editing_until']) > $now->getTimestamp();
            if ($editing) {
                if ((int) $check['editing_by'] === $userId) {
                    return ['kind' => self::KIND_EDITING, 'store' => $store, 'check' => $check, 'date' => $state['date'], 'late' => $state['late'], 'settings' => $state['settings']];
                }
                continue;
            }

            return [
                'kind'     => self::KIND_SUBMIT,
                'store'    => $store,
                'check'    => $check,
                'date'     => $state['date'],
                'late'     => $state['late'] || $check['status'] === Repo::STATUS_ESCALATED,
                'day'      => $this->rows->forDay($storeId, $state['date'], $state['settings']),
                'settings' => $state['settings'],
            ];
        }
        return null;
    }

    /** @param array<string, mixed> $store @return array<string, mixed> */
    private function state(array $store, \DateTimeImmutable $now): array
    {
        $state = $this->service->state($store, $now);
        // Repli sans cron : l'heure limite est passée et personne n'a répondu.
        if (!$this->escalationTried && $state['due'] && $state['late'] && ($state['check']['status'] ?? '') === Repo::STATUS_PENDING) {
            $this->escalationTried = true;
            try {
                $this->service->escalate($now);
                $state = $this->service->state($store, $now);
            } catch (\Throwable) {
                // L'escalade ne doit jamais empêcher d'afficher la fenêtre.
            }
        }
        return $state;
    }
}
