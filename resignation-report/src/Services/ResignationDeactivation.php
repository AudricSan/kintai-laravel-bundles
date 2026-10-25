<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ResignationReport\Services;

use kintai\Bundles\Installed\ResignationReport\Repositories\ResignationReportRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;

/**
 * Désactivation du compte d'un démissionnaire (audit n°8 de Kintai, décision D2) : le compte entier est désactivé à la
 * date de démission, pas à la création du rapport, pour que l'employé garde l'accès à son planning pendant son préavis.
 * Appelée à la création et à la modification d'un rapport, et chaque jour par la tâche « resignation-deactivate ».
 *
 * deactivated_at note qu'un rapport a été appliqué : il ne l'est qu'une fois (un compte réactivé à la main le reste).
 */
final class ResignationDeactivation
{
    public function __construct(
        private readonly ResignationReportRepositoryInterface $reports,
        private readonly UserRepositoryInterface $users,
    ) {}

    /** Date du jour dans le fuseau de l'instance. */
    public static function today(): string
    {
        return date('Y-m-d');
    }

    /**
     * Met le compte en accord avec le rapport : désactivé si la date est atteinte (ou absente) et que le rapport n'a
     * pas encore été appliqué ; réactivé si un rapport déjà appliqué a été repoussé dans le futur.
     *
     * @return 'deactivated'|'reactivated'|null ce qui a été fait
     */
    public function sync(array $report, ?string $today = null): ?string
    {
        $userId = (int) ($report['user_id'] ?? 0);
        if ($userId <= 0 || empty($report['id'])) {
            return null;
        }
        $today   = $today ?? self::today();
        $date    = substr((string) ($report['resignation_date'] ?? ''), 0, 10);
        $due     = $date === '' || $date <= $today;
        $applied = !empty($report['deactivated_at']);

        if ($due && !$applied) {
            $this->setActive($userId, false);
            $this->reports->save(['id' => (int) $report['id'], 'deactivated_at' => date('Y-m-d H:i:s')]);
            return 'deactivated';
        }
        if (!$due && $applied) {
            $this->setActive($userId, true);
            $this->reports->save(['id' => (int) $report['id'], 'deactivated_at' => null]);
            return 'reactivated';
        }
        return null;
    }

    /**
     * Applique tous les rapports arrivés à échéance (tâche planifiée).
     *
     * @return list<int> identifiants des rapports appliqués
     */
    public function applyDue(?string $today = null): array
    {
        $applied = [];
        foreach ($this->reports->findDueForDeactivation($today ?? self::today()) as $report) {
            if ($this->sync($report, $today) === 'deactivated') {
                $applied[] = (int) $report['id'];
            }
        }
        return $applied;
    }

    private function setActive(int $userId, bool $active): void
    {
        $user = $this->users->findById($userId);
        if ($user !== null) {
            $user['is_active'] = $active ? 1 : 0;
            $this->users->save($user);
        }
    }
}
