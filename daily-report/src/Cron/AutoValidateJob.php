<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Cron;

use kintai\Contract\Cron\CronJob;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportAutoValidateService;

/**
 * Tâche planifiée « auto-validate » (contrat 1.8) : valide les rapports journaliers restés non validés après l'heure
 * limite de leur magasin. Lancée par /cron/run/auto-validate (jeton cron) ou php scripts/cron.php auto-validate.
 * Paramètres : date (Y-m-d, défaut : hier), all=1 (tous les magasins, sans filtre horaire).
 */
final class AutoValidateJob implements CronJob
{
    public function __construct(
        private readonly DailyReportAutoValidateService $autoValidate,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getName(): string
    {
        return 'auto-validate';
    }

    public function run(Request $request): Response
    {
        set_time_limit(0);

        $rawDate    = $request->query('date', '');
        $targetDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)
            ? $rawDate
            : date('Y-m-d', strtotime('yesterday'));

        $all         = $request->query('all', '0') === '1';
        $currentHour = $all ? null : date('H');

        $result = $this->autoValidate->run($targetDate, $currentHour);

        $this->auditLogger->log($request, 'cron.auto_validated', 'system', null, [
            'date'      => $targetDate,
            'validated' => $result['validated'],
            'skipped'   => $result['skipped'],
            'errors'    => $result['errors'] ?? 0,
        ]);

        return Response::json([
            'ok'        => true,
            'date'      => $targetDate,
            'validated' => $result['validated'],
            'skipped'   => $result['skipped'],
            'errors'    => $result['errors'],
        ]);
    }
}
