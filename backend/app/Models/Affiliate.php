<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A B2B partner embedding the affiliate widget — paid per 1000 impressions. */
#[Fillable(['name', 'company', 'email', 'website', 'key', 'cpm_cents', 'bonus_cents', 'status', 'notes', 'approved_at'])]
class Affiliate extends Model
{
    protected $casts = ['approved_at' => 'datetime'];

    public function stats(): HasMany
    {
        return $this->hasMany(AffiliateStat::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function impressions(): int
    {
        return (int) $this->stats()->sum('impressions');
    }

    public function clicks(): int
    {
        return (int) $this->stats()->sum('clicks');
    }

    /** impressions ÷ 1000 × CPM */
    public function earnedCents(): int
    {
        return (int) round($this->impressions() / 1000 * $this->cpm_cents);
    }

    public function paidCents(): int
    {
        return (int) $this->payouts()->sum('amount_cents');
    }

    /** Earnings + signup bonus, minus what's already been paid out. */
    public function owedCents(): int
    {
        return max(0, $this->earnedCents() + (int) $this->bonus_cents - $this->paidCents());
    }

    /** Count one event on today's row (atomic upsert-increment). */
    public function track(string $event): void
    {
        $column = $event === 'click' ? 'clicks' : 'impressions';
        $this->stats()->upsert(
            [['affiliate_id' => $this->id, 'date' => now()->toDateString(), $column => 1]],
            ['affiliate_id', 'date'],
            [$column => \Illuminate\Support\Facades\DB::raw("$column + 1")],
        );
    }
}
