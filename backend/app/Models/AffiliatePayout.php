<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['affiliate_id', 'amount_cents', 'note'])]
class AffiliatePayout extends Model
{
}
