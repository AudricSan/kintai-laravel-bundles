<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\StorePhoto\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Image d'un envoi de photos (table store_photo_images). Repris du Core à l'étape 4 du plan du contrat (extraction).
 */
final class StorePhotoImage extends Model
{
    protected $table = 'store_photo_images';
    protected $guarded = [];
    public $timestamps = false;
}
