<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\StorePhoto\Cron;

use kintai\Bundles\Installed\StorePhoto\Services\PhotoRetentionService;
use kintai\Contract\Cron\CronJob;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;

/**
 * Tâche planifiée « photo-retention » (contrat 1.8) : lancée par un planificateur externe via
 * /cron/run/photo-retention?token=… (jeton cron créé par l'Owner). Voir PhotoRetentionService.
 */
final class PhotoRetentionJob implements CronJob
{
    public function __construct(private readonly PhotoRetentionService $retention) {}

    public function getName(): string
    {
        return 'photo-retention';
    }

    public function run(Request $request): Response
    {
        return Response::json(['ok' => true] + $this->retention->run());
    }
}
