<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOption extends Model
{
    protected $guarded = [];

    protected $casts = [
        'required' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class)->orderBy('sort_order');
    }

    /**
     * Whether picking a different value would change the design surface —
     * size/format values carry their own surface_id (see ImportCatalog), and the
     * name check covers hand-seeded catalogues where surfaces aren't linked.
     * Surface-bound options are locked once a design has been approved.
     */
    public function affectsSurface(): bool
    {
        if ($this->values->contains(fn (OptionValue $v) => $v->surface_id !== null)) {
            return true;
        }

        return (bool) preg_match('/\b(size|format|corner|fold|shape|orientation|die.?cut|dimension)/i', $this->name);
    }
}
