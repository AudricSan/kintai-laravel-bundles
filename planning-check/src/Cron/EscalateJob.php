<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck\Cron;

use kintai\Bundles\Installed\PlanningCheck\Services\PlanningCheckService;
use kintai\Contract\Cron\CronJob;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;

/**
 * Tâche planifiée « planning-check-escalate » (contrat 1.8) : à l'heure limite d'un magasin, une validation du planning de
 * demain encore non soumise est escaladée (valideurs et Owners prévenus, aucun PDF envoyé). À lancer toutes les 15 à
 * 30 minutes autour de l'heure limite ; sans cron, la première page chargée après l'heure limite fait le même travail
 * (voir PlanningCheckBundle::pageOverlays()). Idempotente : une validation n'est escaladée qu'une fois.
 */
final class EscalateJob implements CronJob
{
    public function __construct(
        private readonly PlanningCheckService $service,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getName(): string
    {
        return 'planning-check-escalate';
    }

    public function run(Request $request): Response
    {
        $escalated = $this->service->escalate(new \DateTimeImmutable('now'));

        if ($escalated !== []) {
            $this->auditLogger->log($request, 'cron.planning_check_escalated', 'system', null, ['checks' => $escalated]);
        }

        return Response::json(['ok' => true, 'escalated' => count($escalated)]);
    }
}
