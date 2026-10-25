<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\StorePhoto\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Envoi de photos d'un magasin (table store_photo_submissions). Repris du Core à l'étape 4 du plan du contrat
 * (extraction) : la table garde son nom et ses données.
 */
final class StorePhotoSubmission extends Model
{
    protected $table = 'store_photo_submissions';
    protected $guarded = [];
    public $timestamps = false;
}
