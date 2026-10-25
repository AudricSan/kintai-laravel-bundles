<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck\Services;

use kintai\Contract\Services\PdfCjkFontResolver;
use kintai\Contract\Services\TranslationService;
use kintai\Contract\View\ViewRenderer;

/**
 * PDF des shifts du lendemain (mPDF, A4 paysage), dans la langue du magasin (repli français), avec la police japonaise
 * quand il le faut. Même mécanique que le PDF du rapport journalier. Le contenu vient de PlanningShiftRows.
 */
class PlanningPdfService
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TranslationService $translations,
    ) {}

    /**
     * @param array<string, mixed> $store
     * @param array<string, mixed> $check   validation (target_date, validated_at…)
     * @param array<string, mixed> $day     résultat de PlanningShiftRows::forDay()
     * @param array<string, mixed> $context validated_by (nom), updated (bool : version mise à jour), generated_at
     * @return string|null octets du PDF, ou null si la génération échoue
     */
    public function generateBytes(array $store, array $check, array $day, array $context = []): ?string
    {
        try {
            [$html, $title] = $this->buildHtml($store, $check, $day, $context);
            $mpdf = $this->buildMpdf();
            $mpdf->SetTitle($title);
            $mpdf->WriteHTML($html);
            return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Même rendu en HTML (aperçu navigateur, tests). */
    public function generateHtml(array $store, array $check, array $day, array $context = []): string
    {
        return $this->buildHtml($store, $check, $day, $context)[0];
    }

    /** Langue du document : celle du magasin, sinon le français. */
    public function locale(array $store): string
    {
        return match (strtolower(substr((string) ($store['locale'] ?? 'fr'), 0, 2))) {
            'ja' => 'ja',
            'en' => 'en',
            default => 'fr',
        };
    }

    /** @return array{0: string, 1: string} [html, titre] */
    private function buildHtml(array $store, array $check, array $day, array $context): array
    {
        $locale = $this->locale($store);
        $previous = $this->translations->getLocale();
        $this->translations->setLocale($locale);
        try {
            $html = $this->view->render('planning-check::pdf', [
                'store'   => $store,
                'check'   => $check,
                'day'     => $day,
                'context' => $context,
                'locale'  => $locale,
            ]);
            $title = __('planning_check_pdf_title', ['date' => (string) ($check['target_date'] ?? '')]);
        } finally {
            $this->translations->setLocale($previous);
        }
        return [$html, $title];
    }

    private function buildMpdf(): \Mpdf\Mpdf
    {
        $tmpDir = storage_path('app/mpdf');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        return new \Mpdf\Mpdf(PdfCjkFontResolver::applyTo([
            'mode'             => 'utf-8',
            'format'           => 'A4-L',
            'margin_left'      => 12,
            'margin_right'     => 12,
            'margin_top'       => 14,
            'margin_bottom'    => 14,
            'tempDir'          => $tmpDir,
            'useSubstitutions' => true,
        ]));
    }
}
