<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck\Services;

use kintai\Bundles\Installed\PlanningCheck\Repositories\PlanningCheckRepositoryInterface as Repo;
use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Services\NotificationService;

/**
 * Cycle de la validation du planning de demain, sur le modèle du rapport journalier : le manager qui a vérifié les shifts
 * du lendemain les SOUMET ; un détenteur de `planning_check.approve` les VALIDE, et c'est à la validation que le PDF est
 * généré et envoyé au patron. Il peut aussi REFUSER avec un motif (retour au soumetteur). Sans soumission à l'heure limite,
 * les valideurs et les Owners sont prévenus (ESCALADE), sans aucun envoi automatique.
 *
 * Chaque méthode renvoie ['ok' => bool, 'error' => ?string, 'check' => ?array] ; `error` est un suffixe de clé de
 * traduction (planning_check_err_{error}). Les permissions et la portée sont contrôlées par les routes ; ce service
 * garde les règles métier (état, destinataires, idempotence).
 */
final class PlanningCheckService
{
    public function __construct(
        private readonly Repo $repo,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $members,
        private readonly UserRepositoryInterface $users,
        private readonly PermissionService $permissions,
        private readonly NotificationService $notifications,
        private readonly PlanningShiftRows $rows,
        private readonly PlanningPdfService $pdf,
        private readonly PlanningMailService $mail,
    ) {}

    // ── Lecture ─────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * État de la validation d'un magasin à cet instant : réglages, jour visé, validation en cours (créée au besoin),
     * retard, et si une fenêtre doit être proposée au soumetteur.
     *
     * @param array<string, mixed> $store
     * @return array{settings: array<string, mixed>, date: string, check: array<string, mixed>|null, late: bool, due: bool}
     */
    public function state(array $store, \DateTimeImmutable $now): array
    {
        $storeId = (int) $store['id'];
        $settings = $this->repo->settings($storeId);
        // Fuseau de l'application (APP_TIMEZONE), pas celui du magasin qui vaut « UTC » par défaut.
        $timezone = date_default_timezone_get();
        $date = PlanningCheckPolicy::targetDate($timezone, $now);

        $due = !empty($settings['enabled'])
            && PlanningCheckPolicy::isWorkingDay($settings, $date)
            && PlanningCheckPolicy::windowOpen($settings, $timezone, $now);

        return [
            'settings' => $settings,
            'date'     => $date,
            'check'    => $due ? $this->repo->ensure($storeId, $date) : $this->repo->findFor($storeId, $date),
            'late'     => PlanningCheckPolicy::isLate($settings, $timezone, $now),
            'due'      => $due,
        ];
    }

    /** La version envoyée n'est plus celle du planning : un renvoi est proposé. @param array<string, mixed> $check */
    public function needsResend(array $check): bool
    {
        if ($check['status'] !== Repo::STATUS_VALIDATED) {
            return false;
        }
        if ($check['send_status'] === 'failed') {
            return true;
        }
        return $check['send_status'] === 'sent'
            && PlanningCheckPolicy::shiftsHash($this->rows->raw((int) $check['store_id'], (string) $check['target_date'])) !== (string) $check['shifts_hash'];
    }

    /**
     * Utilisateurs qui peuvent valider ce magasin : les membres détenteurs de `planning_check.approve` et les Owners.
     *
     * @return list<int>
     */
    public function approvers(int $storeId): array
    {
        $ids = [];
        foreach ($this->members->findByStore($storeId) as $membership) {
            $user = $this->users->findById((int) $membership['user_id']);
            if ($user !== null && !empty($user['is_active']) && $this->permissions->can($user, 'planning_check.approve', $storeId)) {
                $ids[(int) $user['id']] = true;
            }
        }
        foreach ($this->permissions->ownerUserIds() as $ownerId) {
            $ids[(int) $ownerId] = true;
        }
        return array_keys($ids);
    }

    // ── Actions ─────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Le manager soumet : « le planning de demain est correct ». Un seul manager par magasin et par jour suffit ; sans
     * aucun shift, il faut confirmer explicitement « aucun shift demain ».
     *
     * @return array{ok: bool, error: ?string, check: array<string, mixed>|null}
     */
    public function submit(int $storeId, string $date, int $userId, bool $confirmEmpty, \DateTimeImmutable $now): array
    {
        $store = $this->stores->findById($storeId);
        $settings = $this->repo->settings($storeId);
        if ($store === null || empty($settings['enabled'])) {
            return $this->fail('not_enabled');
        }
        $check = $this->repo->ensure($storeId, $date);
        if (!in_array($check['status'], [Repo::STATUS_PENDING, Repo::STATUS_REJECTED, Repo::STATUS_ESCALATED], true)) {
            return $this->fail('already_submitted', $check);
        }
        $count = count($this->rows->raw($storeId, $date));
        if ($count === 0 && !$confirmEmpty) {
            return $this->fail('no_shifts', $check);
        }

        $check = $this->repo->update($check['id'], [
            'status'          => Repo::STATUS_SUBMITTED,
            'confirmed_empty' => $count === 0 ? 1 : 0,
            'submitted_by'    => $userId,
            'submitted_at'    => date('Y-m-d H:i:s', $now->getTimestamp()),
            'rejected_by'     => null,
            'rejected_at'     => null,
            'reject_reason'   => null,
            'editing_by'      => null,
            'editing_until'   => null,
            'shifts_hash'     => PlanningCheckPolicy::shiftsHash($this->rows->raw($storeId, $date)),
        ]);

        $this->notify($this->approvers($storeId), 'planning_check_submitted', $store, $check, [], $userId);
        return ['ok' => true, 'error' => null, 'check' => $check];
    }

