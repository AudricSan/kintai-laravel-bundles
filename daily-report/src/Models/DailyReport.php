<?php
declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Repris du Core à l'étape 4 du plan du contrat (extraction) : la table garde son nom et ses données.
 */
final class DailyReport extends Model
{
    protected $table = 'daily_reports';
    protected $guarded = [];
    public $timestamps = false;
}
