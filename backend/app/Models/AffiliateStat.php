<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['affiliate_id', 'date', 'impressions', 'clicks'])]
class AffiliateStat extends Model
{
    public $timestamps = false;
}
