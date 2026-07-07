<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// B2B affiliate program: partners embed our widget, we show their users'
// logo on real products, and pay the partner per thousand impressions (CPM).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email')->unique();
            $table->string('website')->nullable();
            $table->string('key', 48)->unique();                 // widget embed key
            $table->unsignedInteger('cpm_cents')->default(1500); // $15.00; program range $15–20
            $table->string('status')->default('pending');        // pending | active | paused
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // one row per affiliate per day — atomic counter increments
        Schema::create('affiliate_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unique(['affiliate_id', 'date']);
        });

        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_stats');
        Schema::dropIfExists('affiliates');
    }
};
