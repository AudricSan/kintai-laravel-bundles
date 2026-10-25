<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck\Services;

/**
 * Règles de temps de la validation du planning, toutes évaluées dans le fuseau reçu (celui de l'application) : quel jour est visé (le
 * lendemain), à partir de quand la fenêtre s'ouvre, quand elle est en retard, et si ce jour fait partie des jours
 * concernés. Fonctions pures, sans accès aux données.
 */
final class PlanningCheckPolicy
{
    /** Durée pendant laquelle « Non, modifier le planning » met la fenêtre en retrait (le temps de corriger les shifts). */
    public const EDITING_MINUTES = 45;

    public static function localNow(string $timezone, \DateTimeImmutable $now): \DateTimeImmutable
    {
        try {
            return $now->setTimezone(new \DateTimeZone($timezone !== '' ? $timezone : 'UTC'));
        } catch (\Throwable) {
            return $now->setTimezone(new \DateTimeZone('UTC'));
        }
    }

    /** Le lendemain, selon le fuseau du magasin (Y-m-d). */
    public static function targetDate(string $timezone, \DateTimeImmutable $now): string
    {
        return self::localNow($timezone, $now)->modify('+1 day')->format('Y-m-d');
    }

    /** La fenêtre est ouverte : l'heure locale a atteint l'heure de début d'affichage. */
    public static function windowOpen(array $settings, string $timezone, \DateTimeImmutable $now): bool
    {
        return self::localNow($timezone, $now)->format('H:i') >= (string) $settings['window_start_time'];
    }

    /** L'heure limite est passée : la validation est en retard. */
    public static function isLate(array $settings, string $timezone, \DateTimeImmutable $now): bool
    {
        return self::localNow($timezone, $now)->format('H:i') >= (string) $settings['deadline_time'];
    }

    /** Le magasin est censé travailler ce jour-là (jour de la semaine coché dans les réglages). */
    public static function isWorkingDay(array $settings, string $date): bool
    {
        $iso = (int) (new \DateTimeImmutable($date))->format('N');
        return in_array($iso, array_map('intval', (array) $settings['weekdays']), true);
    }

    /** Empreinte des shifts d'un jour : change dès qu'un shift est ajouté, supprimé ou modifié. */
    public static function shiftsHash(array $shifts): string
    {
        $rows = [];
        foreach ($shifts as $shift) {
            if (!empty($shift['deleted_at'])) {
                continue;
            }
            $rows[] = implode('|', [
                (int) ($shift['id'] ?? 0), (int) ($shift['user_id'] ?? 0), (int) ($shift['shift_type_id'] ?? 0),
                substr((string) ($shift['start_time'] ?? ''), 0, 5), substr((string) ($shift['end_time'] ?? ''), 0, 5),
                (int) ($shift['pause_minutes'] ?? 0),
            ]);
        }
        sort($rows);
        return hash('sha256', implode("\n", $rows));
    }
}
