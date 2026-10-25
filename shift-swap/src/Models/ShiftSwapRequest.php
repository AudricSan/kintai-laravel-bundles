<?php
declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftSwap\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Repris du Core à l'étape 4 du plan du contrat (extraction) : la table garde son nom et ses données.
 */
final class ShiftSwapRequest extends Model
{
    protected $table = 'shift_swap_requests';
    protected $guarded = [];
    public $timestamps = false;
}
