<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Services;

use kintai\Contract\Auth\PermissionService;

/**
 * Contrôle d'accès pour les rapports journaliers.
 *
 * S'appuie entièrement sur le RBAC dynamique (PermissionCatalog::CATEGORIES['daily_reports'],
 * portée par store via role_assignments) — y compris pour créer/soumettre un rapport, réservé
 * par défaut aux managers (daily_reports.create/submit font partie de MANAGER_DEFAULTS).
 * Un Owner peut accorder explicitement daily_reports.create (seul, sans .submit ni .approve)
 * à un rôle custom assigné à un employé spécifique sur son store, pour l'autoriser à créer
 * (et éditer son propre brouillon) sans pouvoir le soumettre ni le valider — voir CHANGELOG,
 * le libre-service "tout membre du store" a été retiré à la demande explicite du client.
 *
 * Les paramètres non liés aux droits (activation, destinataires mail, envoi
 * automatique, colonnes affichées, mode cumulatif) restent configurables par
 * store via la colonne JSON `daily_report_settings`.
 */
final class DailyReportPermissionService
{
    private const DEFAULTS = [
        'enabled'               => true,
        'mail_recipient_ids'    => [],
        'mail_recipients'       => [],
        'auto_send_on_validate' => false,
        'auto_validate_time'    => null,
        'reminder_time'         => '15:00',
        'no_report_time'        => '18:00',
        'show_columns'          => ['employee', 'type', 'schedule', 'gross_hours', 'pause', 'net_hours'],
        'cumulative_mode'       => 'per_day',
    ];

    public function __construct(
        private readonly PermissionService $permissions,
    ) {}

    public function getSettings(array $store): array
    {
        $raw = $store['daily_report_settings'] ?? null;
        if (!$raw) {
            return self::DEFAULTS;
        }
        $decoded = json_decode($raw, true);
        return array_merge(self::DEFAULTS, is_array($decoded) ? $decoded : []);
    }

    public function isEnabled(array $store): bool
    {
        return (bool) $this->getSettings($store)['enabled'];
    }

    /**
     * Vérifie si l'utilisateur peut créer un rapport pour ce store : seul le détenteur de
     * `daily_reports.create` (portée store ou globale — managers/Owner par défaut, ou tout
     * rôle custom auquel un Owner a explicitement accordé cette clé) est autorisé. $membership
     * n'intervient plus ici (gardé pour la même signature que les méthodes sœurs) : il n'y a
     * plus de libre-service "tout membre du store".
     */
    public function canCreate(array $user, array $store, ?array $membership): bool
    {
        if (!$this->isEnabled($store)) {
            return false;
        }
        return $this->can($user, 'daily_reports.create', $store);
    }

    /**
     * L'auteur peut modifier son propre brouillon.
     * Le détenteur de `daily_reports.update` peut modifier tout brouillon ou
     * rapport soumis (avant validation finale).
     */
    public function canEdit(array $user, array $store, array $report, ?array $membership): bool
    {
        if (!in_array($report['status'], ['draft', 'submitted'], true)) {
            return false;
        }
        if ($this->can($user, 'daily_reports.update', $store)) {
            return true;
        }
        return $report['status'] === 'draft' && (int) $report['author_id'] === (int) $user['id'];
    }

    /**
     * Réservé au détenteur de `daily_reports.submit` : un employé autorisé uniquement à
     * créer (daily_reports.create sans .submit) peut rédiger et éditer son propre brouillon,
     * mais pas le soumettre lui-même — voir CHANGELOG.
     */
    public function canSubmit(array $user, array $store, array $report, ?array $membership): bool
    {
        if ($report['status'] !== 'draft') {
            return false;
        }
        return $this->can($user, 'daily_reports.submit', $store);
    }

    public function canValidate(array $user, array $store, array $report, ?array $membership): bool
    {
        if ($report['status'] !== 'submitted') {
            return false;
        }
        return $this->can($user, 'daily_reports.approve', $store);
    }

    public function canSendMail(array $user, array $store, array $report, ?array $membership): bool
    {
        if ($report['status'] !== 'validated') {
            return false;
        }
        return $this->can($user, 'daily_reports.approve', $store);
    }

    public function canDelete(array $user, array $store, array $report, ?array $membership): bool
    {
        if ($report['deleted_at'] !== null) {
            return false;
        }
        return $this->can($user, 'daily_reports.delete', $store);
    }

    public function canViewList(array $user, array $store, ?array $membership): bool
    {
        if ($this->can($user, 'daily_reports.view', $store)) {
            return true;
        }
        if ($membership === null) {
            return false;
        }
        return $this->isEnabled($store);
    }

    public function canViewReport(array $user, array $store, array $report, ?array $membership): bool
    {
        if ($this->can($user, 'daily_reports.view', $store)) {
            return true;
        }
        if ($membership === null) {
            return false;
        }
        // Sans la permission de gestion, un membre ne voit que ses propres rapports.
        return (int) $report['author_id'] === (int) $user['id'];
    }

    /** Paramétrage du store (réglages e-mail, colonnes, etc.) : réservé aux gestionnaires. */
    public function canManageSettings(array $user, array $store): bool
    {
        return $this->can($user, 'daily_reports.update', $store);
    }

    private function can(array $user, string $permissionKey, array $store): bool
    {
        return $this->permissions->can($user, $permissionKey, (int) $store['id']);
    }
}
