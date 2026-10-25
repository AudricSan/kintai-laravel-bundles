<?php
declare(strict_types=1);

namespace kintai\Bundles\Installed\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Repris du Core à l'étape 4 du plan du contrat (extraction) : la table garde son nom et ses données.
 */
final class MessageThread extends Model
{
    protected $table = 'message_threads';
    protected $guarded = [];
    public $timestamps = false;
}
