<?php
declare(strict_types=1);

namespace kintai\Bundles\Installed\TimeOff\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Repris du Core à l'étape 4 du plan du contrat (extraction) : la table garde son nom et ses données.
 */
final class TimeoffRequest extends Model
{
    protected $table = 'timeoff_requests';
    protected $guarded = [];
    public $timestamps = false;
}