    /** « Non, modifier le planning » : la fenêtre se met en retrait un moment, le temps de corriger les shifts. */
    public function startEditing(int $storeId, string $date, int $userId, \DateTimeImmutable $now): void
    {
        $check = $this->repo->ensure($storeId, $date);
        if (in_array($check['status'], [Repo::STATUS_VALIDATED, Repo::STATUS_SUBMITTED], true)) {
            return;
        }
        $this->repo->update($check['id'], [
            'editing_by'    => $userId,
            'editing_until' => date('Y-m-d H:i:s', $now->getTimestamp() + PlanningCheckPolicy::EDITING_MINUTES * 60),
        ]);
    }

    /** « Revenir à la validation » : la correction est terminée, la fenêtre se réaffiche. */
    public function resumeValidation(int $storeId, string $date): void
    {
        $check = $this->repo->findFor($storeId, $date);
        if ($check !== null && $check['editing_until'] !== null) {
            $this->repo->update($check['id'], ['editing_by' => null, 'editing_until' => null]);
        }
    }

    /**
     * Le valideur approuve : le PDF est généré et envoyé au patron. Sans destinataire valide, rien n'est validé (message
     * clair) ; un échec d'envoi laisse la validation faite mais marque l'envoi « failed » (bouton « Réessayer »).
     *
     * @return array{ok: bool, error: ?string, check: array<string, mixed>|null}
     */
    public function approve(int $checkId, int $userId, \DateTimeImmutable $now): array
    {
        $check = $this->repo->find($checkId);
        if ($check === null) {
            return $this->fail('not_found');
        }
        if ($check['status'] !== Repo::STATUS_SUBMITTED) {
            return $this->fail('bad_status', $check);
        }
        $store = $this->stores->findById($check['store_id']);
        if ($store === null) {
            return $this->fail('not_found', $check);
        }
        $settings = $this->repo->settings($check['store_id']);
        if ($this->mail->recipients($settings, (int) $check['store_id']) === []) {
            return $this->fail('no_recipients', $check);
        }

        $check = $this->repo->update($check['id'], [
            'status'       => Repo::STATUS_VALIDATED,
            'validated_by' => $userId,
            'validated_at' => date('Y-m-d H:i:s', $now->getTimestamp()),
        ]);
        // Un planning rouvert puis revalidé après un premier envoi part comme version mise à jour.
        $sent = $this->deliver($store, $check, $settings, $userId, $check['send_status'] === 'sent', $now);

        $this->notify($check['submitted_by'] !== null ? [$check['submitted_by']] : [], 'planning_check_validated', $store, $check, [], $userId);
        if (!$sent['ok']) {
            $this->notify(array_unique(array_filter([$check['submitted_by'], $userId])), 'planning_check_failed', $store, $sent['check'], [], null);
        }
        return ['ok' => true, 'error' => null, 'check' => $sent['check']];
    }

    /**
     * Renvoie le PDF d'un planning déjà validé (version mise à jour, ou nouvel essai après un échec).
     *
     * @return array{ok: bool, error: ?string, check: array<string, mixed>|null}
     */
    public function resend(int $checkId, int $userId, \DateTimeImmutable $now): array
    {
        $check = $this->repo->find($checkId);
        if ($check === null) {
            return $this->fail('not_found');
        }
        if ($check['status'] !== Repo::STATUS_VALIDATED) {
            return $this->fail('bad_status', $check);
        }
        $store = $this->stores->findById($check['store_id']);
        if ($store === null) {
            return $this->fail('not_found', $check);
        }
        $settings = $this->repo->settings($check['store_id']);
        $updated = $check['send_status'] === 'sent';
        $sent = $this->deliver($store, $check, $settings, $userId, $updated, $now);
        if (!$sent['ok']) {
            return ['ok' => false, 'error' => 'send_failed', 'check' => $sent['check']];
        }
        return ['ok' => true, 'error' => null, 'check' => $sent['check']];
    }

