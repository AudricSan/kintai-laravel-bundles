<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Services;

use kintai\Contract\Data\ShiftRepositoryInterface;
use kintai\Contract\Data\ShiftTypeRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Services\PdfCjkFontResolver;
use kintai\Contract\Services\TranslationService;
use kintai\Contract\View\ViewRenderer;

final class DailyReportPdfService
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TranslationService $translations,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Génère le PDF en mémoire et retourne les octets bruts.
     *
     * @param string|null $locale  Locale explicite ('fr', 'en', 'ja').
     *                             Null → déduit de la langue de l'auteur puis du store.
     */
    public function generateBytes(array $report, array $store, array $author = [], ?string $locale = null): ?string
    {
        try {
            [$html, $title] = $this->buildHtml($report, $store, $author, $locale);

            $mpdf = $this->buildMpdf();
            $mpdf->SetTitle($title);
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Rend le même template que generateBytes(), mais en HTML brut pour un
     * aperçu navigateur (avec barre d'outils impression/téléchargement) au
     * lieu d'un PDF — voir DailyReportController::previewPdf().
     */
    public function generateHtml(array $report, array $store, array $author = [], ?string $locale = null, ?string $downloadUrl = null): string
    {
        [$html] = $this->buildHtml($report, $store, $author, $locale, $downloadUrl);
        return $html;
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    /**
     * Résout la locale à utiliser pour le rendu.
     * Priorité : override explicite → langue de l'auteur → locale du store → français.
     */
    private function resolveLocale(array $author, array $store, ?string $override = null): string
    {
        $raw  = $override ?? $author['language'] ?? $store['locale'] ?? 'fr';
        $lang = strtolower(substr((string) $raw, 0, 2));
        return match ($lang) {
            'ja'    => 'ja',
            'en'    => 'en',
            default => 'fr',
        };
    }

    /**
     * Rend le template HTML dans la locale choisie, puis restaure la locale courante.
     *
     * @return array{0: string, 1: string}  [html, title]
     */
    private function buildHtml(array $report, array $store, array $author, ?string $localeOverride, ?string $downloadUrl = null): array
    {
        $locale   = $this->resolveLocale($author, $store, $localeOverride);
        $previous = $this->translations->getLocale();
        $this->translations->setLocale($locale);

        try {
            $html  = $this->view->render('daily-report::daily-report-pdf', [
                'report'      => $report,
                'store'       => $store,
                'author'      => $author,
                'locale'      => $locale,
                'shiftRows'   => $this->buildShiftRows($store, $report),
                'downloadUrl' => $downloadUrl,
            ]);
            $title = __('daily_report_title') . ' — ' . ($report['report_date'] ?? '');
        } finally {
            $this->translations->setLocale($previous);
        }

        return [$html, $title];
    }

    /**
     * Récupère et formate les shifts du jour du rapport.
     */
    private function buildShiftRows(array $store, array $report): array
    {
        $storeId = (int) $store['id'];
        $reportDate = $report['report_date'] ?? '';

        if ($reportDate === '') {
            return [];
        }

        // Le rapport du jour J affiche les shifts du jour J+1
        $shiftDate = date('Y-m-d', strtotime($reportDate . ' +1 day'));

        $rawShifts = array_filter(
            $this->shifts->findByDate($storeId, $shiftDate),
            fn($s) => empty($s['deleted_at']) && !empty($s['user_id'])
        );

        $typesMap  = array_column($this->shiftTypes->findByStore($storeId), null, 'id');
        $userCache = [];

        $rows = [];
        foreach ($rawShifts as $s) {
            $uid = (int) $s['user_id'];
            if (!isset($userCache[$uid])) {
                $u               = $this->users->findById($uid);
                $userCache[$uid] = $u
                    ? (trim(($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? '')) ?: ($u['email'] ?? '—'))
                    : '—';
            }
            $tid       = (int) ($s['shift_type_id'] ?? 0);
            $grossMin  = (int) $s['duration_minutes'];
            $pauseMin  = (int) $s['pause_minutes'];
            $rows[] = [
                'employee'  => $userCache[$uid],
                'type'      => $typesMap[$tid]['name'] ?? '—',
                'start'     => substr($s['start_time'] ?? '', 0, 5),
                'end'       => substr($s['end_time']   ?? '', 0, 5),
                'gross_min' => $grossMin,
                'pause_min' => $pauseMin,
                'net_min'   => max(0, $grossMin - $pauseMin),
            ];
        }

        usort($rows, fn($a, $b) => strcmp($a['start'], $b['start']));

        return $rows;
    }

    private function buildMpdf(): \Mpdf\Mpdf
    {
        $tmpDir = storage_path('app/mpdf');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        return new \Mpdf\Mpdf(PdfCjkFontResolver::applyTo([
            'mode'             => 'utf-8',
            'format'           => 'A4',
            'margin_left'      => 15,
            'margin_right'     => 15,
            'margin_top'       => 16,
            'margin_bottom'    => 16,
            'tempDir'          => $tmpDir,
            'useSubstitutions' => true,
        ]));
    }
}
