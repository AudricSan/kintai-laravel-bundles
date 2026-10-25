<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\HiringReport\Services;

use kintai\Bundles\Installed\HiringReport\Repositories\HiringReportRepositoryInterface;
use kintai\Contract\Event\EmployeeCreated;

/**
 * Crée le rapport d'embauche d'un employé dès sa création (événement EmployeeCreated du Core, contrat 1.13), à partir
 * de sa fiche : formulaire « Nouvel employé » comme création rapide de l'import Excel. Un employé créé sans magasin
 * (par exemple par l'API) n'a pas de rapport : il en faut un par magasin.
 *
 * Le Core créait ce rapport lui-même jusqu'à l'extraction du bundle ; l'import Excel n'y mettait alors que le magasin,
 * le numéro et le nom.
 */
final class HiringReportOnEmployeeCreated
{
    /** Champs de la fiche employé recopiés tels quels (vide = non renseigné). */
    private const COPIED_USER_FIELDS = [
        'furigana_last_name', 'furigana_first_name', 'gender', 'tax_classification', 'birth_date', 'education',
        'postal_code', 'address', 'phone', 'mobile_phone', 'email', 'guarantor_name', 'guarantor_phone',
    ];

    public function __construct(private readonly HiringReportRepositoryInterface $reports) {}

    public function __invoke(EmployeeCreated $event): void
    {
        if ($event->storeId === null || $event->userId() <= 0) {
            return;
        }
        $user = $event->user;
        $name = (string) ($user['display_name'] ?? '');

        $report = [
            'store_id'        => $event->storeId,
            'user_id'         => $event->userId(),
            'employee_number' => ($user['employee_code'] ?? null) ?: null,
            'employee_name'   => $name,
            'hire_date'       => date('Y-m-d'),
            'store_name'      => null,
            'hired_by'        => $name,
            'created_by'      => $event->createdBy ?? 0,
        ];
        foreach (self::COPIED_USER_FIELDS as $field) {
            $report[$field] = ($user[$field] ?? null) ?: null;
        }

        $this->reports->save($report);
    }
}