    /**
     * « Envoyer maintenant » : le valideur décide lui-même d'envoyer le planning du lendemain, à tout moment (hors fenêtre,
     * jour non ouvré, soumission oubliée). Selon l'état : soumet puis valide (PDF + e-mail), valide ce qui était soumis, ou
     * régénère le PDF d'un planning déjà validé et le renvoie comme version mise à jour.
     *
     * @return array{ok: bool, error: ?string, check: array<string, mixed>|null}
     */
    public function sendNow(int $storeId, string $date, int $userId, bool $confirmEmpty, \DateTimeImmutable $now): array
    {
        $store = $this->stores->findById($storeId);
        $settings = $this->repo->settings($storeId);
        if ($store === null || empty($settings['enabled'])) {
            return $this->fail('not_enabled');
        }
        $check = $this->repo->ensure($storeId, $date);
        if (in_array($check['status'], [Repo::STATUS_PENDING, Repo::STATUS_REJECTED, Repo::STATUS_ESCALATED], true)) {
            $submitted = $this->submit($storeId, $date, $userId, $confirmEmpty, $now);
            if (!$submitted['ok']) {
                return $submitted;
            }
            $check = $submitted['check'];
        }

        return $check['status'] === Repo::STATUS_VALIDATED
            ? $this->resend((int) $check['id'], $userId, $now)
            : $this->approve((int) $check['id'], $userId, $now);
    }

    /**
     * « Revérifier le planning de demain » : prépare la vérification pour que la fenêtre habituelle puisse être rouverte de
     * force (voir PlanningCheckForce). Une vérification soumise ou validée repart en attente (l'historique des envois est
     * conservé, donc le prochain e-mail est une version mise à jour) ; en attente, refusée ou escaladée, elle reste telle
     * quelle ; la mise en retrait « je modifie le planning » est levée. Crée la vérification si elle n'existe pas encore.
     *
     * @return array{ok: bool, error: ?string, check: array<string, mixed>|null}
     */
    public function forceRecheck(int $storeId, string $date): array
    {
        $store = $this->stores->findById($storeId);
        $settings = $this->repo->settings($storeId);
        if ($store === null || empty($settings['enabled'])) {
            return $this->fail('not_enabled');
        }
        $check = $this->repo->ensure($storeId, $date);
        if (in_array($check['status'], [Repo::STATUS_SUBMITTED, Repo::STATUS_VALIDATED], true)) {
            return $this->reopen($storeId, $date);
        }
        if ($check['editing_until'] !== null) {
            $check = $this->repo->update($check['id'], ['editing_by' => null, 'editing_until' => null]);
        }
        return ['ok' => true, 'error' => null, 'check' => $check];
    }

    /**
     * « Refaire le signalement » : une vérification soumise ou validée repart de zéro pour que le cycle (soumettre, valider,
     * envoyer) soit refait. L'historique des envois est conservé, donc le prochain e-mail est une version mise à jour.
     *
     * @return array{ok: bool, error: ?string, check: array<string, mixed>|null}
     */
    public function reopen(int $storeId, string $date): array
    {
        $check = $this->repo->findFor($storeId, $date);
        if ($check === null) {
            return $this->fail('not_found');
        }
        if (!in_array($check['status'], [Repo::STATUS_SUBMITTED, Repo::STATUS_VALIDATED], true)) {
            return $this->fail('bad_status', $check);
        }
        $check = $this->repo->update($check['id'], [
            'status'        => Repo::STATUS_PENDING,
            'submitted_by'  => null,
            'submitted_at'  => null,
            'validated_by'  => null,
            'validated_at'  => null,
            'rejected_by'   => null,
            'rejected_at'   => null,
            'reject_reason' => null,
            'editing_by'    => null,
            'editing_until' => null,
        ]);
        return ['ok' => true, 'error' => null, 'check' => $check];
    }

    /**
     * Le valideur refuse : motif obligatoire, retour au soumetteur qui corrige et soumet de nouveau.
     *
     * @return array{ok: bool, error: ?string, check: array<string, mixed>|null}
     */
    public function reject(int $checkId, int $userId, string $reason, \DateTimeImmutable $now): array
    {
        $reason = trim($reason);
        $check = $this->repo->find($checkId);
        if ($check === null) {
            return $this->fail('not_found');
        }
        if ($check['status'] !== Repo::STATUS_SUBMITTED) {
            return $this->fail('bad_status', $check);
        }
        if ($reason === '') {
            return $this->fail('reason_required', $check);
        }
        $store = $this->stores->findById($check['store_id']);
        $check = $this->repo->update($check['id'], [
            'status'        => Repo::STATUS_REJECTED,
            'rejected_by'   => $userId,
            'rejected_at'   => date('Y-m-d H:i:s', $now->getTimestamp()),
            'reject_reason' => mb_substr($reason, 0, 500),
        ]);
        if ($store !== null && $check['submitted_by'] !== null) {
            $this->notify([$check['submitted_by']], 'planning_check_rejected', $store, $check, ['reason' => $check['reject_reason']], $userId);
        }
        return ['ok' => true, 'error' => null, 'check' => $check];
    }

