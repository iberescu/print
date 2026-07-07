<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Growth batch: coupons, abandoned-cart snapshots, order coupon fields,
// order-email dedupe flag, affiliate approval tracking.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->unsignedTinyInteger('percent_off');
            $table->boolean('first_order_only')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // authed users' carts, snapshotted for the abandoned-cart reminder
        Schema::create('cart_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->json('items');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_code', 40)->nullable()->after('subtotal');
            $table->decimal('discount', 10, 2)->default(0)->after('coupon_code');
            $table->timestamp('confirmation_sent_at')->nullable()->after('status');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', fn (Blueprint $t) => $t->dropColumn('approved_at'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['coupon_code', 'discount', 'confirmation_sent_at']);
        });
        Schema::dropIfExists('cart_reminders');
        Schema::dropIfExists('coupons');
    }
};
