<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Notebook\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Note du carnet d'équipe (table notebook_entries, créée par la migration du bundle). Repris du Core à l'étape 4 du
 * plan du contrat (extraction) : la table garde son nom et ses données.
 */
final class NotebookEntry extends Model
{
    protected $table = 'notebook_entries';
    protected $guarded = [];
    public $timestamps = false;
}
