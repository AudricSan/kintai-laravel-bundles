<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ResignationReport\Cron;

use kintai\Bundles\Installed\ResignationReport\Services\ResignationDeactivation;
use kintai\Contract\Cron\CronJob;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;

/**
 * Tâche planifiée « resignation-deactivate » (contrat 1.8) : désactive le compte des démissionnaires dont la date de
 * démission est atteinte. À lancer une fois par jour : /cron/run/resignation-deactivate (jeton cron) ou
 * php scripts/cron.php resignation-deactivate.
 */
final class ResignationDeactivateJob implements CronJob
{
    public function __construct(
        private readonly ResignationDeactivation $deactivation,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getName(): string
    {
        return 'resignation-deactivate';
    }

    public function run(Request $request): Response
    {
        $applied = $this->deactivation->applyDue();

        foreach ($applied as $reportId) {
            $this->auditLogger->log($request, 'resignation_report.deactivated', 'resignation_report', $reportId, [
                'reason' => 'resignation_date_reached',
            ]);
        }

        return Response::json(['ok' => true, 'deactivated' => count($applied), 'reports' => $applied]);
    }
}
