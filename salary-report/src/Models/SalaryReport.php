<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\SalaryReport\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Repris du Core à l'étape 4 du plan du contrat (extraction) : la table garde son nom et ses données.
 */
final class SalaryReport extends Model
{
    protected $table = 'salary_reports';
    protected $guarded = [];
    public $timestamps = false;
}
