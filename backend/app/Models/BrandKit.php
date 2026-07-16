<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * In-house upsell brand kit — the internal-engine alternative to a pqsg capture.
 * Stores the captured brand inputs (logo/website/company) plus everything the
 * pipeline generates asynchronously: a brand summary + Google keywords, the
 * "your logo on products" images, and the Layout.ai display ads. Keyed by the
 * same session key the storefront already uses (session('pqsg.key')).
 */
class BrandKit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'extract'  => 'array',
        'summary'  => 'array',
        'products' => 'array',
        'competitors' => 'array',
        'ads'      => 'array',
        'stages'   => 'array',
    ];

    /**
     * Merge one stage's status into the stages map (extract|summary|products|ads).
     * Atomic JSON_SET so parallel workers don't clobber each other's stage keys.
     */
    public function markStage(string $stage, string $state): void
    {
        \DB::statement(
            'UPDATE brand_kits SET stages = JSON_SET(CASE WHEN JSON_TYPE(stages) = ? THEN stages ELSE JSON_OBJECT() END, ?, ?), updated_at = ? WHERE id = ?',
            ['OBJECT', '$.'.$stage, $state, now(), $this->id]
        );
    }

    /**
     * Append generated items to a json array column (products|ads).
     * Atomic JSON_ARRAY_APPEND per item — safe under concurrent workers.
     */
    public function appendItems(string $column, array $items): void
    {
        abort_unless(in_array($column, ['products', 'ads'], true), 500);
        foreach ($items as $item) {
            \DB::statement(
                "UPDATE brand_kits SET {$column} = JSON_ARRAY_APPEND(COALESCE({$column}, JSON_ARRAY()), '$', CAST(? AS JSON)), updated_at = ? WHERE id = ?",
                [json_encode($item), now(), $this->id]
            );
        }
    }
}
