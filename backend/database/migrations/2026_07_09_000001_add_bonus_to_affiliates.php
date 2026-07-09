<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One-time signup bonus credited on approval (adds to what the affiliate is owed). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $t) {
            $t->unsignedInteger('bonus_cents')->default(0)->after('cpm_cents');
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', fn (Blueprint $t) => $t->dropColumn('bonus_cents'));
    }
};
