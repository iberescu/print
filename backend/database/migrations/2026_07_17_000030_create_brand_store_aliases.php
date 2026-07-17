<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** RTB House alias pool: pre-provisioned random shop subdomains that live in
 *  the (daily-read) product feed from day one. A new brand store claims a free
 *  alias — its feed images flip to the customer's mockups on the next read,
 *  while events fire immediately against the already-known alias product ids.
 *  Ad clicks on {alias}.host 301 to the real store subdomain. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_store_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 63)->unique();
            $table->unsignedBigInteger('brand_store_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_store_aliases');
    }
};
