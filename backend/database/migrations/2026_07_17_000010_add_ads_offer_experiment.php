<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A/B: "$29 for $250 ads" vs "free $500 credit on $100+ orders" — event log
 *  for the conversion report, plus the variant stamped on orders (webhooks
 *  have no session, so paid-order attribution must ride on the order row). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_events', function (Blueprint $table) {
            $table->id();
            $table->string('test', 40);
            $table->string('variant', 40);
            $table->boolean('has_url')->nullable(); // capture had a website URL (null = no brand kit)
            $table->string('event', 40);            // assigned | offer_added | order_paid
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['test', 'variant', 'event']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('ab_ads_variant', 40)->nullable();
            $table->boolean('ab_ads_has_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_events');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ab_ads_variant', 'ab_ads_has_url']);
        });
    }
};
