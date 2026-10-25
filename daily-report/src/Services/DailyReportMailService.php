<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Services;

use kintai\Contract\Services\Mailer;
use kintai\Contract\Services\TranslationService;
use kintai\Contract\Services\UserSelection;

/**
 * Envoi du rapport journalier par e-mail.
 * La locale est résolue dans cet ordre : override explicite → langue de l'auteur → locale du store → 'fr'.
 */
final class DailyReportMailService
{
    public function __construct(
        private readonly DailyReportPermissionService $permissions,
        private readonly Mailer $mailer,
        private readonly TranslationService $translations,
        private readonly UserSelection $selection,
    ) {}

    /**
     * @param string|null $pdfBytes  Octets bruts du PDF (null = pas de pièce jointe)
     * @param array       $author    Données de l'auteur (pour déduire la langue)
     * @param string|null $locale    Locale explicite ('fr', 'en', 'ja') — prioritaire sur l'auteur/store
     */
    public function send(
        array   $report,
        array   $store,
        ?string $pdfBytes = null,
        array   $author   = [],
        ?string $locale   = null,
    ): bool {
        $targets = $this->targets($store, $this->permissions->getSettings($store));

        if ($targets === []) {
            return false;
        }

        // Une langue par destinataire (sa langue de compte), sauf locale explicite ; adresse héritée : langue de l'auteur ou du store.
        $byLocale = [];
        foreach ($targets as $target) {
            $source = $target['language'] !== '' ? ['language' => $target['language']] : $author;
            $byLocale[$this->resolveLocale($source, $store, $locale)][] = $target['email'];
        }

        $tmpFile     = null;
        $attachments = [];

        if ($pdfBytes !== null) {
            $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kintai_dr_' . bin2hex(random_bytes(8)) . '.pdf';
            file_put_contents($tmpFile, $pdfBytes);
            $attachments = [$tmpFile];
        }

        $sentAny  = false;
        $previous = $this->translations->getLocale();
        try {
            foreach ($byLocale as $resolved => $emails) {
                $this->translations->setLocale((string) $resolved);
                try {
                    $storeName  = $store['name'] ?? 'Kintai';
                    $reportDate = $report['report_date'] ?? '';
                    $subject    = '[' . $storeName . '] ' . __('daily_report_title') . ' — ' . $reportDate;
                    $body       = $this->buildBody($report, $store, (string) $resolved);
                } finally {
                    $this->translations->setLocale($previous);
                }

                // Un e-mail par personne : les destinataires ne voient pas les adresses des autres.
                foreach ($emails as $email) {
                    $sentAny = $this->mailer->send([$email], $subject, $body, $attachments) || $sentAny;
                }
            }
        } finally {
            if ($tmpFile !== null && file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        return $sentAny;
    }

    /**
     * Destinataires du magasin : employés choisis (relus à l'envoi, donc adresse à jour et comptes inactifs écartés), puis
     * adresses saisies à la main avant la sélection d'employés. Sans doublon.
     *
     * @param array<string, mixed> $settings
     * @return list<array{email: string, language: string}>
     */
    private function targets(array $store, array $settings): array
    {
        $targets = [];
        $seen    = [];
        $add = static function (string $email, string $language) use (&$targets, &$seen): void {
            $key = strtolower($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false && !isset($seen[$key])) {
                $seen[$key] = true;
                $targets[]  = ['email' => $email, 'language' => $language];
            }
        };

        $ids = (array) ($settings['mail_recipient_ids'] ?? []);
        if ($ids !== [] && isset($store['id'])) {
            foreach ($this->selection->resolve((int) $store['id'], $ids) as $user) {
                $add($user['email'], $user['language']);
            }
        }
        foreach ((array) ($settings['mail_recipients'] ?? []) as $email) {
            $add((string) $email, '');
        }
        return $targets;
    }

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

    private function buildBody(array $report, array $store, string $locale): string
    {
        $storeName = htmlspecialchars($store['name'] ?? '');
        $date      = htmlspecialchars($report['report_date'] ?? '');
        $sales     = number_format((float) ($report['sales_total']    ?? 0), 0, ',', ' ');
        $customers = (int) ($report['customer_count'] ?? 0);
        $labor     = number_format((float) ($report['labor_cost']     ?? 0), 0, ',', ' ');
        $waste     = number_format((float) ($report['waste_total']    ?? 0), 0, ',', ' ');
        $notes     = nl2br(htmlspecialchars($report['notes'] ?? '—'));
        $currency  = htmlspecialchars($store['currency'] ?? '¥');

        // Labels traduits (locale déjà appliquée par send() avant cet appel)
        $lTitle     = htmlspecialchars(__('daily_report_title'));
        $lDate      = htmlspecialchars(__('date'));
        $lSales     = htmlspecialchars(__('daily_report_sales'));
        $lCustomers = htmlspecialchars(__('daily_report_customers'));
        $lLabor     = htmlspecialchars(__('daily_report_labor'));
        $lWaste     = htmlspecialchars(__('daily_report_waste'));
        $lNotes     = htmlspecialchars(__('daily_report_notes'));

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$locale}">
        <head><meta charset="UTF-8"><title>{$lTitle}</title></head>
        <body style="font-family:Arial,sans-serif;color:#222;max-width:600px;margin:auto;">
          <h2 style="color:#2c3e50;border-bottom:2px solid #2c3e50;padding-bottom:8px;">
            {$lTitle} — {$storeName}
          </h2>
          <p style="color:#666;">{$lDate} : <strong>{$date}</strong></p>
          <table style="width:100%;border-collapse:collapse;margin-top:16px;">
            <tr style="background:#eaf2fb;">
              <td style="padding:8px 12px;font-weight:bold;">{$lSales}</td>
              <td style="padding:8px 12px;text-align:right;">{$sales} {$currency}</td>
            </tr>
            <tr>
              <td style="padding:8px 12px;font-weight:bold;">{$lCustomers}</td>
              <td style="padding:8px 12px;text-align:right;">{$customers}</td>
            </tr>
            <tr style="background:#eaf2fb;">
              <td style="padding:8px 12px;font-weight:bold;">{$lLabor}</td>
              <td style="padding:8px 12px;text-align:right;">{$labor} {$currency}</td>
            </tr>
            <tr>
              <td style="padding:8px 12px;font-weight:bold;">{$lWaste}</td>
              <td style="padding:8px 12px;text-align:right;">{$waste} {$currency}</td>
            </tr>
            <tr style="background:#eaf2fb;">
              <td style="padding:8px 12px;font-weight:bold;">{$lNotes}</td>
              <td style="padding:8px 12px;">{$notes}</td>
            </tr>
          </table>
          <p style="margin-top:24px;font-size:11px;color:#999;">
            Kintai — {$storeName}
          </p>
        </body>
        </html>
        HTML;
    }
}
