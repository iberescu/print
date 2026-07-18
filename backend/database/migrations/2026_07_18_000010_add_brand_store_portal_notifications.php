<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Brand-store portal emails (day 3 + day 10 after creation) — the customer's
 *  email is resolved from their order (stamped with the capture key at
 *  checkout, since kits themselves are anonymous). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_stores', function (Blueprint $table) {
            $table->string('owner_email', 190)->nullable();
            $table->unsignedTinyInteger('portal_emails_sent')->default(0);
            $table->timestamp('portal_email_last_at')->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('brand_kit_key', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('brand_stores', function (Blueprint $table) {
            $table->dropColumn(['owner_email', 'portal_emails_sent', 'portal_email_last_at']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('brand_kit_key');
        });
    }
};
