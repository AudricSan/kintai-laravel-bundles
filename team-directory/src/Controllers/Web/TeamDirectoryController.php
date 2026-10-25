<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\TeamDirectory\Controllers\Web;

use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Contract\Exceptions\NotFoundException;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\View\ViewRenderer;

/**
 * Annuaire des collègues : chaque employé peut consulter le profil enrichi
 * (photo, bio, compétences, langues parlées, loisirs — voir la migration
 * 2026_09_17_000000) des collègues avec lesquels il partage au moins un store.
 * Un Owner (portée globale) voit l'ensemble de l'organisation.
 *
 * N'expose jamais les coordonnées personnelles (téléphone, adresse) — celles-ci
 * restent réservées à l'auto-consultation (/profile) et à la fiche RH admin.
 */
final class TeamDirectoryController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly UserRepositoryInterface $users,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly StoreRepositoryInterface $stores,
    ) {}

    public function index(Request $request): Response
    {
        $user    = $request->getAttribute('auth_user');
        $userId  = (int) ($user['id'] ?? 0);
        $isOwner = !empty($user['is_admin']);

        $myStoreIds = $isOwner
            ? array_map(fn($s) => (int) $s['id'], $this->stores->findAll())
            : $this->storeIdsFor($userId);

        $storeIdsByColleague = [];
        foreach ($myStoreIds as $sid) {
            foreach ($this->storeUsers->findByStore($sid) as $m) {
                $mid = (int) $m['user_id'];
                if ($mid === $userId) {
                    continue;
                }
                $storeIdsByColleague[$mid][] = $sid;
            }
        }

        $colleagues = [];
        foreach (array_keys($storeIdsByColleague) as $cid) {
            $u = $this->users->findById($cid);
            if ($u === null || empty($u['is_active']) || (int) ($u['show_in_directory'] ?? 1) === 0) {
                continue;
            }
            $colleagues[] = [
                'user'      => $u,
                'store_ids' => array_unique($storeIdsByColleague[$cid]),
            ];
        }
        usort($colleagues, fn($a, $b) => strcmp($this->displayName($a['user']), $this->displayName($b['user'])));

        return Response::html($this->view->render('team-directory::team-directory', [
            'title'      => __('team_directory'),
            'colleagues' => $colleagues,
            'stores_map' => $this->storesMap(),
        ], 'layout.app'));
    }

    public function show(Request $request): Response
    {
        $user     = $request->getAttribute('auth_user');
        $userId   = (int) ($user['id'] ?? 0);
        $isOwner  = !empty($user['is_admin']);
        $targetId = (int) $request->param('id');

        $target = $this->users->findById($targetId);
        if ($target === null || empty($target['is_active'])) {
            throw new NotFoundException(__('error_user_not_found'));
        }

        $isSelf = $targetId === $userId;
        if (!$isSelf && !$isOwner) {
            $shared = array_intersect($this->storeIdsFor($userId), $this->storeIdsFor($targetId));
            if ($shared === []) {
                throw new ForbiddenException(__('team_directory_error_not_colleague'));
            }
        }
        if (!$isSelf && (int) ($target['show_in_directory'] ?? 1) === 0) {
            throw new NotFoundException(__('error_user_not_found'));
        }

        return Response::html($this->view->render('team-directory::team-directory-show', [
            'title'      => $this->displayName($target),
            'colleague'  => $target,
            'store_ids'  => $this->storeIdsFor($targetId),
            'stores_map' => $this->storesMap(),
        ], 'layout.app'));
    }

    /** @return int[] */
    private function storeIdsFor(int $userId): array
    {
        return array_values(array_unique(array_map(
            fn($m) => (int) $m['store_id'],
            $this->storeUsers->findByUser($userId)
        )));
    }

    /** @return array<int, string> */
    private function storesMap(): array
    {
        $map = [];
        foreach ($this->stores->findAll() as $s) {
            $map[(int) $s['id']] = $s['name'] ?? ('#' . $s['id']);
        }
        return $map;
    }

    private function displayName(array $u): string
    {
        $name = trim(($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? ''));
        return $name !== '' ? $name : (string) ($u['display_name'] ?? $u['email'] ?? ('#' . ($u['id'] ?? '')));
    }
}
