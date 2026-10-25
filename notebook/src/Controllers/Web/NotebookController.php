<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Notebook\Controllers\Web;

use kintai\Contract\Auth\PermissionService;
use kintai\Bundles\Installed\Notebook\Repositories\NotebookEntryRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;
use kintai\Contract\Services\NotificationService;
use kintai\Contract\Http\Concerns\HasAdminAccess;
use kintai\Contract\View\ViewRenderer;

final class NotebookController
{
    use HasAdminAccess;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly NotebookEntryRepositoryInterface $notes,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifs,
        private readonly PermissionService $permissions,
    ) {}

    /**
     * GET /notebook — liste des notes visibles (organisation entière + stores
     * gérés), épinglées en tête. Les expirées sont masquées par défaut,
     * ?show_expired=1 les réaffiche (historique complet).
     */
    public function index(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $storeIds   = $managedIds ?? array_map(fn($s) => (int) $s['id'], $this->stores->findAll());

        $entries = $this->notes->findVisibleForStores($storeIds);

        $showExpired = $request->query('show_expired') === '1';
        if (!$showExpired) {
            $now = date('Y-m-d H:i:s');
            $entries = array_values(array_filter(
                $entries,
                fn($n) => empty($n['expires_at']) || $n['expires_at'] > $now
            ));
        }

        usort($entries, function ($a, $b) {
            $pinCmp = (int) ($b['pinned'] ?? 0) <=> (int) ($a['pinned'] ?? 0);
            return $pinCmp !== 0 ? $pinCmp : strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        $user   = $request->getAttribute('auth_user') ?? [];
        $userId = (int) ($user['id'] ?? 0);

        $usersMap  = $this->buildUsersMap();
        $storesMap = $this->buildStoresMap(null);

        $entries = array_map(function (array $n) use ($usersMap, $storesMap, $userId, $user): array {
            $n['author_name'] = $usersMap[(int) $n['author_id']] ?? ('#' . $n['author_id']);
            $n['store_name']  = $n['store_id'] !== null ? ($storesMap[(int) $n['store_id']] ?? null) : null;
            $n['can_manage']  = $this->canManageEntry($user, $n['store_id'] !== null ? (int) $n['store_id'] : null);
            $n['can_edit']    = (int) $n['author_id'] === $userId || $n['can_manage'];
            return $n;
        }, $entries);

        // Les notes sont affichées : leurs notifications de publication sont lues.
        if ($userId > 0 && $entries !== []) {
            $this->notifs->markReadFor($userId, 'notebook_entry', array_map(fn(array $n) => (int) $n['id'], $entries));
        }

        return Response::html($this->view->render('notebook::notebook', [
            'title'         => __('bundle_notebook'),
            'highlight_id'  => (int) $request->query('highlight', 0),
            'entries'       => $entries,
            'stores_map'    => $storesMap,
            'store_ids'     => $storeIds,
            'can_global'    => $managedIds === null,
            'show_expired'  => $showExpired,
            'can_create'    => $this->permissions->can($user, 'notebook.create', null),
        ], 'layout.app'));
    }

    /**
     * POST /notebook — crée une note. store_id vide = note "toute
     * l'organisation", réservée aux utilisateurs à portée globale (Owner ou
     * rôle accordant notebook.create en portée "Toutes les boutiques").
     */
    public function store(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user') ?? [];
        $userId = (int) ($user['id'] ?? 0);

        $content = trim((string) $request->post('content', ''));
        if ($content === '') {
            return Response::redirect($this->base() . '/notebook?error=empty_content');
        }

        $storeIdRaw = trim((string) $request->post('store_id', ''));
        $storeId    = $storeIdRaw !== '' ? (int) $storeIdRaw : null;

        if ($storeId === null) {
            if ($this->managedIds($request) !== null) {
                return Response::redirect($this->base() . '/notebook?error=forbidden');
            }
        } else {
            $this->assertStoreAccess($request, $storeId);
        }

        $expiresAt = $this->parseExpiresAt($request->post('expires_at', ''));

        $saved = $this->notes->save([
            'author_id'  => $userId,
            'store_id'   => $storeId,
            'content'    => $content,
            'pinned'     => 0,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogger->log(
            $request,
            'notebook_entry.created',
            'notebook_entry',
            (int) ($saved['id'] ?? 0),
            ['store_id' => $storeId],
            $storeId,
            $userId
        );

        $this->notifyMembers($storeId, (int) ($saved['id'] ?? 0), $userId, $content);

        return Response::redirect($this->base() . '/notebook?success=created');
    }

    /** POST /notebook/{id}/update — auteur ou notebook.manage. */
    public function update(Request $request): Response
    {
        $entry = $this->requireEntry($request);
        $this->assertCanEdit($request, $entry);

        $content = trim((string) $request->post('content', ''));
        if ($content === '') {
            return Response::redirect($this->base() . '/notebook?error=empty_content');
        }

        $old = $entry;
        $entry['content']    = $content;
        $entry['expires_at'] = $this->parseExpiresAt($request->post('expires_at', ''));
        $this->notes->save($entry);

        $this->auditLogger->logUpdate(
            $request,
            'notebook_entry.updated',
            'notebook_entry',
            (int) $entry['id'],
            $old,
            $entry,
            [],
            $entry['store_id'] !== null ? (int) $entry['store_id'] : null
        );

        return Response::redirect($this->base() . '/notebook?success=updated');
    }

    /** POST /notebook/{id}/delete — auteur ou notebook.manage. */
    public function destroy(Request $request): Response
    {
        $entry = $this->requireEntry($request);
        $this->assertCanEdit($request, $entry);

        $this->notes->delete((int) $entry['id']);

        $this->auditLogger->log(
            $request,
            'notebook_entry.deleted',
            'notebook_entry',
            (int) $entry['id'],
            ['store_id' => $entry['store_id']],
            $entry['store_id'] !== null ? (int) $entry['store_id'] : null
        );

        return Response::redirect($this->base() . '/notebook?success=deleted');
    }

    /** POST /notebook/{id}/pin — notebook.manage uniquement (déjà gardé par la route). */
    public function togglePin(Request $request): Response
    {
        $entry = $this->requireEntry($request);
        // La route n'exige notebook.manage que « quelque part » : sans ce contrôle, le gérant d'un seul
        // magasin épinglait aussi les notes d'un autre magasin ou celles de toute l'organisation.
        if (!$this->canManageEntry($request->getAttribute('auth_user') ?? [], $entry['store_id'] !== null ? (int) $entry['store_id'] : null)) {
            throw new \kintai\Contract\Exceptions\ForbiddenException(__('notebook_error_forbidden'));
        }
        $entry['pinned'] = empty($entry['pinned']) ? 1 : 0;
        $this->notes->save($entry);

        return Response::redirect($this->base() . '/notebook?success=updated');
    }

    private function requireEntry(Request $request): array
    {
        $entry = $this->notes->findById((int) $request->param('id'));
        if ($entry === null) {
            throw new \kintai\Contract\Exceptions\NotFoundException(__('notebook_error_entry_not_found'));
        }
        if ($entry['store_id'] !== null) {
            $this->assertStoreAccess($request, (int) $entry['store_id']);
        }
        return $entry;
    }

    private function assertCanEdit(Request $request, array $entry): void
    {
        $user   = $request->getAttribute('auth_user') ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $storeId = $entry['store_id'] !== null ? (int) $entry['store_id'] : null;

        if ((int) $entry['author_id'] === $userId) {
            return;
        }
        if ($this->canManageEntry($user, $storeId)) {
            return;
        }
        throw new \kintai\Contract\Exceptions\ForbiddenException(__('notebook_error_forbidden'));
    }

    /**
     * notebook.manage sur le magasin de la note ; pour une note « toute l'organisation » (magasin nul),
     * la permission doit être accordée sans restriction de magasin — `can(..., null)` signifie
     * seulement « quelque part » et laissait un gérant d'un seul magasin modifier les notes globales.
     */
    private function canManageEntry(array $user, ?int $storeId): bool
    {
        return $storeId === null
            ? $this->permissions->hasUnrestrictedGrant($user, 'notebook.manage')
            : $this->permissions->can($user, 'notebook.manage', $storeId);
    }

    /** Notifie les utilisateurs ayant notebook.view — membres du store si scopée, tout le monde si organisation. */
    private function notifyMembers(?int $storeId, int $referenceId, int $authorId, string $content): void
    {
        $recipients = [];
        if ($storeId !== null) {
            foreach ($this->storeUsers->findByStore($storeId) as $m) {
                $candidate = $this->users->findById((int) $m['user_id']);
                if ($candidate !== null && $this->permissions->can($candidate, 'notebook.view', $storeId)) {
                    $recipients[] = (int) $m['user_id'];
                }
            }
        } else {
            foreach ($this->users->findAll() as $candidate) {
                if ($this->permissions->can($candidate, 'notebook.view', null)) {
                    $recipients[] = (int) $candidate['id'];
                }
            }
        }
        if ($recipients !== []) {
            $usersMap   = $this->buildUsersMap();
            $authorName = $usersMap[$authorId] ?? ('#' . $authorId);
            $excerpt    = mb_strimwidth($content, 0, 80, '…');
            $this->notifs->notifyMany(
                $recipients,
                'notebook_entry_created',
                'notif_notebook_entry_created_body',
                ['author' => $authorName, 'excerpt' => $excerpt],
                $referenceId,
                '/notebook?highlight=' . $referenceId
            );
        }
    }

    private function parseExpiresAt(mixed $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }
}