    /**
     * Échéance : pour chaque magasin activé dont le jour visé est un jour de travail, une validation encore « pending » ou
     * « rejected » après l'heure limite passe « escalated » et prévient les valideurs et les Owners (une seule fois). Aucun
     * PDF n'est envoyé.
     *
     * @return list<int> identifiants des validations escaladées
     */
    public function escalate(\DateTimeImmutable $now): array
    {
        $escalated = [];
        foreach ($this->repo->enabledStoreIds() as $storeId) {
            $store = $this->stores->findById($storeId);
            if ($store === null) {
                continue;
            }
            $state = $this->state($store, $now);
            $check = $state['check'];
            if (!$state['due'] || !$state['late'] || $check === null) {
                continue;
            }
            if (!in_array($check['status'], [Repo::STATUS_PENDING, Repo::STATUS_REJECTED], true)) {
                continue;
            }
            $check = $this->repo->update($check['id'], [
                'status'       => Repo::STATUS_ESCALATED,
                'escalated_at' => date('Y-m-d H:i:s', $now->getTimestamp()),
            ]);
            $this->notify($this->approvers($storeId), 'planning_check_overdue', $store, $check, [], null);
            $escalated[] = $check['id'];
        }
        return $escalated;
    }

    // ── Interne ─────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Génère le PDF, l'envoie, enregistre le résultat et l'historique. @return array{ok: bool, check: array<string, mixed>}
     */
    private function deliver(array $store, array $check, array $settings, int $userId, bool $updated, \DateTimeImmutable $now): array
    {
        $storeId = (int) $store['id'];
        $date = (string) $check['target_date'];
        $hash = PlanningCheckPolicy::shiftsHash($this->rows->raw($storeId, $date));
        $validator = $this->users->findById((int) ($check['validated_by'] ?? $userId));
        $context = [
            'validated_by' => $validator === null ? '' : trim(($validator['last_name'] ?? '') . ' ' . ($validator['first_name'] ?? '')),
            'updated'      => $updated,
            'generated_at' => date('H:i', $now->getTimestamp()),
        ];
        $day = $this->rows->forDay($storeId, $date, $settings);
        $bytes = $this->pdf->generateBytes($store, $check, $day, $context);
        $result = $this->mail->send($store, $check, $settings, $bytes, $context);

        $this->repo->addSend([
            'check_id'    => $check['id'],
            'sent_by'     => $userId,
            'status'      => $result['ok'] ? 'sent' : 'failed',
            'shifts_hash' => $hash,
            'recipients'  => $result['recipients'],
            'error'       => $result['error'],
        ]);
        $check = $this->repo->update($check['id'], [
            'send_status' => $result['ok'] ? 'sent' : 'failed',
            'sent_at'     => $result['ok'] ? date('Y-m-d H:i:s', $now->getTimestamp()) : $check['sent_at'],
            'last_error'  => $result['error'],
            'shifts_hash' => $result['ok'] ? $hash : $check['shifts_hash'],
        ]);
        return ['ok' => $result['ok'], 'check' => $check];
    }

    /**
     * Notification enrichie vers l'historique du magasin, avec la validation en évidence (« lu » automatique à la visite).
     *
     * @param list<int>            $userIds
     * @param array<string, mixed> $store
     * @param array<string, mixed> $check
     * @param array<string, mixed> $extra   paramètres de traduction supplémentaires
     */
    private function notify(array $userIds, string $type, array $store, array $check, array $extra, ?int $exceptUserId): void
    {
        $userIds = array_values(array_filter(array_unique($userIds), static fn(int $id): bool => $id !== $exceptUserId || $type === 'planning_check_validated'));
        if ($userIds === []) {
            return;
        }
        try {
            $this->notifications->notifyMany(
                $userIds,
                $type,
                'notif_' . $type . '_body',
                ['store' => (string) ($store['name'] ?? ''), 'date' => (string) $check['target_date']] + $extra,
                (int) $check['id'],
                '/admin/stores/' . (int) $check['store_id'] . '/planning-check?highlight=' . (int) $check['id'],
            );
        } catch (\Throwable) {
            // Une notification qui échoue ne doit jamais défaire l'action métier déjà enregistrée.
        }
    }

    /** @param array<string, mixed>|null $check @return array{ok: false, error: string, check: array<string, mixed>|null} */
    private function fail(string $error, ?array $check = null): array
    {
        return ['ok' => false, 'error' => $error, 'check' => $check];
    }
}
