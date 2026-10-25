<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Services;

use kintai\Bundles\Installed\DailyReport\Repositories\DailyReportRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;

/**
 * Validation automatique des rapports journaliers non validés.
 *
 * Appelé par le script CLI scripts/auto-validate-reports.php,
 * planifié toutes les heures via Task Scheduler (Windows) ou cron (Linux).
 *
 * Logique :
 *  - Pour chaque store actif, on lit `auto_validate_time` dans les paramètres.
 *  - Si l'heure courante (H) correspond à l'heure configurée, on valide le rapport
 *    de la veille (ou d'une date explicite) qui n'est pas encore en statut 'validated'.
 */
final class DailyReportAutoValidateService
{
    public function __construct(
        private readonly StoreRepositoryInterface $stores,
        private readonly DailyReportRepositoryInterface $reports,
        private readonly UserRepositoryInterface $users,
        private readonly DailyReportPermissionService $permissions,
        private readonly DailyReportPdfService $pdfService,
        private readonly DailyReportMailService $mailService,
    ) {}

    /**
     * Parcourt les stores actifs et valide les rapports non validés de $date
     * pour les stores dont l'heure configurée correspond à $currentHour.
     *
     * @param string      $date        Date YYYY-MM-DD à traiter (typiquement hier)
     * @param string|null $currentHour Heure courante "HH" (ex. "01").
     *                                 Null = pas de filtre horaire, traiter tous les stores.
     * @return array{validated: int, skipped: int, errors: string[]}
     */
    public function run(string $date, ?string $currentHour = null): array
    {
        $result = ['validated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($this->stores->findActive() as $store) {
            $settings = $this->permissions->getSettings($store);

            if (!$this->permissions->isEnabled($store)) {
                $result['skipped']++;
                continue;
            }

            $autoTime = trim((string) ($settings['auto_validate_time'] ?? ''));
            if ($autoTime === '') {
                $result['skipped']++;
                continue;
            }

            if ($currentHour !== null && substr($autoTime, 0, 2) !== $currentHour) {
                $result['skipped']++;
                continue;
            }

            try {
                $report = $this->reports->findByStoreAndDate((int) $store['id'], $date);

                if ($report === null || $report['status'] === 'validated') {
                    $result['skipped']++;
                    continue;
                }

                $this->validateReport($report, $store, $settings);
                $result['validated']++;
            } catch (\Throwable $e) {
                $result['errors'][] = sprintf('Store #%d (%s) : %s', $store['id'], $store['name'] ?? '?', $e->getMessage());
            }
        }

        return $result;
    }

    private function validateReport(array $report, array $store, array $settings): void
    {
        $now = date('Y-m-d H:i:s');

        $updated = array_merge($report, [
            'status'       => 'validated',
            'validated_by' => null,
            'validated_at' => $now,
            'updated_at'   => $now,
        ]);

        $this->reports->save($updated);

        if (!empty($settings['auto_send_on_validate']) && (!empty($settings['mail_recipient_ids']) || !empty($settings['mail_recipients']))) {
            $author   = $this->users->findById((int) $report['author_id']) ?? [];
            $pdfBytes = $this->pdfService->generateBytes($updated, $store, $author);
            $sent     = $this->mailService->send($updated, $store, $pdfBytes, $author);
            if ($sent) {
                $this->reports->save(array_merge($updated, ['mail_sent_at' => $now]));
            } else {
                error_log('[Kintai][DailyReport] Auto-envoi mail échoué (store #' . ($store['id'] ?? '?') . ', rapport #' . ($report['id'] ?? '?') . ')');
            }
        }
    }
}
