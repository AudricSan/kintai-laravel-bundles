<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck\Services;

use kintai\Contract\Services\Mailer;
use kintai\Contract\Services\TranslationService;
use kintai\Contract\Services\UserSelection;

/**
 * Envoi du planning de demain au patron : e-mail court avec le PDF en pièce jointe, aux destinataires du magasin, dans la
 * langue du magasin (repli français). Aucun lien d'action : le patron ne répond pas par e-mail.
 */
final class PlanningMailService
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly TranslationService $translations,
        private readonly PlanningPdfService $pdf,
        private readonly UserSelection $selection,
    ) {}

    /**
     * Adresses à qui envoyer, d'après les réglages du magasin : les employés choisis (relus maintenant, donc adresse à jour,
     * comptes inactifs ou retirés du magasin écartés), puis les adresses saisies avant la sélection. Sans doublon.
     *
     * @param array<string, mixed> $settings
     * @return list<string>
     */
    public function recipients(array $settings, int $storeId = 0): array
    {
        $emails = [];
        $add = static function (string $email) use (&$emails): void {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $emails[strtolower($email)] ??= $email;
            }
        };

        $ids = (array) ($settings['mail_recipient_ids'] ?? []);
        if ($ids !== [] && $storeId > 0) {
            foreach ($this->selection->resolve($storeId, $ids) as $user) {
                $add($user['email']);
            }
        }
        foreach ((array) ($settings['mail_recipients'] ?? []) as $email) {
            $add((string) $email);
        }
        return array_values($emails);
    }

    /**
     * @param array<string, mixed> $store
     * @param array<string, mixed> $check
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $context  validated_by (nom), updated (bool), generated_at
     * @return array{ok: bool, error: ?string, recipients: list<string>}
     */
    public function send(array $store, array $check, array $settings, ?string $pdfBytes, array $context = []): array
    {
        $recipients = $this->recipients($settings, (int) ($store['id'] ?? 0));
        if ($recipients === []) {
            return ['ok' => false, 'error' => 'no_recipients', 'recipients' => []];
        }
        if ($pdfBytes === null) {
            return ['ok' => false, 'error' => 'pdf_failed', 'recipients' => $recipients];
        }

        $locale = $this->pdf->locale($store);
        $previous = $this->translations->getLocale();
        $this->translations->setLocale($locale);
        try {
            $storeName = (string) ($store['name'] ?? 'Kintai');
            $date = (string) ($check['target_date'] ?? '');
            $subject = '[' . $storeName . '] ' . __('planning_check_mail_subject', ['date' => $date]) . (!empty($context['updated']) ? ' — ' . __('planning_check_updated_label') : '');
            $body = $this->body($storeName, $date, $locale, $context);
        } finally {
            $this->translations->setLocale($previous);
        }

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kintai_pc_' . bin2hex(random_bytes(8)) . '.pdf';
        file_put_contents($tmp, $pdfBytes);
        try {
            // Un e-mail par personne : les destinataires ne voient pas les adresses des autres.
            $sent = false;
            foreach ($recipients as $recipient) {
                $sent = $this->mailer->send([$recipient], $subject, $body, [$tmp]) || $sent;
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'recipients' => $recipients];
        } finally {
            if (file_exists($tmp)) {
                unlink($tmp);
            }
        }
        return ['ok' => $sent, 'error' => $sent ? null : 'mail_failed', 'recipients' => $recipients];
    }

    /** @param array<string, mixed> $context */
    private function body(string $storeName, string $date, string $locale, array $context): string
    {
        $title = htmlspecialchars(__('planning_check_mail_subject', ['date' => $date]));
        $intro = htmlspecialchars(__('planning_check_mail_intro', ['store' => $storeName, 'date' => $date]));
        $by = !empty($context['validated_by'])
            ? '<p style="color:#666;">' . htmlspecialchars(__('planning_check_mail_validated_by', ['name' => (string) $context['validated_by']])) . '</p>'
            : '';
        $store = htmlspecialchars($storeName);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$locale}">
        <head><meta charset="UTF-8"><title>{$title}</title></head>
        <body style="font-family:Arial,sans-serif;color:#222;max-width:600px;margin:auto;">
          <h2 style="color:#2c3e50;border-bottom:2px solid #2c3e50;padding-bottom:8px;">{$title}</h2>
          <p>{$intro}</p>
          {$by}
          <p style="margin-top:24px;font-size:11px;color:#999;">Kintai — {$store}</p>
        </body>
        </html>
        HTML;
    }
}
