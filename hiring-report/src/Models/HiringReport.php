<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\HiringReport\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Repris du Core à l'étape 4 du plan du contrat (extraction) : la table garde son nom et ses données.
 */
final class HiringReport extends Model
{
    protected $table = 'hiring_reports';
    protected $guarded = [];
    public $timestamps = false;
}
