<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One editor project (the ?project= uuid): fabric JSON on disk, preview URL,
 * owned by a session until a login claims it. Feeds "My designs" + edit links.
 */
#[Fillable(['id', 'user_id', 'product_slug', 'product_name', 'preview', 'design_path'])]
class DesignProject extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';
}
