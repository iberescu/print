<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Private Brand Stores: {subdomain}.runmyprint.com — the main shop re-skinned
 *  with a customer's brand, access-gated to @their-domain email login links. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_kit_id')->index();
            $table->string('subdomain', 63)->unique();
            $table->string('company', 160);
            $table->string('email_domain', 190);      // who may log in: *@this
            $table->uuid('token');                     // owner/preview grant (cart iframe)
            $table->json('colors')->nullable();        // {primary, accent, palette[]}
            $table->string('status', 20)->default('ready');
            $table->timestamps();
        });

        Schema::create('brand_store_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_store_id')->index();
            $table->string('email', 190);
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_store_tokens');
        Schema::dropIfExists('brand_stores');
    }
};
