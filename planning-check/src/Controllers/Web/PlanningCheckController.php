<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck\Controllers\Web;

use kintai\Bundles\Installed\PlanningCheck\Repositories\PlanningCheckRepositoryInterface as Repo;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningCheckForce;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningCheckPolicy;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningCheckService;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningMailService;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningPdfService;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningShiftRows;
use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Contract\Exceptions\NotFoundException;
use kintai\Contract\Http\Concerns\HasBaseUrl;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;
use kintai\Contract\Services\NotificationService;
use kintai\Contract\Services\UserSelection;
use kintai\Contract\View\ViewRenderer;

/**
 * Validation du planning de demain : historique par magasin, soumission / validation / refus / renvoi, réglages. Les
 * permissions et l'appartenance au magasin sont portées par les routes (voir routes.php) ; le contrôleur vérifie en plus la
 * portée des gérants (managed_store_ids) et que la validation visée appartient bien au magasin de l'URL.
 */
final class PlanningCheckController
{
    use HasBaseUrl;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly Repo $repo,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
        private readonly PlanningCheckService $service,
        private readonly PlanningShiftRows $rows,
        private readonly PlanningPdfService $pdf,
        private readonly PlanningMailService $mail,
        private readonly PermissionService $permissions,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
        private readonly UserSelection $selection,
    ) {}

    // ── Accueil et historique ───────────────────────────────────────────────────────────────────────────────────

    /** GET /admin/planning-check — les magasins dont l'utilisateur peut consulter la validation. */
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('auth_user');
        $managedIds = $request->getAttribute('managed_store_ids');
        $visible = [];
        foreach ($this->stores->findAll() as $store) {
            $id = (int) $store['id'];
            if (($managedIds === null || in_array($id, $managedIds, true)) && $this->permissions->can($user, 'planning_check.view', $id)) {
                $visible[] = $store + ['planning_check_enabled' => $this->repo->settings($id)['enabled']];
            }
        }
        // Un seul magasin : on ouvre directement son historique.
        if (count($visible) === 1) {
            return Response::redirect($this->base() . '/admin/stores/' . (int) $visible[0]['id'] . '/planning-check');
        }

        return Response::html($this->view->render('planning-check::index', [
            'title'  => __('planning_check_title'),
            'stores' => $visible,
        ], 'layout.app'));
    }

    /** GET /admin/stores/{id}/planning-check */
    public function history(Request $request): Response
    {
        $store = $this->requireStore($request);
        $user  = $request->getAttribute('auth_user');
        $storeId = (int) $store['id'];

        $checks = $this->repo->recentForStore($storeId, 40);
        // Planning de demain (fuseau de l'application) : sa vérification, même hors fenêtre, pour l'envoyer ou la refaire à la main.
        $tomorrow = PlanningCheckPolicy::targetDate(date_default_timezone_get(), new \DateTimeImmutable('now'));
        // Les notifications « à valider », « validé »… de ces validations sont lues à la visite de la page.
        if ($checks !== []) {
            $this->notifications->markReadFor((int) $user['id'], 'planning_check', array_column($checks, 'id'));
        }

        $names = [];
        foreach ($checks as &$check) {
            foreach (['submitted_by', 'validated_by', 'rejected_by'] as $field) {
                $uid = $check[$field];
                if ($uid !== null && !isset($names[$uid])) {
                    $u = $this->users->findById($uid);
                    $names[$uid] = $u === null ? '—' : (trim(($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? '')) ?: ($u['email'] ?? '—'));
                }
            }
            $check['needs_resend'] = $this->service->needsResend($check);
        }
        unset($check);

        return Response::html($this->view->render('planning-check::history', [
            'title'        => __('planning_check_history_title'),
            'store'        => $store,
            'checks'       => $checks,
            'names'        => $names,
            'highlight'    => (int) $request->query('highlight', 0),
            'tomorrow'     => [
                'date'  => $tomorrow,
                'check' => $this->repo->findFor($storeId, $tomorrow),
                'count' => count($this->rows->raw($storeId, $tomorrow)),
            ],
            'can_approve'  => $this->permissions->can($user, 'planning_check.approve', $storeId),
            'can_submit'   => $this->permissions->can($user, 'planning_check.submit', $storeId),
            'can_settings' => $this->permissions->can($user, 'planning_check.settings', $storeId),
            'settings'     => $this->repo->settings($storeId),
        ], 'layout.app'));
    }

    // ── Soumettre / modifier ────────────────────────────────────────────────────────────────────────────────────

    /** POST /admin/stores/{id}/planning-check/submit */
    public function submit(Request $request): Response
    {
        $store = $this->requireStore($request);
        $date = $this->requireTargetDate($request, $store);
        $user = $request->getAttribute('auth_user');

        $result = $this->service->submit((int) $store['id'], $date, (int) $user['id'], $request->post('confirm_empty', '') === '1', new \DateTimeImmutable('now'));
        if ($result['ok']) {
            // La vérification est soumise : plus rien à forcer (la fenêtre de validation prend le relais pour les valideurs).
            PlanningCheckForce::clear((int) $store['id']);
            $this->auditLogger->log($request, 'planning_check.submitted', 'planning_check', (int) $result['check']['id'], ['store_id' => (int) $store['id'], 'date' => $date], (int) $store['id']);
        }
        return $this->back($store, $result, 'submitted');
    }

    /**
     * POST /admin/stores/{id}/planning-check/recheck — « Revérifier le planning de demain » : rouvre la vérification (même
     * déjà soumise ou validée) et force l'ouverture de la fenêtre habituelle, avec les shifts de demain, comme si l'heure
     * de la vérification était venue. La soumettre recrée un rapport.
     */
    public function recheck(Request $request): Response
    {
        $store = $this->requireStore($request);
        $date = $this->requireTargetDate($request, $store);

        $result = $this->service->forceRecheck((int) $store['id'], $date);
        if ($result['ok']) {
            PlanningCheckForce::mark((int) $store['id'], $date);
            $this->auditLogger->log($request, 'planning_check.recheck_forced', 'planning_check', (int) $result['check']['id'], ['store_id' => (int) $store['id'], 'date' => $date], (int) $store['id']);
        }
        return $this->back($store, $result, 'recheck');
    }

    /** POST /admin/stores/{id}/planning-check/edit — « Non, modifier le planning » : retrait de la fenêtre, puis le planning. */
    public function edit(Request $request): Response
    {
        $store = $this->requireStore($request);
        $date = $this->requireTargetDate($request, $store);
        $user = $request->getAttribute('auth_user');

        $this->service->startEditing((int) $store['id'], $date, (int) $user['id'], new \DateTimeImmutable('now'));

        return Response::redirect($this->base() . '/admin/shifts/timeline?store_id=' . (int) $store['id'] . '&start=' . $date . '&view=3days');
    }

    /** POST /admin/stores/{id}/planning-check/resume — « Revenir à la validation » depuis la barre de correction. */
    public function resume(Request $request): Response
    {
        $store = $this->requireStore($request);
        $date = $this->requireTargetDate($request, $store);
        $this->service->resumeValidation((int) $store['id'], $date);

        return Response::redirect(back_url($this->base() . '/admin/stores/' . (int) $store['id'] . '/planning-check'));
    }

    // ── Valider / refuser / renvoyer ────────────────────────────────────────────────────────────────────────────

    /** POST /admin/stores/{id}/planning-check/{cid}/approve */
    public function approve(Request $request): Response
    {
        [$store, $check] = $this->requireCheck($request);
        $user = $request->getAttribute('auth_user');

        $result = $this->service->approve((int) $check['id'], (int) $user['id'], new \DateTimeImmutable('now'));
        if ($result['ok']) {
            $this->auditLogger->log($request, 'planning_check.validated', 'planning_check', (int) $check['id'], [
                'store_id' => (int) $store['id'], 'date' => $check['target_date'], 'send_status' => $result['check']['send_status'] ?? '',
            ], (int) $store['id']);
        }
        return $this->back($store, $result, 'validated');
    }

    /** POST /admin/stores/{id}/planning-check/{cid}/reject */
    public function reject(Request $request): Response
    {
        [$store, $check] = $this->requireCheck($request);
        $user = $request->getAttribute('auth_user');

        $result = $this->service->reject((int) $check['id'], (int) $user['id'], (string) $request->post('reason', ''), new \DateTimeImmutable('now'));
        if ($result['ok']) {
            $this->auditLogger->log($request, 'planning_check.rejected', 'planning_check', (int) $check['id'], ['store_id' => (int) $store['id'], 'date' => $check['target_date']], (int) $store['id']);
        }
        return $this->back($store, $result, 'rejected');
    }

    /** POST /admin/stores/{id}/planning-check/{cid}/resend */
    public function resend(Request $request): Response
    {
        [$store, $check] = $this->requireCheck($request);
        $user = $request->getAttribute('auth_user');

        $result = $this->service->resend((int) $check['id'], (int) $user['id'], new \DateTimeImmutable('now'));
        if ($result['ok']) {
            $this->auditLogger->log($request, 'planning_check.resent', 'planning_check', (int) $check['id'], ['store_id' => (int) $store['id'], 'date' => $check['target_date']], (int) $store['id']);
        }
        return $this->back($store, $result, 'resent');
    }

    /** POST /admin/stores/{id}/planning-check/send-now — le valideur envoie lui-même le planning de demain, à tout moment. */
    public function sendNow(Request $request): Response
    {
        $store = $this->requireStore($request);
        $date = $this->requireTargetDate($request, $store);
        $user = $request->getAttribute('auth_user');

        $result = $this->service->sendNow((int) $store['id'], $date, (int) $user['id'], $request->post('confirm_empty', '') === '1', new \DateTimeImmutable('now'));
        if ($result['ok']) {
            $this->auditLogger->log($request, 'planning_check.sent_now', 'planning_check', (int) ($result['check']['id'] ?? 0), [
                'store_id' => (int) $store['id'], 'date' => $date, 'send_status' => $result['check']['send_status'] ?? '',
            ], (int) $store['id']);
        }
        return $this->back($store, $result, 'sent_now');
    }

    /** GET /admin/stores/{id}/planning-check/{cid}/pdf — PDF du planning tel qu'il est maintenant. */
    public function pdf(Request $request): Response
    {
        [$store, $check] = $this->requireCheck($request);
        $settings = $this->repo->settings((int) $store['id']);
        $day = $this->rows->forDay((int) $store['id'], (string) $check['target_date'], $settings);
        $bytes = $this->pdf->generateBytes($store, $check, $day, ['generated_at' => date('H:i')]);
        if ($bytes === null) {
            throw new NotFoundException(__('planning_check_err_pdf_failed'));
        }
        return Response::pdf($bytes, 'planning_' . str_replace('-', '', (string) $check['target_date']) . '_store' . (int) $store['id'] . '.pdf');
    }

    // ── Réglages ────────────────────────────────────────────────────────────────────────────────────────────────

    /** GET /admin/stores/{id}/planning-check/settings */
    public function settings(Request $request): Response
    {
        $store = $this->requireStore($request);

        return Response::html($this->view->render('planning-check::settings', [
            'title'      => __('planning_check_settings_title'),
            'store'      => $store,
            'settings'   => $this->repo->settings((int) $store['id']),
            'candidates' => $this->selection->candidates((int) $store['id']),
            'errors'     => [],
        ], 'layout.app'));
    }

    /** POST /admin/stores/{id}/planning-check/settings */
    public function saveSettings(Request $request): Response
    {
        $store = $this->requireStore($request);
        $before = $this->repo->settings((int) $store['id']);
        [$settings, $errors] = self::parseSettings($request->allPost(), $before);

        // Chaque employé coché doit être un membre actif du magasin.
        if ($this->selection->validate((int) $store['id'], $settings['mail_recipient_ids'])['error'] === 'unknown') {
            $errors[] = 'planning_check_err_recipient_unknown';
        }

        if ($errors !== []) {
            return Response::html($this->view->render('planning-check::settings', [
                'title'      => __('planning_check_settings_title'),
                'store'      => $store,
                'settings'   => $settings,
                'candidates' => $this->selection->candidates((int) $store['id']),
                'errors'     => $errors,
            ], 'layout.app'));
        }

        $this->repo->saveSettings((int) $store['id'], $settings);
        $this->auditLogger->logUpdate($request, 'planning_check.settings_saved', 'planning_check_settings', (int) $store['id'], $before, $settings, ['store_id' => (int) $store['id']], (int) $store['id']);

        return Response::redirect($this->base() . '/admin/stores/' . (int) $store['id'] . '/planning-check/settings?success=saved');
    }

    /** POST /admin/stores/{id}/planning-check/test — envoie un PDF d'essai (planning de demain tel qu'il est) à l'utilisateur. */
    public function sendTest(Request $request): Response
    {
        $store = $this->requireStore($request);
        $user = $request->getAttribute('auth_user');
        $email = (string) ($user['email'] ?? '');
        $url = $this->base() . '/admin/stores/' . (int) $store['id'] . '/planning-check/settings';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return Response::redirect($url . '?error=test_no_email');
        }

        $now = new \DateTimeImmutable('now');
        $date = PlanningCheckPolicy::targetDate(date_default_timezone_get(), $now);
        $settings = $this->repo->settings((int) $store['id']);
        $check = ['target_date' => $date, 'status' => 'test'];
        $day = $this->rows->forDay((int) $store['id'], $date, $settings);
        $bytes = $this->pdf->generateBytes($store, $check, $day, ['validated_by' => '', 'generated_at' => $now->format('H:i')]);
        $result = $this->mail->send($store, $check, ['mail_recipients' => [$email]], $bytes, []);

        return Response::redirect($url . ($result['ok'] ? '?success=test_sent' : '?error=test_failed'));
    }

    /**
     * Réglages saisis → [réglages validés, erreurs (clés de traduction)]. Heures HH:MM avec début avant l'échéance, au moins un
     * jour, destinataires (employés cochés et adresses d'avant la sélection restées cochées, 10 au plus en tout).
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $stored Réglages enregistrés (les adresses d'avant la sélection ne se retirent que de là)
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    public static function parseSettings(array $post, array $stored = []): array
    {
        $errors = [];
        $defaults = Repo::DEFAULT_SETTINGS;
        $time = static fn(string $key): ?string => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', trim((string) ($post[$key] ?? ''))) === 1 ? trim((string) $post[$key]) : null;

        $deadline = $time('deadline_time');
        $start = $time('window_start_time');
        if ($deadline === null || $start === null) {
            $errors[] = 'planning_check_err_time_invalid';
        } elseif ($start >= $deadline) {
            $errors[] = 'planning_check_err_window_order';
        }

        $weekdays = array_values(array_unique(array_filter(
            array_map('intval', is_array($post['weekdays'] ?? null) ? $post['weekdays'] : []),
            static fn(int $d): bool => $d >= 1 && $d <= 7,
        )));
        sort($weekdays);
        if ($weekdays === []) {
            $errors[] = 'planning_check_err_weekdays';
        }

        // Employés cochés (comptes ; leur existence dans le magasin est vérifiée par saveSettings) et adresses saisies
        // avant la sélection : on peut en retirer, plus en ajouter.
        $ids = [];
        foreach (is_array($post['mail_recipient_ids'] ?? null) ? $post['mail_recipient_ids'] : [] as $id) {
            if ((int) $id > 0 && !in_array((int) $id, $ids, true)) {
                $ids[] = (int) $id;
            }
        }
        $keep = array_map(static fn($e): string => strtolower(trim((string) $e)), is_array($post['legacy_recipients'] ?? null) ? $post['legacy_recipients'] : []);
        $legacy = array_values(array_filter(
            array_map('strval', (array) ($stored['mail_recipients'] ?? [])),
            static fn(string $e): bool => in_array(strtolower($e), $keep, true) && filter_var($e, FILTER_VALIDATE_EMAIL) !== false,
        ));

        if (count($ids) + count($legacy) > 10) {
            $errors[] = 'planning_check_err_recipients_max';
        }

        $enabled = ($post['enabled'] ?? '') === '1';
        if ($enabled && $ids === [] && $legacy === []) {
            $errors[] = 'planning_check_err_recipients_required';
        }

        return [[
            'enabled'             => $enabled,
            'deadline_time'       => $deadline ?? $defaults['deadline_time'],
            'window_start_time'   => $start ?? $defaults['window_start_time'],
            'weekdays'            => $weekdays !== [] ? $weekdays : $defaults['weekdays'],
            'mail_recipient_ids'  => $ids,
            'mail_recipients'     => $legacy,
            'include_open_shifts' => ($post['include_open_shifts'] ?? '') === '1',
        ], array_values(array_unique($errors))];
    }

    // ── Interne ─────────────────────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function requireStore(Request $request): array
    {
        $storeId = (int) $request->param('id');
        $store = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException(__('error_store_not_found'));
        }
        $managedIds = $request->getAttribute('managed_store_ids');
        if ($managedIds !== null && !in_array($storeId, $managedIds, true)) {
            throw new ForbiddenException(__('error_not_store_manager'));
        }
        return $store;
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function requireCheck(Request $request): array
    {
        $store = $this->requireStore($request);
        $check = $this->repo->find((int) $request->param('cid'));
        if ($check === null || $check['store_id'] !== (int) $store['id']) {
            throw new NotFoundException(__('planning_check_err_not_found'));
        }
        return [$store, $check];
    }

    /** Le jour soumis doit être le lendemain du magasin : on ne valide pas une date arbitraire envoyée par le formulaire. */
    private function requireTargetDate(Request $request, array $store): string
    {
        $expected = PlanningCheckPolicy::targetDate(date_default_timezone_get(), new \DateTimeImmutable('now'));
        if ((string) $request->post('date', '') !== $expected) {
            throw new NotFoundException(__('planning_check_err_not_found'));
        }
        return $expected;
    }

    /**
     * Retour à la page d'où vient l'action (le planning, le tableau de bord…), avec le résultat dans l'URL.
     *
     * @param array<string, mixed> $store
     * @param array{ok: bool, error: ?string, check: array<string, mixed>|null} $result
     */
    private function back(array $store, array $result, string $success): Response
    {
        $target = back_url($this->base() . '/admin/stores/' . (int) $store['id'] . '/planning-check');
        $path = strtok($target, '?');
        $query = $result['ok'] ? 'pc=' . $success : 'pc_error=' . urlencode((string) $result['error']);
        return Response::redirect($path . '?' . $query);
    }
}
